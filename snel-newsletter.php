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

require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/core/cron.php';
