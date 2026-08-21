<?php
/**
 * Plugin Name: Snel Newsletter
 * Description: Lightweight newsletter toolkit by Snelstack. Send, track, grow.
 * Version: 1.9.11
 * Author: Snelstack
 * Author URI: https://snelstack.com
 * License: GPL v2 or later
 * Text Domain: snel-newsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SNEL_NEWSLETTER_VERSION', '1.9.11' );
define( 'SNEL_NEWSLETTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNEL_NEWSLETTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/core/updater.php';

require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/logger/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/admin.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/cpt.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/subscribers/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/campaigns/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/lanes/Lane.php';
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

require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/core/install.php';

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
