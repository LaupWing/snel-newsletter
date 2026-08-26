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

if (! defined('ABSPATH')) {
    exit;
}

define('SNEL_NEWSLETTER_VERSION', '1.9.11');
define('SNEL_NEWSLETTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SNEL_NEWSLETTER_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/core/autoload.php';

Snel\Newsletter\Core\Plugin::boot();
