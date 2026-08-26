<?php
/**
 * Sender feature — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

new Snel\Newsletter\Sender\Rest();

/**
 * Register server-side render for newsletter blocks.
 */
add_action( 'init', function () {
    register_block_type( SNEL_NEWSLETTER_PLUGIN_DIR . 'src/blocks/newsletter-button/block.json', array(
        'render_callback' => function ( $attributes ) {
            return Snel\Newsletter\Sender\EmailTemplate::render_button_block( $attributes );
        },
    ) );

    register_block_type( SNEL_NEWSLETTER_PLUGIN_DIR . 'src/blocks/newsletter-download/block.json', array(
        'render_callback' => function ( $attributes ) {
            return Snel\Newsletter\Sender\EmailTemplate::render_download_block( $attributes );
        },
    ) );
} );
