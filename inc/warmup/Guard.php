<?php

namespace Snel\Newsletter\Warmup;

use Snel\Newsletter\Logger\Logger;

defined( 'ABSPATH' ) || exit;

class Guard {

    public static function opt_daily_sent( string $lane ): string {
        $lane = in_array( $lane, Settings::lanes(), true ) ? $lane : Settings::LANE_BROADCAST;
        return 'snel_warmup_' . $lane . '_daily_sent';
    }

    public static function opt_daily_date( string $lane ): string {
        $lane = in_array( $lane, Settings::lanes(), true ) ? $lane : Settings::LANE_BROADCAST;
        return 'snel_warmup_' . $lane . '_daily_date';
    }

    // null = unlimited (warmup complete).
    public static function daily_remaining( string $lane = Settings::LANE_BROADCAST ): ?int {
        $day = Settings::current_day( $lane );
        $cap = Ramp::cap_for_day( $day );

        if ( $cap === null ) {
            return null;
        }

        self::maybe_reset_daily_counter( $lane );

        $sent = (int) get_option( self::opt_daily_sent( $lane ), 0 );
        return max( 0, $cap - $sent );
    }

    public static function increment_daily( string $lane = Settings::LANE_BROADCAST ): void {
        self::maybe_reset_daily_counter( $lane );
        $sent = (int) get_option( self::opt_daily_sent( $lane ), 0 );
        update_option( self::opt_daily_sent( $lane ), $sent + 1, false );
    }

    public static function sent_today( string $lane = Settings::LANE_BROADCAST ): int {
        self::maybe_reset_daily_counter( $lane );
        return (int) get_option( self::opt_daily_sent( $lane ), 0 );
    }

    // Runs after queue_campaign() inserts rows; marks cooldown subscribers as
    // delayed. Cooldown is per-subscriber and lane-agnostic. Returns rows delayed.
    // One set-based pass instead of a query per subscriber: the old loop held the
    // publish request open for minutes on large lists (the 300s timeout).
    public static function apply_cooldowns( int $campaign_id ): int {
        global $wpdb;

        $queue = $wpdb->prefix . 'snel_send_queue';

        $delayed = (int) $wpdb->query( $wpdb->prepare(
            "UPDATE $queue q
             INNER JOIN (
                 SELECT subscriber_id, MAX(sent_at) AS last_sent
                 FROM $queue
                 WHERE status = 'sent'
                 GROUP BY subscriber_id
             ) ls ON ls.subscriber_id = q.subscriber_id
             SET q.status = 'delayed',
                 q.delayed_until = DATE_ADD(ls.last_sent, INTERVAL %d DAY)
             WHERE q.campaign_id = %d
               AND q.status = 'pending'
               AND DATE_ADD(ls.last_sent, INTERVAL %d DAY) > %s",
            Settings::COOLDOWN_DAYS,
            $campaign_id,
            Settings::COOLDOWN_DAYS,
            current_time( 'mysql' )
        ) );

        if ( $delayed > 0 ) {
            Logger::info( 'warmup', 'Cooldowns applied to campaign', array(
                'campaign_id' => $campaign_id,
                'delayed'     => $delayed,
            ) );
        }

        return $delayed;
    }

    private static function maybe_reset_daily_counter( string $lane ): void {
        $today     = current_time( 'Y-m-d' );
        $last_date = get_option( self::opt_daily_date( $lane ), '' );

        if ( $last_date === $today ) {
            return;
        }

        update_option( self::opt_daily_sent( $lane ), 0, false );
        update_option( self::opt_daily_date( $lane ), $today, false );

        Logger::info( 'warmup', 'Daily counter reset', array(
            'lane'       => $lane,
            'date'       => $today,
            'warmup_day' => Settings::current_day( $lane ),
            'cap'        => Ramp::cap_for_day( Settings::current_day( $lane ) ) ?? 'unlimited',
        ) );
    }
}
