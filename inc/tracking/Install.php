<?php
/**
 * Tracking database table creation.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Tracking;

defined( 'ABSPATH' ) || exit;

class Install {

    public static function create_tables() {
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
    }
}
