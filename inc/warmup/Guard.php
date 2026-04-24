<?php
/**
 * Warmup guard — the single entry point for the queue processor.
 *
 * Responsibilities:
 *  - Track how many emails have been sent today vs the daily cap.
 *  - Mark subscribers as delayed after a campaign is queued.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Warmup;

use Snel\Newsletter\Logger\Logger;

defined( 'ABSPATH' ) || exit;

class Guard {

    const OPT_DAILY_SENT = 'snel_warmup_daily_sent';
    const OPT_DAILY_DATE = 'snel_warmup_daily_date';

    /**
     * How many emails can still go out today. Returns null when unlimited.
     */
    public static function daily_remaining(): ?int {
        $day = Settings::current_day();
        $cap = Ramp::cap_for_day( $day );

        if ( $cap === null ) {
            return null;
        }

        self::maybe_reset_daily_counter();

        $sent = (int) get_option( self::OPT_DAILY_SENT, 0 );
        return max( 0, $cap - $sent );
    }

    /**
     * Record one successful send against the daily counter.
     */
    public static function increment_daily(): void {
        self::maybe_reset_daily_counter();
        $sent = (int) get_option( self::OPT_DAILY_SENT, 0 );
        update_option( self::OPT_DAILY_SENT, $sent + 1, false );
    }

    /**
     * After queue_campaign() inserts rows, mark cooldown subscribers as delayed.
     * Returns the number of rows delayed.
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
     * Reset the daily counter when the date has changed.
     */
    private static function maybe_reset_daily_counter(): void {
        $today     = current_time( 'Y-m-d' );
        $last_date = get_option( self::OPT_DAILY_DATE, '' );

        if ( $last_date === $today ) {
            return;
        }

        $day = Settings::current_day();
        $cap = Ramp::cap_for_day( $day );

        update_option( self::OPT_DAILY_SENT, 0, false );
        update_option( self::OPT_DAILY_DATE, $today, false );

        Logger::info( 'warmup', 'Daily counter reset', array(
            'date'       => $today,
            'warmup_day' => $day,
            'cap'        => $cap ?? 'unlimited',
        ) );
    }
}
