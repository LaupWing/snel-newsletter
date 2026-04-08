<?php
/**
 * Database tables — subscribers and tags.
 *
 * @package SnelNewsletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create custom tables on plugin activation.
 */
function snel_newsletter_create_tables() {
    global $wpdb;

    $charset = $wpdb->get_charset_collate();

    $subscribers_table = $wpdb->prefix . 'snel_subscribers';
    $tags_table        = $wpdb->prefix . 'snel_subscriber_tags';

    $sql = "CREATE TABLE $subscribers_table (
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

    CREATE TABLE $tags_table (
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

/**
 * Helper: get the subscribers table name.
 */
function snel_newsletter_subscribers_table() {
    global $wpdb;
    return $wpdb->prefix . 'snel_subscribers';
}

/**
 * Helper: get the tags table name.
 */
function snel_newsletter_tags_table() {
    global $wpdb;
    return $wpdb->prefix . 'snel_subscriber_tags';
}
