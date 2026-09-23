<?php

namespace Snel\Newsletter\Tracking;

defined( 'ABSPATH' ) || exit;

class Install {

    public static function create_tables(): void {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'snel_tracking';

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            subscriber_id bigint(20) unsigned NOT NULL,
            type varchar(20) NOT NULL,
            url varchar(2048) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_subscriber (campaign_id, subscriber_id),
            KEY type (type),
            KEY campaign_type (campaign_id, type)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // SES feedback per sent message (delivery, delay, bounce, complaint); joined to the queue on message_id.
        $events = $wpdb->prefix . 'snel_delivery_events';
        $sql    = "CREATE TABLE $events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
            subscriber_id bigint(20) unsigned NOT NULL DEFAULT 0,
            message_id varchar(100) NOT NULL DEFAULT '',
            type varchar(20) NOT NULL,
            detail varchar(500) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_type (campaign_id, type),
            KEY message_id (message_id)
        ) $charset;";
        dbDelta( $sql );
    }
}
