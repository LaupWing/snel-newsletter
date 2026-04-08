<?php
/**
 * Queue database table creation.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Queue;

defined( 'ABSPATH' ) || exit;

class Install {

    public static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'snel_send_queue';

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            subscriber_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            retries tinyint unsigned NOT NULL DEFAULT 0,
            message_id varchar(255) DEFAULT '',
            error_message varchar(500) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_subscriber (campaign_id, subscriber_id),
            KEY status (status),
            KEY campaign_status (campaign_id, status)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
