<?php
/**
 * Warmup DB migrations.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Warmup;

defined( 'ABSPATH' ) || exit;

class Install {

    /**
     * Add the delayed_until column to snel_send_queue if it doesn't exist yet.
     */
    public static function maybe_add_columns(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'snel_send_queue';

        $exists = $wpdb->get_var(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = '$table'
             AND COLUMN_NAME = 'delayed_until'"
        );

        if ( ! $exists ) {
            $wpdb->query( "ALTER TABLE $table ADD COLUMN delayed_until datetime DEFAULT NULL AFTER sent_at" );
            $wpdb->query( "ALTER TABLE $table ADD KEY delayed_status (status, delayed_until)" );
        }
    }
}
