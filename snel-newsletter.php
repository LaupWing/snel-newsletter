<?php
/**
 * Plugin Name: Snel Newsletter
 * Description: Lightweight newsletter toolkit by Snelstack. Send, track, grow.
 * Version: 1.0.0
 * Author: Snelstack
 * Author URI: https://snelstack.com
 * License: GPL v2 or later
 * Text Domain: snel-newsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SNEL_NEWSLETTER_VERSION', '1.0.0' );
define( 'SNEL_NEWSLETTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNEL_NEWSLETTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load modules.
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/admin.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/cpt.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/subscribers/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/campaigns/index.php';
require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/settings/index.php';

// Create tables on activation.
register_activation_hook( __FILE__, array( 'Snel\Newsletter\Subscribers\Install', 'create_tables' ) );

// Auto-create tables on version update.
add_action( 'admin_init', function () {
    $db_version = get_option( 'snel_newsletter_db_version', '0' );
    if ( version_compare( $db_version, SNEL_NEWSLETTER_VERSION, '<' ) ) {
        Snel\Newsletter\Subscribers\Install::create_tables();
        update_option( 'snel_newsletter_db_version', SNEL_NEWSLETTER_VERSION );
    }
} );
