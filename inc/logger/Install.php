<?php
/**
 * Logger database table creation.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Logger;

defined( 'ABSPATH' ) || exit;

class Install {

    public static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'snel_newsletter_logs';

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(10) NOT NULL DEFAULT 'info',
            context varchar(50) NOT NULL DEFAULT '',
            message varchar(500) NOT NULL DEFAULT '',
            data longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY context (context),
            KEY created_at (created_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
