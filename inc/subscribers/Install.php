<?php
/**
 * Subscriber database table creation.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Subscribers;

defined( 'ABSPATH' ) || exit;

class Install {

    public static function create_tables() {
        global $wpdb;

        $charset         = $wpdb->get_charset_collate();
        $subscribers     = $wpdb->prefix . 'snel_subscribers';
        $subscriber_tags = $wpdb->prefix . 'snel_subscriber_tags';

        $sql = "CREATE TABLE $subscribers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            name varchar(255) DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            unsubscribe_token varchar(64) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset;

        CREATE TABLE $subscriber_tags (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) unsigned NOT NULL,
            tag varchar(100) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY subscriber_tag (subscriber_id, tag),
            KEY tag (tag),
            KEY subscriber_id (subscriber_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
