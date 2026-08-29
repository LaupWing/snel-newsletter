<?php

namespace Snel\Newsletter\Warmup;

defined( 'ABSPATH' ) || exit;

// Each lane keeps an independent ramp so a fresh automation domain starts at
// Day 1 even while broadcasts are further along. Unknown lanes map to broadcast.
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
        // Every enable starts a FRESH ramp: a stale start date would leave
        // current_day() past the schedule, meaning unlimited cap and no ramp.
        self::reset_ramp( $lane );
        update_option( self::opt_enabled( $lane ), 1 );
    }

    public static function disable( string $lane = self::LANE_BROADCAST ): void {
        update_option( self::opt_enabled( $lane ), 0 );
    }

    // Restart at Day 1: resets the start date and today's counter.
    public static function reset_ramp( string $lane = self::LANE_BROADCAST ): void {
        update_option( self::opt_started_at( $lane ), current_time( 'Y-m-d' ) );
        update_option( Guard::opt_daily_sent( $lane ), 0, false );
        update_option( Guard::opt_daily_date( $lane ), current_time( 'Y-m-d' ), false );
    }

    // 1-based: the start date itself is day 1.
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
