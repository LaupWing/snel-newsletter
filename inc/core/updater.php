<?php
// Updates come from GitHub releases, not wordpress.org.
// Needs `composer install`; without vendor/ the plugin silently falls back to manual updates.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! file_exists( SNEL_NEWSLETTER_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    return;
}

require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'vendor/autoload.php';

YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/LaupWing/snel-newsletter/',
    SNEL_NEWSLETTER_PLUGIN_DIR . 'snel-newsletter.php',
    'snel-newsletter'
);
