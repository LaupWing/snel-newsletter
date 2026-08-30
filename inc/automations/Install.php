<?php

namespace Snel\Newsletter\Automations;

defined( 'ABSPATH' ) || exit;

class Install {

    public static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        $automations = $wpdb->prefix . 'snel_automations';
        $runs        = $wpdb->prefix . 'snel_automation_runs';
        $events      = $wpdb->prefix . 'snel_automation_events';

        $sql = "CREATE TABLE $automations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'paused',
            trigger_type varchar(20) NOT NULL DEFAULT 'tag',
            trigger_tag varchar(190) NOT NULL DEFAULT '',
            steps longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY trigger_tag (trigger_tag)
        ) $charset;

        CREATE TABLE $runs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            automation_id bigint(20) unsigned NOT NULL,
            subscriber_id bigint(20) unsigned NOT NULL,
            position varchar(100) NOT NULL DEFAULT '[0]',
            status varchar(20) NOT NULL DEFAULT 'active',
            next_run_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            claim varchar(64) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY automation_subscriber (automation_id, subscriber_id),
            KEY due (status, next_run_at)
        ) $charset;

        CREATE TABLE $events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            automation_id bigint(20) unsigned NOT NULL,
            subscriber_id bigint(20) unsigned NOT NULL,
            step_path varchar(100) NOT NULL DEFAULT '',
            step_type varchar(20) NOT NULL DEFAULT '',
            detail varchar(190) NOT NULL DEFAULT '',
            level varchar(10) NOT NULL DEFAULT 'info',
            message varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY automation_step (automation_id, step_path),
            KEY automation_time (automation_id, created_at),
            KEY subscriber (subscriber_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
