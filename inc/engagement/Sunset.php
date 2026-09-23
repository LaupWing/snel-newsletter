<?php

namespace Snel\Newsletter\Engagement;

use Snel\Newsletter\Logger\Logger;

defined( 'ABSPATH' ) || exit;

// SOT:SUNSET — subscribers who received the last THRESHOLD broadcasts and neither opened
// nor clicked become 'inactive': still in the list, no longer mailed. Ten unopened sends
// (~2-3 months here) matches the common 90-180 day sunset window; pixel-blocking clients
// are the known false-positive risk, hence the click check and the reversible status.
class Sunset {

    const THRESHOLD = 10;
    const CRON_HOOK = 'snel_newsletter_sunset';

    public static function run(): int {
        global $wpdb;

        $recent = Waves::recent_broadcast_ids( 0, self::THRESHOLD );
        if ( count( $recent ) < self::THRESHOLD ) {
            return 0;
        }

        $subs     = $wpdb->prefix . 'snel_subscribers';
        $queue    = $wpdb->prefix . 'snel_send_queue';
        $tracking = $wpdb->prefix . 'snel_tracking';
        $ids_csv  = implode( ',', $recent );

        $targets = $wpdb->get_col( $wpdb->prepare(
            "SELECT s.id
             FROM $subs s
             INNER JOIN $queue q ON q.subscriber_id = s.id AND q.campaign_id IN ($ids_csv) AND q.status = 'sent'
             WHERE s.status = 'active'
             GROUP BY s.id
             HAVING COUNT(DISTINCT q.campaign_id) >= %d
                AND SUM( EXISTS(
                    SELECT 1 FROM $tracking t
                    WHERE t.campaign_id = q.campaign_id AND t.subscriber_id = s.id AND t.type IN ('open', 'click')
                ) ) = 0",
            self::THRESHOLD
        ) );

        if ( empty( $targets ) ) {
            return 0;
        }

        $targets_csv = implode( ',', array_map( 'intval', $targets ) );
        $wpdb->query( "UPDATE $subs SET status = 'inactive' WHERE id IN ($targets_csv) AND status = 'active'" );

        Logger::info( 'engagement', 'Sunset: unengaged subscribers set to inactive', array(
            'count'     => count( $targets ),
            'threshold' => self::THRESHOLD,
            'lookback'  => $recent,
        ) );

        return count( $targets );
    }

    public static function ensure_scheduled(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( strtotime( 'tomorrow 03:00' ), 'daily', self::CRON_HOOK );
        }
    }
}
