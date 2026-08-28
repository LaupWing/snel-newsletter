<?php
// Tables are (re)created on activation and on every version bump; dbDelta makes that idempotent.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// SOT:INSTALL
function snel_newsletter_install() {
    Snel\Newsletter\Subscribers\Install::create_tables();
    Snel\Newsletter\Tracking\Install::create_tables();
    Snel\Newsletter\Queue\Install::create_tables();
    Snel\Newsletter\Logger\Install::create_tables();
    Snel\Newsletter\Automations\Install::create_tables();
}

register_activation_hook( SNEL_NEWSLETTER_PLUGIN_DIR . 'snel-newsletter.php', 'snel_newsletter_install' );

add_action( 'admin_init', function () {
    if ( version_compare( get_option( 'snel_newsletter_db_version', '0' ), SNEL_NEWSLETTER_VERSION, '<' ) ) {
        snel_newsletter_install();
        update_option( 'snel_newsletter_db_version', SNEL_NEWSLETTER_VERSION );
    }
} );
