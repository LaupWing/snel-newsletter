<?php
/**
 * Warmup settings — on/off state and day tracking, per sending lane.
 *
 * Two lanes ('broadcast', 'automation') each keep an independent ramp so a
 * fresh automation domain starts at Day 1 even while broadcasts are on Day 5.
 * All option keys are namespaced by lane; the legacy global keys map to the
 * 'broadcast' lane for backward compatibility.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Warmup;

defined( 'ABSPATH' ) || exit;

class Settings {

    const COOLDOWN_DAYS = 2;

    const LANE_BROADCAST  = 'broadcast';
    const LANE_AUTOMATION = 'automation';

    public static function lanes(): array {
        return array( self::LANE_BROADCAST, self::LANE_AUTOMATION );
    }

    private static function normalize_lane( string $lane ): string {
        return in_array( $lane, self::lanes(), true ) ? $lane : self::LANE_BROADCAST;
    }

    public static function opt_enabled( string $lane ): string {
        return 'snel_warmup_' . self::normalize_lane( $lane ) . '_enabled';
    }

    public static function opt_started_at( string $lane ): string {
        return 'snel_warmup_' . self::normalize_lane( $lane ) . '_started_at';
    }

    public static function is_enabled( string $lane = self::LANE_BROADCAST ): bool {
        return (bool) get_option( self::opt_enabled( $lane ), false );
    }

    public static function enable( string $lane = self::LANE_BROADCAST ): void {
        // Every enable starts a FRESH ramp. The old code set the start date only
        // once and never cleared it, so re-enabling a warmup that had already
        // run left current_day() past the schedule → unlimited cap → no ramp.
        self::reset_ramp( $lane );
        update_option( self::opt_enabled( $lane ), 1 );
    }

    public static function disable( string $lane = self::LANE_BROADCAST ): void {
        update_option( self::opt_enabled( $lane ), 0 );
    }

    /**
     * Restart the ramp at Day 1 — resets the start date and today's counter.
     * Used by enable() and the explicit "Restart" action.
     */
    public static function reset_ramp( string $lane = self::LANE_BROADCAST ): void {
        update_option( self::opt_started_at( $lane ), current_time( 'Y-m-d' ) );
        update_option( Guard::opt_daily_sent( $lane ), 0, false );
        update_option( Guard::opt_daily_date( $lane ), current_time( 'Y-m-d' ), false );
    }

    /**
     * How many days into warmup we are for a lane (1-based).
     */
    public static function current_day( string $lane = self::LANE_BROADCAST ): int {
        $started = get_option( self::opt_started_at( $lane ) );
        if ( ! $started ) {
            return 1;
        }
        $diff = (int) floor(
            ( strtotime( current_time( 'Y-m-d' ) ) - strtotime( $started ) ) / DAY_IN_SECONDS
        );
        return max( 1, $diff + 1 );
    }

    public static function started_at( string $lane = self::LANE_BROADCAST ): ?string {
        return get_option( self::opt_started_at( $lane ) ) ?: null;
    }
}
