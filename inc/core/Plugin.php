<?php

namespace Snel\Newsletter\Core;

defined( 'ABSPATH' ) || exit;

// SOT:BOOT — the two lists below are the plugin. Core runs always; each module wires itself in its index.php.
class Plugin {

    private const CORE = array( 'updater', 'admin', 'cpt', 'editor', 'install', 'cron' );

    private const MODULES = array(
        'logger',
        'subscribers',
        'campaigns',
        'adapters',
        'sender',
        'tracking',
        'queue',
        'automations',
        'cpt-sources',
        'settings',
        'warmup',
    );

    public static function boot(): void {
        foreach ( self::CORE as $file ) {
            require_once SNEL_NEWSLETTER_PLUGIN_DIR . "inc/core/{$file}.php";
        }

        foreach ( self::MODULES as $module ) {
            require_once SNEL_NEWSLETTER_PLUGIN_DIR . "inc/{$module}/index.php";
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/cli.php';
        }
    }
}
