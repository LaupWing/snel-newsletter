<?php
/**
 * Admin pages — menu registration, script enqueue.
 *
 * @package SnelNewsletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register admin menu and submenu pages.
 */
add_action( 'admin_menu', function () {
    add_menu_page(
        __( 'Snel Newsletter', 'snel-newsletter' ),
        __( 'Newsletter', 'snel-newsletter' ),
        'manage_options',
        'snel-newsletter',
        function () { snel_newsletter_render_page( 'dashboard' ); },
        'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgdmlld0JveD0iMCAwIDI0IDI0Ij48ZGVmcz48bGluZWFyR3JhZGllbnQgaWQ9ImciIHgxPSIwIiB5MT0iMCIgeDI9IjEiIHkyPSIxIj48c3RvcCBvZmZzZXQ9IjAlIiBzdG9wLWNvbG9yPSIjM2I4MmY2Ii8+PHN0b3Agb2Zmc2V0PSIxMDAlIiBzdG9wLWNvbG9yPSIjN2MzYWVkIi8+PC9saW5lYXJHcmFkaWVudD48L2RlZnM+PGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTIiIGZpbGw9InVybCgjZykiLz48cGF0aCBkPSJNNS41IDguNWExIDEgMCAwIDEgMS0xaDExYTEgMSAwIDAgMSAxIDF2N2ExIDEgMCAwIDEtMSAxaC0xMWExIDEgMCAwIDEtMS0xdi03em0uMy0uMkwxMiAxMi44bDYuMi00LjUiIGZpbGw9Im5vbmUiIHN0cm9rZT0iI2ZmZiIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz48L3N2Zz4=',
        28
    );
    add_submenu_page( 'snel-newsletter', __( 'Dashboard', 'snel-newsletter' ), __( 'Dashboard', 'snel-newsletter' ), 'manage_options', 'snel-newsletter', '__return_null' );
    add_submenu_page( 'snel-newsletter', __( 'Subscribers', 'snel-newsletter' ), __( 'Subscribers', 'snel-newsletter' ), 'manage_options', 'snel-newsletter-subscribers', function () { snel_newsletter_render_page( 'subscribers' ); } );
    add_submenu_page( 'snel-newsletter', __( 'Campaigns', 'snel-newsletter' ), __( 'Campaigns', 'snel-newsletter' ), 'manage_options', 'snel-newsletter-campaigns', function () { snel_newsletter_render_page( 'campaigns' ); } );
    add_submenu_page( 'snel-newsletter', __( 'Tags', 'snel-newsletter' ), __( 'Tags', 'snel-newsletter' ), 'manage_options', 'snel-newsletter-tags', function () { snel_newsletter_render_page( 'tags' ); } );
    add_submenu_page( 'snel-newsletter', __( 'Settings', 'snel-newsletter' ), __( 'Settings', 'snel-newsletter' ), 'manage_options', 'snel-newsletter-settings', function () { snel_newsletter_render_page( 'settings' ); } );
} );

function snel_newsletter_render_page( $page ) {
    error_log( '[snel-newsletter] render_page: ' . $page );
    printf( '<div id="snel-newsletter-root" class="wrap" data-page="%s"></div>', esc_attr( $page ) );
}

/**
 * Enqueue admin React app on Snel Newsletter pages.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    $pages = array(
        'toplevel_page_snel-newsletter',
        'newsletter_page_snel-newsletter-subscribers',
        'newsletter_page_snel-newsletter-campaigns',
        'newsletter_page_snel-newsletter-tags',
        'newsletter_page_snel-newsletter-settings',
    );
    if ( ! in_array( $hook, $pages, true ) ) {
        return;
    }

    $asset_file = SNEL_NEWSLETTER_PLUGIN_DIR . 'build/index.asset.php';
    error_log( '[snel-newsletter] enqueue_scripts hook fired, hook: ' . $hook );
    error_log( '[snel-newsletter] asset file path: ' . $asset_file );
    error_log( '[snel-newsletter] asset file exists: ' . ( file_exists( $asset_file ) ? 'yes' : 'NO' ) );

    if ( ! file_exists( $asset_file ) ) {
        error_log( '[snel-newsletter] ABORTING — build/index.asset.php not found' );
        return;
    }

    $asset = require $asset_file;
    error_log( '[snel-newsletter] asset version: ' . $asset['version'] );

    wp_enqueue_script( 'snel-newsletter-admin', SNEL_NEWSLETTER_PLUGIN_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
    wp_enqueue_style( 'snel-newsletter-admin', SNEL_NEWSLETTER_PLUGIN_URL . 'build/index.css', array( 'wp-components' ), $asset['version'] );

    wp_localize_script( 'snel-newsletter-admin', 'snelNewsletter', array(
        'restUrl' => rest_url( 'snel-newsletter/v1' ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
        'version' => SNEL_NEWSLETTER_VERSION,
    ) );
    error_log( '[snel-newsletter] scripts enqueued successfully' );
} );
