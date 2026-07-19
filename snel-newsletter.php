<?php
/**
 * Plugin Name: Snel Newsletter
 * Description: Lightweight newsletter toolkit by Snelstack. Send, track, grow.
 * Version: 1.9.5
 * Author: Snelstack
 * Author URI: https://snelstack.com
 * License: GPL v2 or later
 * Text Domain: snel-newsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Auto-updater — pulls releases from GitHub.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';

    $snel_newsletter_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/LaupWing/snel-newsletter/',
        __FILE__,
        'snel-newsletter'
    );
}

define( 'SNEL_NEWSLETTER_VERSION', '1.9.5' );
define( 'SNEL_NEWSLETTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNEL_NEWSLETTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load modules.
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/logger/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/admin.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/cpt.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/subscribers/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/campaigns/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/ses/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/adapters/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/sender/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/tracking/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/queue/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/automations/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/cpt-sources/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/settings/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/warmup/index.php';

// WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/cli.php';
}

// Create tables on activation.
register_activation_hook( __FILE__, function () {
    Snel\Newsletter\Subscribers\Install::create_tables();
    Snel\Newsletter\Tracking\Install::create_tables();
    Snel\Newsletter\Queue\Install::create_tables();
    Snel\Newsletter\Logger\Install::create_tables();
    Snel\Newsletter\Automations\Install::create_tables();
    Snel\Newsletter\Warmup\Install::maybe_add_columns();
} );

// Auto-create tables on version update.
add_action( 'admin_init', function () {
    $db_version = get_option( 'snel_newsletter_db_version', '0' );
    if ( version_compare( $db_version, SNEL_NEWSLETTER_VERSION, '<' ) ) {
        Snel\Newsletter\Subscribers\Install::create_tables();
        Snel\Newsletter\Tracking\Install::create_tables();
        Snel\Newsletter\Queue\Install::create_tables();
        Snel\Newsletter\Logger\Install::create_tables();
        Snel\Newsletter\Automations\Install::create_tables();
        Snel\Newsletter\Warmup\Install::maybe_add_columns();
        update_option( 'snel_newsletter_db_version', SNEL_NEWSLETTER_VERSION );
    }
} );

// Self-heal: reschedule the queue cron if it's gone but rows are still waiting.
add_action( 'admin_init', function () {
    if ( wp_next_scheduled( Snel\Newsletter\Queue\Processor::CRON_HOOK ) ) {
        return;
    }

    global $wpdb;
    $has_work = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}snel_send_queue
         WHERE status IN ('pending', 'retrying')
            OR (status = 'delayed' AND delayed_until <= NOW())"
    );

    if ( $has_work ) {
        wp_schedule_single_event( time() + 5, Snel\Newsletter\Queue\Processor::CRON_HOOK );
        Snel\Newsletter\Logger\Logger::info( 'queue', 'Cron hook was missing — rescheduled by self-heal', array( 'rows' => (int) $has_work ) );
    }
} );
