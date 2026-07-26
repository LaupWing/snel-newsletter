<?php
/**
 * Warmup guard — daily-cap accounting per sending lane.
 *
 * @package SnelNewsletter
 */

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

    /**
     * How many emails can still go out today on this lane. null = unlimited.
     */
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

    /**
     * Record one successful send against a lane's daily counter.
     */
    public static function increment_daily( string $lane = Settings::LANE_BROADCAST ): void {
        self::maybe_reset_daily_counter( $lane );
        $sent = (int) get_option( self::opt_daily_sent( $lane ), 0 );
        update_option( self::opt_daily_sent( $lane ), $sent + 1, false );
    }

    /**
     * Emails sent today on this lane.
     */
    public static function sent_today( string $lane = Settings::LANE_BROADCAST ): int {
        self::maybe_reset_daily_counter( $lane );
        return (int) get_option( self::opt_daily_sent( $lane ), 0 );
    }

    /**
     * After queue_campaign() inserts rows, mark cooldown subscribers as delayed.
     * Cooldown is per-subscriber and lane-agnostic. Returns rows delayed.
     */
    public static function apply_cooldowns( int $campaign_id ): int {
        global $wpdb;

        $queue = $wpdb->prefix . 'snel_send_queue';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, subscriber_id FROM $queue WHERE campaign_id = %d AND status = 'pending'",
            $campaign_id
        ) );

        if ( empty( $rows ) ) {
            return 0;
        }

        $delayed = 0;

        foreach ( $rows as $row ) {
            $until = Cooldown::locked_until( (int) $row->subscriber_id );

            if ( ! $until ) {
                continue;
            }

            $wpdb->update(
                $queue,
                array( 'status' => 'delayed', 'delayed_until' => $until ),
                array( 'id'     => $row->id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            $delayed++;

            Logger::debug( 'warmup', 'Subscriber delayed — cooldown active', array(
                'subscriber_id' => (int) $row->subscriber_id,
                'campaign_id'   => $campaign_id,
                'delayed_until' => $until,
            ) );
        }

        if ( $delayed > 0 ) {
            Logger::info( 'warmup', 'Cooldowns applied to campaign', array(
                'campaign_id'  => $campaign_id,
                'delayed'      => $delayed,
                'total_queued' => count( $rows ),
            ) );
        } else {
            Logger::debug( 'warmup', 'No cooldowns needed for campaign', array(
                'campaign_id'  => $campaign_id,
                'total_queued' => count( $rows ),
            ) );
        }

        return $delayed;
    }

    /**
     * Reset a lane's daily counter when the date has changed.
     */
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
