<?php
/**
 * Queue Processor.
 *
 * Handles queuing subscribers for a campaign and processing the send queue
 * in batches via WP Cron. Supports warm-up (gradual increase in batch size).
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Queue;

defined( 'ABSPATH' ) || exit;

class Processor {

    const BATCH_SIZE     = 50;   // Emails per cron run.
    const MAX_RETRIES    = 3;    // Max retry attempts per email.
    const CRON_HOOK      = 'snel_newsletter_process_queue';
    const CRON_INTERVAL  = 60;   // Seconds between batches.

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'snel_send_queue';
    }

    /**
     * Queue all active subscribers (or tag-filtered) for a campaign.
     */
    public static function queue_campaign( $campaign_id, $tags = array() ) {
        global $wpdb;

        $sub_table  = $wpdb->prefix . 'snel_subscribers';
        $tags_table = $wpdb->prefix . 'snel_subscriber_tags';
        $queue      = self::table();

        // Get subscriber IDs. A custom filter audience (set in the editor) wins
        // over tags; either way we only ever send to active subscribers.
        $filters = get_post_meta( $campaign_id, '_snel_nl_audience_filters', true );

        if ( is_array( $filters ) && ! empty( $filters ) ) {
            // Force the audience to active subscribers regardless of what was
            // filtered on — a broadcast must never hit unsubscribed/bounced.
            $filters[] = array( 'field' => 'status', 'operator' => 'is', 'value' => 'active' );
            $ids = \Snel\Newsletter\Subscribers\Model::ids_for_filters( $filters );
        } elseif ( ! empty( $tags ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $tags ), '%s' ) );
            $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT s.id FROM $sub_table s
                 INNER JOIN $tags_table t ON t.subscriber_id = s.id
                 WHERE s.status = 'active' AND t.tag IN ($placeholders)",
                $tags
            ) );
        } else {
            $ids = $wpdb->get_col( "SELECT id FROM $sub_table WHERE status = 'active'" );
        }

        if ( empty( $ids ) ) {
            return 0;
        }

        // Insert into queue (ignore duplicates).
        $values = array();
        $place  = array();
        foreach ( $ids as $id ) {
            $values[] = $campaign_id;
            $values[] = (int) $id;
            $place[]  = '(%d, %d)';
        }

        $place_sql = implode( ', ', $place );
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO $queue (campaign_id, subscriber_id) VALUES $place_sql",
            $values
        ) );

        $total = count( $ids );

        // Apply subscriber cooldowns if warmup is enabled.
        if ( \Snel\Newsletter\Warmup\Settings::is_enabled() ) {
            \Snel\Newsletter\Warmup\Guard::apply_cooldowns( $campaign_id );
        }

        // Update campaign meta.
        update_post_meta( $campaign_id, '_snel_nl_send_status', 'sending' );
        update_post_meta( $campaign_id, '_snel_nl_total_recipients', $total );
        update_post_meta( $campaign_id, '_snel_nl_sent_count', 0 );

        // Schedule the cron to start processing.
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + 5, self::CRON_HOOK );
        }

        return $total;
    }

    /**
     * Process a batch of queued emails.
     * Called by WP Cron.
     */
    public static function process_batch() {
        global $wpdb;

        $queue    = self::table();
        $adapter  = \Snel\Newsletter\Adapters\Manager::get_active();
        $settings = get_option( 'snel_newsletter_settings', array() );

        $from_email = $settings['from_email'] ?? '';
        $from_name  = $settings['from_name'] ?? '';
        $reply_to   = $settings['reply_to'] ?? '';

        if ( ! $from_email || ! $adapter->is_configured() ) {
            \Snel\Newsletter\Logger\Logger::error( 'queue', 'Batch aborted — missing from_email or adapter not configured', array(
                'from_email'    => $from_email ?: '(empty)',
                'is_configured' => $adapter->is_configured(),
            ) );
            wp_schedule_single_event( time() + self::CRON_INTERVAL, self::CRON_HOOK );
            return;
        }

        // Per-lane warmup budgets (int remaining, or null = unlimited). A lane
        // whose cap is already spent is excluded from this batch's fetch so the
        // other lane can still drain — no starvation between lanes.
        $workflow_ids = \Snel\Newsletter\Campaigns\Model::workflow_ids();
        $lane_budget  = array();
        foreach ( \Snel\Newsletter\Warmup\Settings::lanes() as $lane ) {
            $lane_budget[ $lane ] = \Snel\Newsletter\Warmup\Settings::is_enabled( $lane )
                ? \Snel\Newsletter\Warmup\Guard::daily_remaining( $lane )
                : null;
        }

        $auto_capped      = ( $lane_budget[ \Snel\Newsletter\Warmup\Settings::LANE_AUTOMATION ] ?? null ) === 0;
        $broadcast_capped = ( $lane_budget[ \Snel\Newsletter\Warmup\Settings::LANE_BROADCAST ] ?? null ) === 0;

        // Exclude fully-capped lanes at the SQL level. workflow_ids are ints.
        $exclude_sql = '';
        if ( $workflow_ids ) {
            $ids_csv = implode( ',', $workflow_ids );
            if ( $auto_capped ) {
                $exclude_sql .= " AND q.campaign_id NOT IN ($ids_csv)";
            }
            if ( $broadcast_capped ) {
                $exclude_sql .= " AND q.campaign_id IN ($ids_csv)";
            }
        } elseif ( $broadcast_capped ) {
            // No automation campaigns exist, so a capped broadcast lane = nothing to send.
            $exclude_sql .= ' AND 1=0';
        }

        // Get a batch of pending emails. Also picks up delayed rows whose wait has expired.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT q.*, s.email, s.name, s.unsubscribe_token
             FROM $queue q
             INNER JOIN {$wpdb->prefix}snel_subscribers s ON s.id = q.subscriber_id
             WHERE ( q.status IN ('pending', 'retrying')
                OR (q.status = 'delayed' AND q.delayed_until <= %s) )
                $exclude_sql
             ORDER BY q.id ASC
             LIMIT %d",
            current_time( 'mysql' ),
            self::BATCH_SIZE
        ) );

        if ( empty( $rows ) ) {
            // Nothing fetchable *right now* — but that doesn't mean we're done.
            // Rows may be waiting because their lane hit its warmup cap, or
            // sitting in 'delayed' with a future delayed_until. Finalizing here
            // would abandon them AND end the cron chain, orphaning the campaign.

            // 1. Cap-blocked: pending/retrying rows exist but every lane is
            //    spent for today → try again after midnight.
            $pending = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM $queue WHERE status IN ('pending', 'retrying')"
            );
            if ( $pending > 0 ) {
                \Snel\Newsletter\Logger\Logger::info( 'warmup', 'All lanes at their daily cap — pausing until tomorrow', array(
                    'pending' => $pending,
                ) );
                wp_schedule_single_event( strtotime( 'tomorrow midnight' ) + 60, self::CRON_HOOK );
                return;
            }

            // 2. Only future-delayed rows left → reschedule for the next due one.
            $next_due = $wpdb->get_var(
                "SELECT MIN(delayed_until) FROM $queue
                 WHERE status = 'delayed' AND delayed_until IS NOT NULL"
            );
            if ( $next_due ) {
                $delay = max( 30, strtotime( $next_due ) - time() );
                wp_schedule_single_event( time() + $delay, self::CRON_HOOK );
                return;
            }

            // 3. Queue truly drained — mark campaigns as sent.
            self::finalize_campaigns();
            return;
        }

        \Snel\Newsletter\Logger\Logger::info( 'queue', 'Batch started', array( 'count' => count( $rows ) ) );

        foreach ( $rows as $row ) {
            $post = get_post( $row->campaign_id );
            if ( ! $post ) {
                $wpdb->update( $queue, array( 'status' => 'failed', 'error_message' => 'Campaign not found' ), array( 'id' => $row->id ) );
                continue;
            }

            // Resolve which lane this send belongs to, its from-identity, and
            // its warmup budget. Automation emails send from their own domain so
            // a bad flow never burns the broadcast reputation.
            $lane = in_array( (int) $row->campaign_id, $workflow_ids, true )
                ? \Snel\Newsletter\Warmup\Settings::LANE_AUTOMATION
                : \Snel\Newsletter\Warmup\Settings::LANE_BROADCAST;

            if ( $lane_budget[ $lane ] !== null && $lane_budget[ $lane ] <= 0 ) {
                // Lane cap reached mid-batch — leave this row pending for tomorrow.
                continue;
            }

            $identity      = \Snel\Newsletter\Lanes\Lane::identity( $lane );
            $row_from      = $identity['from_email'] ?: $from_email;
            $row_from_name = $identity['from_name'] ?: $from_name;
            $row_reply_to  = $identity['reply_to'];

            // Build the email.
            $content = apply_filters( 'the_content', $post->post_content );
            $preview = get_post_meta( $row->campaign_id, '_snel_nl_preview_text', true ) ?: '';
            $unsub   = rest_url( "snel-newsletter/v1/t/unsubscribe?token={$row->unsubscribe_token}" );

            $html = \Snel\Newsletter\Sender\EmailTemplate::render( $content, $row_from_name, $unsub, $preview );

            // Inject tracking pixel if adapter doesn't handle it.
            if ( ! $adapter->handles_open_tracking() ) {
                $pixel_url = rest_url( "snel-newsletter/v1/t/open?c={$row->campaign_id}&s={$row->subscriber_id}" );
                $pixel     = '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" style="display:block;width:1px;height:1px;border:0;" alt="">';
                $html      = str_replace( '</body>', $pixel . '</body>', $html );
            }

            // Rewrite links for click tracking if adapter doesn't handle it.
            if ( ! $adapter->handles_click_tracking() ) {
                $html = self::rewrite_links( $html, $row->campaign_id, $row->subscriber_id );
            }

            $text    = \Snel\Newsletter\Sender\EmailTemplate::to_plain_text( $html );
            $subject = $post->post_title;

            // Add List-Unsubscribe header.
            $headers = array(
                'List-Unsubscribe'      => '<' . $unsub . '>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            );

            // Send from this lane's identity.
            $result = $adapter->send( $row_from, $row_from_name, $row->email, $subject, $html, $text, $row_reply_to, $headers );

            if ( $result['success'] ) {
                $wpdb->update( $queue, array(
                    'status'     => 'sent',
                    'message_id' => $result['message_id'] ?? '',
                    'sent_at'    => current_time( 'mysql' ),
                ), array( 'id' => $row->id ) );

                // Increment sent count.
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = '_snel_nl_sent_count'",
                    $row->campaign_id
                ) );

                // Track against this lane's warmup cap (both the persistent
                // daily counter and the in-batch budget).
                if ( \Snel\Newsletter\Warmup\Settings::is_enabled( $lane ) ) {
                    \Snel\Newsletter\Warmup\Guard::increment_daily( $lane );
                    if ( $lane_budget[ $lane ] !== null ) {
                        $lane_budget[ $lane ]--;
                    }
                }
            } else {
                $retries = $row->retries + 1;
                $status  = $retries >= self::MAX_RETRIES ? 'failed' : 'retrying';
                $error   = $result['error'] ?? 'Unknown error';

                $wpdb->update( $queue, array(
                    'status'        => $status,
                    'retries'       => $retries,
                    'error_message' => $error,
                ), array( 'id' => $row->id ) );

                $level = $status === 'failed' ? 'error' : 'warning';
                \Snel\Newsletter\Logger\Logger::$level( 'queue', 'Send ' . $status, array(
                    'to'          => $row->email,
                    'campaign_id' => $row->campaign_id,
                    'retries'     => $retries,
                    'error'       => $error,
                ) );
            }
        }

        \Snel\Newsletter\Logger\Logger::info( 'queue', 'Batch finished', array( 'count' => count( $rows ) ) );

        // Refresh open/click stats for all campaigns touched in this batch.
        $campaign_ids = array_unique( array_column( (array) $rows, 'campaign_id' ) );
        foreach ( $campaign_ids as $cid ) {
            $stats = \Snel\Newsletter\Tracking\Model::campaign_stats( $cid );
            update_post_meta( $cid, '_snel_nl_opened', $stats['opens'] );
            update_post_meta( $cid, '_snel_nl_clicked', $stats['unique_clicks'] );
        }

        // Schedule next batch.
        wp_schedule_single_event( time() + self::CRON_INTERVAL, self::CRON_HOOK );
    }

    /**
     * Rewrite links in HTML for click tracking.
     */
    private static function rewrite_links( $html, $campaign_id, $subscriber_id ) {
        return preg_replace_callback(
            '/<a\s+([^>]*?)href="([^"]*?)"([^>]*?)>/i',
            function ( $matches ) use ( $campaign_id, $subscriber_id ) {
                $url = $matches[2];

                // Don't track unsubscribe links or anchors.
                if ( strpos( $url, 'unsubscribe' ) !== false || strpos( $url, '#' ) === 0 ) {
                    return $matches[0];
                }

                $hash      = \Snel\Newsletter\Tracking\Model::sign( $campaign_id, $subscriber_id, $url );
                $track_url = rest_url( 'snel-newsletter/v1/t/click' ) . '?c=' . $campaign_id . '&s=' . $subscriber_id . '&url=' . urlencode( $url ) . '&h=' . $hash;

                return '<a ' . $matches[1] . 'href="' . esc_url( $track_url ) . '"' . $matches[3] . '>';
            },
            $html
        );
    }

    /**
     * Check for campaigns with no more pending emails and mark as sent.
     */
    private static function finalize_campaigns() {
        global $wpdb;

        $queue = self::table();

        // Find campaigns that still have active (unsent) queue rows.
        // 'delayed' rows that haven't fired yet count as active.
        $campaign_ids = $wpdb->get_col(
            "SELECT DISTINCT campaign_id FROM $queue WHERE status IN ('pending', 'retrying', 'delayed')"
        );

        // Get all campaigns currently marked as sending.
        $sending = get_posts( array(
            'post_type'   => 'snel_newsletter',
            'post_status' => 'publish',
            'meta_key'    => '_snel_nl_send_status',
            'meta_value'  => 'sending',
            'fields'      => 'ids',
            'numberposts' => -1,
        ) );

        foreach ( $sending as $cid ) {
            if ( ! in_array( $cid, $campaign_ids ) ) {
                update_post_meta( $cid, '_snel_nl_send_status', 'sent' );

                // Update final stats from tracking table.
                $stats = \Snel\Newsletter\Tracking\Model::campaign_stats( $cid );
                update_post_meta( $cid, '_snel_nl_opened', $stats['opens'] );
                update_post_meta( $cid, '_snel_nl_clicked', $stats['unique_clicks'] );
            }
        }
    }
}
