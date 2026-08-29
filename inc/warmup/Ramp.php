<?php

namespace Snel\Newsletter\Warmup;

defined( 'ABSPATH' ) || exit;

class Ramp {

    // Max sends per warmup day.
    private static $schedule = array(
        1 => 200,
        2 => 500,
        3 => 1000,
        4 => 2000,
        5 => 2000,
        6 => 5000,
        7 => 5000,
    );

    // null = unlimited (warmup complete); gaps in the schedule fall back to 200.
    public static function cap_for_day( int $day ): ?int {
        if ( $day >= 8 ) {
            return null;
        }
        return self::$schedule[ $day ] ?? 200;
    }
}
