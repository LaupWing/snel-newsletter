<?php
/**
 * Warmup ramp schedule.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Warmup;

defined( 'ABSPATH' ) || exit;

class Ramp {

    /**
     * Max emails per day per warmup day. null = unlimited (warmup complete).
     */
    private static $schedule = array(
        1 => 200,
        2 => 500,
        3 => 1000,
        4 => 2000,
        5 => 2000,
        6 => 5000,
        7 => 5000,
    );

    /**
     * Returns the send cap for a given warmup day, or null when unlimited.
     */
    public static function cap_for_day( int $day ): ?int {
        if ( $day >= 8 ) {
            return null;
        }
        return self::$schedule[ $day ] ?? 200;
    }
}
