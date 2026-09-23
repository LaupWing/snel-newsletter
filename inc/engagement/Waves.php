<?php

namespace Snel\Newsletter\Engagement;

use Snel\Newsletter\Logger\Logger;

defined( 'ABSPATH' ) || exit;

// SOT:WAVES — a broadcast leaves in three waves ordered by recent engagement, so the
// first signals a mailbox provider sees come from people who open nearly everything.
// Tiers over the last LOOKBACK broadcasts: core (opened >= 4 of 5, or nearly all when
// fewer received, or never received one yet) goes now; middle (opened some) after one
// step; cold (received >= 1, opened none) after two steps. Rows already parked by the
// cooldown keep their later date.
class Waves {

    const LOOKBACK     = 5;
    const STEP_MINUTES = 30;

    public static function apply( int $campaign_id ): array {
        global $wpdb;

        $recent = self::recent_broadcast_ids( $campaign_id );
        if ( empty( $recent ) ) {
            return array( 'core' => 0, 'middle' => 0, 'cold' => 0 );
        }

        $queue    = $wpdb->prefix . 'snel_send_queue';
        $tracking = $wpdb->prefix . 'snel_tracking';
        $ids_csv  = implode( ',', array_map( 'intval', $recent ) );
        $now      = current_time( 'mysql' );

        $engagement = "SELECT q2.subscriber_id,
                              COUNT(*) AS received,
                              SUM( EXISTS(
                                  SELECT 1 FROM $tracking t
                                  WHERE t.campaign_id = q2.campaign_id AND t.subscriber_id = q2.subscriber_id AND t.type = 'open'
                              ) ) AS opened
                       FROM $queue q2
                       WHERE q2.campaign_id IN ($ids_csv) AND q2.status = 'sent'
                       GROUP BY q2.subscriber_id";

        $is_core = "( (e.received >= 4 AND e.opened >= 4) OR (e.received < 4 AND e.opened >= CEIL(e.received * 0.75)) )";

        $cold = (int) $wpdb->query( $wpdb->prepare(
            "UPDATE $queue q
             INNER JOIN ( $engagement ) e ON e.subscriber_id = q.subscriber_id
             SET q.status = 'delayed', q.delayed_until = DATE_ADD(%s, INTERVAL %d MINUTE)
             WHERE q.campaign_id = %d AND q.status = 'pending' AND e.opened = 0",
            $now, self::STEP_MINUTES * 2, $campaign_id
        ) );

        $middle = (int) $wpdb->query( $wpdb->prepare(
            "UPDATE $queue q
             INNER JOIN ( $engagement ) e ON e.subscriber_id = q.subscriber_id
             SET q.status = 'delayed', q.delayed_until = DATE_ADD(%s, INTERVAL %d MINUTE)
             WHERE q.campaign_id = %d AND q.status = 'pending' AND NOT $is_core",
            $now, self::STEP_MINUTES, $campaign_id
        ) );

        $core = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $queue WHERE campaign_id = %d AND status = 'pending'",
            $campaign_id
        ) );

        Logger::info( 'engagement', 'Waves applied to campaign', array(
            'campaign_id' => $campaign_id,
            'core'        => $core,
            'middle'      => $middle,
            'cold'        => $cold,
            'lookback'    => $recent,
        ) );

        return array( 'core' => $core, 'middle' => $middle, 'cold' => $cold );
    }

    // The last LOOKBACK broadcasts that actually went out, newest first, excluding this one and automation emails.
    public static function recent_broadcast_ids( int $exclude_id, int $limit = self::LOOKBACK ): array {
        global $wpdb;

        $queue    = $wpdb->prefix . 'snel_send_queue';
        $workflow = \Snel\Newsletter\Campaigns\Model::workflow_ids();
        $skip     = implode( ',', array_map( 'intval', array_merge( $workflow, array( $exclude_id ) ) ) );

        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT campaign_id FROM $queue
             WHERE status = 'sent' AND campaign_id NOT IN ($skip)
             GROUP BY campaign_id
             HAVING COUNT(*) >= 100
             ORDER BY MIN(sent_at) DESC
             LIMIT %d",
            $limit
        ) ) );
    }
}
