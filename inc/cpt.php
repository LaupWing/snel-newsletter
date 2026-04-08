<?php
/**
 * Newsletter custom post type.
 *
 * @package SnelNewsletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the newsletter CPT.
 */
add_action( 'init', function () {
    register_post_type( 'snel_newsletter', array(
        'labels'       => array(
            'name'               => __( 'Newsletters', 'snel-newsletter' ),
            'singular_name'      => __( 'Newsletter', 'snel-newsletter' ),
            'add_new'            => __( 'New Campaign', 'snel-newsletter' ),
            'add_new_item'       => __( 'New Campaign', 'snel-newsletter' ),
            'edit_item'          => __( 'Edit Campaign', 'snel-newsletter' ),
            'view_item'          => __( 'View Campaign', 'snel-newsletter' ),
            'search_items'       => __( 'Search Campaigns', 'snel-newsletter' ),
            'not_found'          => __( 'No campaigns found', 'snel-newsletter' ),
            'not_found_in_trash' => __( 'No campaigns found in trash', 'snel-newsletter' ),
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => false, // We handle the menu ourselves.
        'show_in_rest' => true,  // Required for Gutenberg.
        'supports'     => array( 'title', 'editor' ),
        'has_archive'  => false,
        'rewrite'      => false,
    ) );
} );

/**
 * Redirect the default CPT list (edit.php?post_type=snel_newsletter)
 * to our React campaigns page.
 */
add_action( 'admin_init', function () {
    global $pagenow;
    if ( $pagenow === 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'snel_newsletter' ) {
        wp_safe_redirect( admin_url( 'admin.php?page=snel-newsletter-campaigns' ) );
        exit;
    }
} );

/**
 * Keep the Newsletter menu highlighted when editing a newsletter post.
 */
add_filter( 'parent_file', function ( $parent_file ) {
    global $post_type;
    if ( $post_type === 'snel_newsletter' ) {
        return 'snel-newsletter';
    }
    return $parent_file;
} );

/**
 * Enqueue editor scripts on the newsletter CPT only.
 */
add_action( 'enqueue_block_editor_assets', function () {
    global $post_type;
    if ( $post_type !== 'snel_newsletter' ) {
        return;
    }

    $asset_file = SNEL_NEWSLETTER_PLUGIN_DIR . 'build/editor.asset.php';
    if ( ! file_exists( $asset_file ) ) {
        return;
    }

    $asset = require $asset_file;

    wp_enqueue_script(
        'snel-newsletter-editor',
        SNEL_NEWSLETTER_PLUGIN_URL . 'build/editor.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );
    wp_enqueue_style(
        'snel-newsletter-editor',
        SNEL_NEWSLETTER_PLUGIN_URL . 'build/editor.css',
        array( 'wp-components' ),
        $asset['version']
    );

    // Mock tags for now — will come from DB later.
    wp_localize_script( 'snel-newsletter-editor', 'snelNewsletterEditor', array(
        'restUrl'       => rest_url( 'snel-newsletter/v1' ),
        'nonce'         => wp_create_nonce( 'wp_rest' ),
        'subscriberCount' => 4012,
        'tags'          => array( 'fitness', 'nutrition', 'paid', 'free-trial', 'vip' ),
    ) );
} );

/**
 * Change "Publish" to "Send Newsletter" on the newsletter CPT.
 * Only hooks in on the newsletter edit screen to avoid performance issues.
 */
add_action( 'admin_head', function () {
    global $post_type;
    if ( $post_type !== 'snel_newsletter' ) {
        return;
    }

    add_filter( 'gettext', function ( $translation, $text ) {
        static $replacements = array(
            'Publish'           => 'Send Newsletter',
            'Update'            => 'Update Campaign',
            'Schedule'          => 'Schedule Send',
            'Submit for Review' => 'Save Campaign',
        );

        return $replacements[ $text ] ?? $translation;
    }, 10, 2 );
} );
