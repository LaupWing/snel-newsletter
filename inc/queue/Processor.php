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

        // Get subscriber IDs.
        if ( ! empty( $tags ) ) {
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
            $place[]  = '(%d, %d, %s)';
        }

        $place_sql = implode( ', ', $place );
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO $queue (campaign_id, subscriber_id, status) VALUES $place_sql",
            $values
        ) );

        $total = count( $ids );

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
            return;
        }

        // Get a batch of pending emails.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT q.*, s.email, s.name, s.unsubscribe_token
             FROM $queue q
             INNER JOIN {$wpdb->prefix}snel_subscribers s ON s.id = q.subscriber_id
             WHERE q.status IN ('pending', 'retrying')
             ORDER BY q.id ASC
             LIMIT %d",
            self::BATCH_SIZE
        ) );

        if ( empty( $rows ) ) {
            // No more emails — mark campaigns as sent.
            self::finalize_campaigns();
            return;
        }

        $inject_tracking = ! $adapter->handles_open_tracking() || ! $adapter->handles_click_tracking();

        foreach ( $rows as $row ) {
            $post = get_post( $row->campaign_id );
            if ( ! $post ) {
                $wpdb->update( $queue, array( 'status' => 'failed', 'error_message' => 'Campaign not found' ), array( 'id' => $row->id ) );
                continue;
            }

            // Build the email.
            $content = apply_filters( 'the_content', $post->post_content );
            $preview = get_post_meta( $row->campaign_id, '_snel_nl_preview_text', true ) ?: '';
            $unsub   = rest_url( "snel-newsletter/v1/t/unsubscribe?token={$row->unsubscribe_token}" );

            $html = \Snel\Newsletter\Sender\EmailTemplate::render( $content, $from_name, $unsub, $preview );

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

            // Send.
            $result = $adapter->send( $from_email, $from_name, $row->email, $subject, $html, $text, $reply_to, $headers );

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
            } else {
                $retries = $row->retries + 1;
                $status  = $retries >= self::MAX_RETRIES ? 'failed' : 'retrying';

                $wpdb->update( $queue, array(
                    'status'        => $status,
                    'retries'       => $retries,
                    'error_message' => $result['error'] ?? 'Unknown error',
                ), array( 'id' => $row->id ) );
            }
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

                $track_url = rest_url( 'snel-newsletter/v1/t/click' ) . '?c=' . $campaign_id . '&s=' . $subscriber_id . '&url=' . urlencode( $url );

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

        // Find campaigns that are "sending" but have no pending/retrying emails.
        $campaign_ids = $wpdb->get_col(
            "SELECT DISTINCT campaign_id FROM $queue WHERE status IN ('pending', 'retrying')"
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
