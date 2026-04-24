<?php
/**
 * Warmup settings — on/off state and day tracking.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Warmup;

defined( 'ABSPATH' ) || exit;

class Settings {

    const OPT_ENABLED    = 'snel_warmup_enabled';
    const OPT_STARTED_AT = 'snel_warmup_started_at';
    const COOLDOWN_DAYS  = 2;

    public static function is_enabled(): bool {
        return (bool) get_option( self::OPT_ENABLED, false );
    }

    public static function enable(): void {
        // Record start date only on first enable.
        if ( ! get_option( self::OPT_STARTED_AT ) ) {
            update_option( self::OPT_STARTED_AT, current_time( 'Y-m-d' ) );
        }
        update_option( self::OPT_ENABLED, 1 );
    }

    public static function disable(): void {
        update_option( self::OPT_ENABLED, 0 );
    }

    /**
     * How many days into warmup we are (1-based).
     */
    public static function current_day(): int {
        $started = get_option( self::OPT_STARTED_AT );
        if ( ! $started ) {
            return 1;
        }
        $diff = (int) floor(
            ( strtotime( current_time( 'Y-m-d' ) ) - strtotime( $started ) ) / DAY_IN_SECONDS
        );
        return max( 1, $diff + 1 );
    }

    public static function started_at(): ?string {
        return get_option( self::OPT_STARTED_AT ) ?: null;
    }
}
