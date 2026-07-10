<?php
/**
 * Tracking database queries.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Tracking;

defined( 'ABSPATH' ) || exit;

class Model {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'snel_tracking';
    }

    /**
     * Sign a click-tracking URL so the redirect target can't be forged.
     */
    public static function sign( $campaign_id, $subscriber_id, $url ) {
        return hash_hmac( 'sha256', $campaign_id . '|' . $subscriber_id . '|' . $url, wp_salt( 'auth' ) );
    }

    /**
     * Log a tracking event.
     */
    public static function log( $campaign_id, $subscriber_id, $type, $url = '' ) {
        global $wpdb;

        // Prevent duplicate open events (one per subscriber per campaign).
        if ( $type === 'open' ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM " . self::table() . " WHERE campaign_id = %d AND subscriber_id = %d AND type = 'open' LIMIT 1",
                $campaign_id, $subscriber_id
            ) );
            if ( $exists ) return;
        }

        $wpdb->insert( self::table(), array(
            'campaign_id'   => $campaign_id,
            'subscriber_id' => $subscriber_id,
            'type'          => $type,
            'url'           => $url,
        ), array( '%d', '%d', '%s', '%s' ) );
    }

    /**
     * Get stats for a campaign.
     */
    public static function campaign_stats( $campaign_id ) {
        global $wpdb;
        $table = self::table();

        return array(
            'opens'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT subscriber_id) FROM $table WHERE campaign_id = %d AND type = 'open'", $campaign_id ) ),
            'clicks'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE campaign_id = %d AND type = 'click'", $campaign_id ) ),
            'unique_clicks' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT subscriber_id) FROM $table WHERE campaign_id = %d AND type = 'click'", $campaign_id ) ),
        );
    }

    /**
     * Get stats for a subscriber.
     */
    public static function subscriber_stats( $subscriber_id ) {
        global $wpdb;
        $table = self::table();

        return array(
            'emails_received' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT campaign_id) FROM $table WHERE subscriber_id = %d", $subscriber_id ) ),
            'opens'           => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE subscriber_id = %d AND type = 'open'", $subscriber_id ) ),
            'clicks'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE subscriber_id = %d AND type = 'click'", $subscriber_id ) ),
            'last_open'       => $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM $table WHERE subscriber_id = %d AND type = 'open' ORDER BY created_at DESC LIMIT 1", $subscriber_id ) ),
        );
    }
}
