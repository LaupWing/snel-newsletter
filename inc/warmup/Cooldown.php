<?php
/**
 * Per-subscriber cooldown check.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Warmup;

defined( 'ABSPATH' ) || exit;

class Cooldown {

    /**
     * Returns the datetime until which this subscriber is in cooldown,
     * or null if they're free to receive an email right now.
     */
    public static function locked_until( int $subscriber_id ): ?string {
        global $wpdb;

        $queue = $wpdb->prefix . 'snel_send_queue';

        $last_sent = $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(sent_at) FROM $queue WHERE subscriber_id = %d AND status = 'sent'",
            $subscriber_id
        ) );

        if ( ! $last_sent ) {
            return null;
        }

        $cooldown_ends = gmdate(
            'Y-m-d H:i:s',
            strtotime( $last_sent ) + ( Settings::COOLDOWN_DAYS * DAY_IN_SECONDS )
        );

        if ( $cooldown_ends > current_time( 'mysql' ) ) {
            return $cooldown_ends;
        }

        return null;
    }
}
