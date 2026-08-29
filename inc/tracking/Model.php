<?php

namespace Snel\Newsletter\Tracking;

defined( 'ABSPATH' ) || exit;

class Model {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'snel_tracking';
    }

    // HMAC ties the redirect URL to campaign + subscriber so click targets
    // can't be forged. Must match at send time and click time.
    public static function sign( int $campaign_id, int $subscriber_id, string $url ): string {
        return hash_hmac( 'sha256', $campaign_id . '|' . $subscriber_id . '|' . $url, wp_salt( 'auth' ) );
    }

    public static function log( int $campaign_id, int $subscriber_id, string $type, string $url = '' ): void {
        global $wpdb;

        // Invariant: at most one open row per subscriber per campaign.
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

    public static function campaign_stats( int $campaign_id ): array {
        global $wpdb;
        $table = self::table();

        return array(
            'opens'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT subscriber_id) FROM $table WHERE campaign_id = %d AND type = 'open'", $campaign_id ) ),
            'clicks'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE campaign_id = %d AND type = 'click'", $campaign_id ) ),
            'unique_clicks' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT subscriber_id) FROM $table WHERE campaign_id = %d AND type = 'click'", $campaign_id ) ),
        );
    }

    public static function subscriber_stats( int $subscriber_id ): array {
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
