<?php

namespace Snel\Newsletter\Queue;

defined( 'ABSPATH' ) || exit;

class Processor {

    const BATCH_SIZE     = 50;   // Emails per cron run.
    const MAX_RETRIES    = 3;    // Max retry attempts per email.
    const CRON_HOOK      = 'snel_newsletter_process_queue';
    const CRON_INTERVAL  = 60;   // Seconds between batches.

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'snel_send_queue';
    }

    // Only active subscribers ever come out of here; filters win over tags.
    public static function audience_ids( int $campaign_id, array $tags = array() ): array {
        global $wpdb;

        $sub_table  = $wpdb->prefix . 'snel_subscribers';
        $tags_table = $wpdb->prefix . 'snel_subscriber_tags';

        $filters = get_post_meta( $campaign_id, '_snel_nl_audience_filters', true );

        // Safety net: if the campaign targets tags but none made it into meta,
        // abort — never fall through to the everyone-branch below.
        $audience = get_post_meta( $campaign_id, '_snel_nl_audience', true );
        if ( $audience === 'tags' && empty( $tags ) && ( ! is_array( $filters ) || empty( $filters ) ) ) {
            \Snel\Newsletter\Logger\Logger::error( 'queue', 'Campaign audience is "tags" but no tags saved — queue aborted', array(
                'campaign_id' => $campaign_id,
            ) );
            return array();
        }

        if ( is_array( $filters ) && ! empty( $filters ) ) {
            // Force active regardless of the chosen filters — a broadcast must
            // never hit unsubscribed/bounced.
            $filters[] = array( 'field' => 'status', 'operator' => 'is', 'value' => 'active' );
            return \Snel\Newsletter\Subscribers\Model::ids_for_filters( $filters );
        }

        if ( ! empty( $tags ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $tags ), '%s' ) );
            return $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT s.id FROM $sub_table s
                 INNER JOIN $tags_table t ON t.subscriber_id = s.id
                 WHERE s.status = 'active' AND t.tag IN ($placeholders)",
                $tags
            ) );
        }

        return $wpdb->get_col( "SELECT id FROM $sub_table WHERE status = 'active'" );
    }

    public static function queue_campaign( int $campaign_id, array $tags = array() ): int {
        global $wpdb;

        $queue = self::table();
        $ids   = self::audience_ids( $campaign_id, $tags );

        if ( empty( $ids ) ) {
            return 0;
        }

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

        if ( \Snel\Newsletter\Warmup\Settings::is_enabled() ) {
            \Snel\Newsletter\Warmup\Guard::apply_cooldowns( $campaign_id );
        }

        update_post_meta( $campaign_id, '_snel_nl_send_status', 'sending' );
        update_post_meta( $campaign_id, '_snel_nl_total_recipients', $total );
        update_post_meta( $campaign_id, '_snel_nl_sent_count', 0 );

        self::ensure_soon();

        return $total;
    }

    // Only subscribers still in the audience resume; sent rows stay sent, so no doubles.
    public static function resume_campaign( int $campaign_id ): int {
        global $wpdb;

        $queue = self::table();
        $tags  = get_post_meta( $campaign_id, '_snel_nl_tags', true ) ?: array();
        $ids   = self::audience_ids( $campaign_id, $tags );

        if ( empty( $ids ) ) {
            return 0;
        }

        $ids_csv = implode( ',', array_map( 'intval', $ids ) );
        $resumed = (int) $wpdb->query( $wpdb->prepare(
            "UPDATE $queue SET status = 'pending', error_message = ''
             WHERE campaign_id = %d AND status = 'cancelled' AND subscriber_id IN ($ids_csv)",
            $campaign_id
        ) );

        // Rebuild progress totals from the queue itself — the campaign may have
        // been partially sent before it was cancelled.
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $queue WHERE campaign_id = %d AND status IN ('sent', 'pending', 'retrying', 'delayed')",
            $campaign_id
        ) );
        $sent = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $queue WHERE campaign_id = %d AND status = 'sent'",
            $campaign_id
        ) );

        update_post_meta( $campaign_id, '_snel_nl_send_status', 'sending' );
        update_post_meta( $campaign_id, '_snel_nl_total_recipients', $total );
        update_post_meta( $campaign_id, '_snel_nl_sent_count', $sent );

        \Snel\Newsletter\Logger\Logger::info( 'queue', 'Campaign resumed', array(
            'campaign_id' => $campaign_id,
            'resumed'     => $resumed,
            'already_sent' => $sent,
        ) );

        self::ensure_soon();

        return $resumed;
    }

    // Make the drainer run within seconds. A prior batch may have parked it far
    // out (capped lane paused until midnight); new work must not wait for that.
    public static function ensure_soon(): void {
        $existing = wp_next_scheduled( self::CRON_HOOK );

        if ( $existing && $existing > time() + 60 ) {
            wp_unschedule_event( $existing, self::CRON_HOOK );
            $existing = false;
        }

        if ( ! $existing ) {
            wp_schedule_single_event( time() + 5, self::CRON_HOOK );
        }
    }

    public static function process_batch(): void {
        global $wpdb;

        $adapter  = \Snel\Newsletter\Adapters\Manager::get_active();
        $settings = get_option( 'snel_newsletter_settings', array() );

        $from_email = $settings['from_email'] ?? '';
        $from_name  = $settings['from_name'] ?? '';

        if ( ! $from_email || ! $adapter->is_configured() ) {
            \Snel\Newsletter\Logger\Logger::error( 'queue', 'Batch aborted — missing from_email or adapter not configured', array(
                'from_email'    => $from_email ?: '(empty)',
                'is_configured' => $adapter->is_configured(),
            ) );
            wp_schedule_single_event( time() + self::CRON_INTERVAL, self::CRON_HOOK );
            return;
        }

        self::cancel_inactive_rows();
        self::release_stale_claims();

        $workflow_ids = \Snel\Newsletter\Campaigns\Model::workflow_ids();
        $lane_budget  = self::lane_budgets();
        $rows         = self::claim_batch( self::capped_lane_sql( $workflow_ids, $lane_budget ) );

        if ( empty( $rows ) ) {
            self::handle_empty_batch();
            return;
        }

        \Snel\Newsletter\Logger\Logger::info( 'queue', 'Batch started', array( 'count' => count( $rows ) ) );

        foreach ( $rows as $row ) {
            self::send_row( $row, $adapter, $from_email, $from_name, $workflow_ids, $lane_budget );
        }

        \Snel\Newsletter\Logger\Logger::info( 'queue', 'Batch finished', array( 'count' => count( $rows ) ) );

        self::refresh_campaign_stats( $rows );

        wp_schedule_single_event( time() + self::CRON_INTERVAL, self::CRON_HOOK );
    }

    // Per-lane warmup budget: int remaining, null = unlimited.
    // Watchdog body: re-arm the drainer if its chain died, or pull a far-parked
    // event forward while sendable rows wait. Registered in core/cron.php.
    public static function watchdog(): void {
        $next = wp_next_scheduled( self::CRON_HOOK );

        if ( $next && $next <= time() + 120 ) {
            return;
        }

        if ( self::has_pending_work() ) {
            self::ensure_soon();
            \Snel\Newsletter\Logger\Logger::info( 'queue', 'Watchdog armed the drainer', array(
                'was_next' => $next ? gmdate( 'c', $next ) : null,
            ) );
        }
    }

    // Single source for "is there queue work"; watchdog and self-heal both use it.
    public static function has_pending_work(): bool {
        global $wpdb;
        $queue = self::table();
        return (bool) $wpdb->get_var(
            "SELECT COUNT(*) FROM $queue
             WHERE status IN ('pending', 'retrying')
                OR (status = 'delayed' AND delayed_until <= NOW())"
        );
    }

    private static function lane_budgets(): array {
        $budget = array();
        foreach ( \Snel\Newsletter\Warmup\Settings::lanes() as $lane ) {
            $budget[ $lane ] = \Snel\Newsletter\Warmup\Settings::is_enabled( $lane )
                ? \Snel\Newsletter\Warmup\Guard::daily_remaining( $lane )
                : null;
        }
        return $budget;
    }

    // A spent lane is excluded from the fetch so the other lane still drains — no starvation.
    private static function capped_lane_sql( array $workflow_ids, array $lane_budget ): string {
        $auto_capped      = ( $lane_budget[ \Snel\Newsletter\Warmup\Settings::LANE_AUTOMATION ] ?? null ) === 0;
        $broadcast_capped = ( $lane_budget[ \Snel\Newsletter\Warmup\Settings::LANE_BROADCAST ] ?? null ) === 0;

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

        return $exclude_sql;
    }

    // Unsubscribed/bounced after queueing must never receive the campaign (invariant 2).
    // Cancelling here also stops their rows from keeping a campaign on 'sending' forever.
    private static function cancel_inactive_rows(): void {
        global $wpdb;
        $queue = self::table();

        $wpdb->query(
            "UPDATE $queue q
             INNER JOIN {$wpdb->prefix}snel_subscribers s ON s.id = q.subscriber_id
             SET q.status = 'cancelled', q.error_message = 'Subscriber no longer active'
             WHERE q.status IN ('pending', 'retrying', 'delayed')
               AND s.status != 'active'"
        );
    }

    // SOT:CLAIM — claim before send (invariant 6): the UPDATE flips rows to 'processing'
    // atomically with a unique token, so overlapping runs can never pick up the same row.
    private static function claim_batch( string $exclude_sql ): array {
        global $wpdb;
        $queue = self::table();
        $token = uniqid( 'claim-', true );

        $wpdb->query( $wpdb->prepare(
            "UPDATE $queue q
             SET q.status = 'processing', q.message_id = %s, q.claimed_at = NOW()
             WHERE ( q.status IN ('pending', 'retrying')
                OR (q.status = 'delayed' AND q.delayed_until <= %s) )
                $exclude_sql
             ORDER BY q.id ASC
             LIMIT %d",
            $token,
            current_time( 'mysql' ),
            self::BATCH_SIZE
        ) );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT q.*, s.email, s.name, s.unsubscribe_token
             FROM $queue q
             INNER JOIN {$wpdb->prefix}snel_subscribers s ON s.id = q.subscriber_id
             WHERE q.status = 'processing' AND q.message_id = %s AND s.status = 'active'",
            $token
        ) );
    }

    // A batch that dies mid-run leaves rows stuck on 'processing'; give them back after 15 min.
    private static function release_stale_claims(): void {
        global $wpdb;
        $queue = self::table();

        $wpdb->query(
            "UPDATE $queue
             SET status = 'retrying', message_id = '', claimed_at = NULL
             WHERE status = 'processing' AND claimed_at < NOW() - INTERVAL 15 MINUTE"
        );
    }

    // Nothing fetchable now != done: rows may be cap-blocked or future-delayed.
    // Finalizing too early would abandon them and end the cron chain.
    private static function handle_empty_batch(): void {
        global $wpdb;
        $queue = self::table();

        // Another batch is mid-flight; let it finish, just keep the chain alive.
        $processing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $queue WHERE status = 'processing'" );
        if ( $processing > 0 ) {
            wp_schedule_single_event( time() + self::CRON_INTERVAL, self::CRON_HOOK );
            return;
        }

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

        $next_due = $wpdb->get_var(
            "SELECT MIN(delayed_until) FROM $queue
             WHERE status = 'delayed' AND delayed_until IS NOT NULL"
        );
        if ( $next_due ) {
            $delay = max( 30, strtotime( $next_due ) - time() );
            wp_schedule_single_event( time() + $delay, self::CRON_HOOK );
            return;
        }

        self::finalize_campaigns();
    }

    private static function send_row( object $row, $adapter, string $from_email, string $from_name, array $workflow_ids, array &$lane_budget ): void {
        global $wpdb;
        $queue = self::table();

        $post = get_post( $row->campaign_id );
        if ( ! $post ) {
            $wpdb->update( $queue, array( 'status' => 'failed', 'error_message' => 'Campaign not found' ), array( 'id' => $row->id ) );
            return;
        }

        // A cancelled campaign must never send, even with rows still pending
        // (cancel racing an in-flight batch, or rows reset by hand).
        if ( get_post_meta( $row->campaign_id, '_snel_nl_send_status', true ) === 'cancelled' ) {
            $wpdb->update( $queue, array( 'status' => 'cancelled' ), array( 'id' => $row->id ) );
            return;
        }

        // Automation sends from its own domain so a bad flow never burns the broadcast reputation.
        $lane = in_array( (int) $row->campaign_id, $workflow_ids, true )
            ? \Snel\Newsletter\Warmup\Settings::LANE_AUTOMATION
            : \Snel\Newsletter\Warmup\Settings::LANE_BROADCAST;

        if ( $lane_budget[ $lane ] !== null && $lane_budget[ $lane ] <= 0 ) {
            // Lane cap reached mid-batch — release the claim so the row sends tomorrow.
            $wpdb->update( $queue, array( 'status' => 'pending', 'message_id' => '', 'claimed_at' => null ), array( 'id' => $row->id ) );
            return;
        }

        $identity      = \Snel\Newsletter\Lanes\Lane::identity( $lane );
        $row_from      = $identity['from_email'] ?: $from_email;
        $row_from_name = $identity['from_name'] ?: $from_name;
        $row_reply_to  = $identity['reply_to'];

        $content = apply_filters( 'the_content', $post->post_content );
        $preview = get_post_meta( $row->campaign_id, '_snel_nl_preview_text', true ) ?: '';
        $unsub   = rest_url( "snel-newsletter/v1/t/unsubscribe?token={$row->unsubscribe_token}" );

        $html = \Snel\Newsletter\Sender\EmailTemplate::render( $content, $row_from_name, $unsub, $preview );

        if ( ! $adapter->handles_open_tracking() ) {
            $pixel_url = rest_url( "snel-newsletter/v1/t/open?c={$row->campaign_id}&s={$row->subscriber_id}" );
            $pixel     = '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" style="display:block;width:1px;height:1px;border:0;" alt="">';
            $html      = str_replace( '</body>', $pixel . '</body>', $html );
        }

        if ( ! $adapter->handles_click_tracking() ) {
            $html = self::rewrite_links( $html, $row->campaign_id, $row->subscriber_id );
        }

        $text    = \Snel\Newsletter\Sender\EmailTemplate::to_plain_text( $html );
        $subject = $post->post_title;

        $headers = array(
            'List-Unsubscribe'      => '<' . $unsub . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        );

        $result = $adapter->send( $row_from, $row_from_name, $row->email, $subject, $html, $text, $row_reply_to, $headers );

        self::record_result( $row, $result, $lane, $lane_budget );
    }

    private static function record_result( object $row, array $result, string $lane, array &$lane_budget ): void {
        global $wpdb;
        $queue = self::table();

        if ( $result['success'] ) {
            $wpdb->update( $queue, array(
                'status'     => 'sent',
                'message_id' => $result['message_id'] ?? '',
                'sent_at'    => current_time( 'mysql' ),
            ), array( 'id' => $row->id ) );

            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = '_snel_nl_sent_count'",
                $row->campaign_id
            ) );

            if ( \Snel\Newsletter\Warmup\Settings::is_enabled( $lane ) ) {
                \Snel\Newsletter\Warmup\Guard::increment_daily( $lane );
                if ( $lane_budget[ $lane ] !== null ) {
                    $lane_budget[ $lane ]--;
                }
            }
            return;
        }

        $retries = $row->retries + 1;
        $status  = $retries >= self::MAX_RETRIES ? 'failed' : 'retrying';
        $error   = $result['error'] ?? 'Unknown error';

        $wpdb->update( $queue, array(
            'status'        => $status,
            'retries'       => $retries,
            'message_id'    => '',
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

    private static function refresh_campaign_stats( array $rows ): void {
        $campaign_ids = array_unique( array_column( $rows, 'campaign_id' ) );
        foreach ( $campaign_ids as $cid ) {
            $stats = \Snel\Newsletter\Tracking\Model::campaign_stats( $cid );
            update_post_meta( $cid, '_snel_nl_opened', $stats['opens'] );
            update_post_meta( $cid, '_snel_nl_clicked', $stats['unique_clicks'] );
        }
    }

    private static function rewrite_links( string $html, $campaign_id, $subscriber_id ): string {
        return preg_replace_callback(
            '/<a\s+([^>]*?)href="([^"]*?)"([^>]*?)>/i',
            function ( $matches ) use ( $campaign_id, $subscriber_id ) {
                $url = $matches[2];

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

    // Mark campaigns with no unsent rows as sent. Unfired 'delayed' rows still
    // count as active work.
    private static function finalize_campaigns(): void {
        global $wpdb;

        $queue = self::table();

        $campaign_ids = $wpdb->get_col(
            "SELECT DISTINCT campaign_id FROM $queue WHERE status IN ('pending', 'retrying', 'delayed', 'processing')"
        );

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
                self::refresh_campaign_stats( array( (object) array( 'campaign_id' => $cid ) ) );
            }
        }
    }
}
