<?php
/**
 * Automations feature — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/Install.php';
require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/Engine.php';
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/Rest.php';

new Snel\Newsletter\Automations\Rest();

// Cron tick.
add_action( Snel\Newsletter\Automations\Engine::CRON_HOOK, array( 'Snel\Newsletter\Automations\Engine', 'tick' ) );

// Tag-added trigger.
add_action( 'snel_newsletter_tags_added', array( 'Snel\Newsletter\Automations\Engine', 'on_tags_added' ), 10, 2 );

// Self-heal: reschedule the tick if runs are still in flight but the cron is gone.
add_action( 'admin_init', function () {
    if ( wp_next_scheduled( Snel\Newsletter\Automations\Engine::CRON_HOOK ) ) {
        return;
    }

    global $wpdb;
    $has_work = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}snel_automation_runs r
         INNER JOIN {$wpdb->prefix}snel_automations a ON a.id = r.automation_id AND a.status = 'active'
         WHERE r.status IN ('active', 'waiting')"
    );

    if ( $has_work ) {
        Snel\Newsletter\Automations\Engine::ensure_scheduled();
    }
} );
