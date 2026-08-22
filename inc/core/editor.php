<?php

defined( 'ABSPATH' ) || exit;

// WP's default list page for the CPT is unused; send users to the React campaigns page.
add_action( 'admin_init', function () {
    global $pagenow;
    if ( $pagenow === 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'snel_newsletter' ) {
        wp_safe_redirect( admin_url( 'admin.php?page=snel-newsletter-campaigns' ) );
        exit;
    }
} );

// Own category so the newsletter blocks sit at the top of the inserter.
add_filter( 'block_categories_all', function ( $categories, $context ) {
    if ( isset( $context->post ) && $context->post->post_type === 'snel_newsletter' ) {
        array_unshift( $categories, array(
            'slug'  => 'snel-newsletter',
            'title' => __( 'Newsletter', 'snel-newsletter' ),
            'icon'  => 'email',
        ) );
    }
    return $categories;
}, 10, 2 );

// Only blocks that render in email; everything else is hidden from the inserter.
add_filter( 'allowed_block_types_all', function ( $allowed, $context ) {
    if ( ! isset( $context->post ) || $context->post->post_type !== 'snel_newsletter' ) {
        return $allowed;
    }

    return array(
        'core/paragraph',
        'core/heading',
        'core/image',
        'core/list',
        'core/list-item',
        'core/quote',
        'core/separator',
        'core/spacer',
        'core/buttons',
        'core/button',
        'snel/newsletter-button',
        'snel/newsletter-download',
    );
}, 10, 2 );

// Editing a campaign should keep the Snel Newsletter menu item highlighted.
add_filter( 'parent_file', function ( $parent_file ) {
    global $post_type;
    if ( $post_type === 'snel_newsletter' ) {
        return 'snel-newsletter';
    }
    return $parent_file;
} );

// Sidebar bundle plus the data it needs at load, so the editor opens without extra requests.
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

    $tags       = array();
    $count      = 0;
    $tag_counts = array();
    if ( class_exists( 'Snel\Newsletter\Subscribers\Model' ) ) {
        $tag_rows   = \Snel\Newsletter\Subscribers\Model::all_tags();
        $tags       = wp_list_pluck( $tag_rows, 'tag' );
        $counts     = \Snel\Newsletter\Subscribers\Model::counts();
        $count      = $counts['active'] ?? 0;
        $tag_counts = \Snel\Newsletter\Subscribers\Model::active_counts_by_tag();
    }

    $nl_settings = get_option( 'snel_newsletter_settings', array() );

    wp_localize_script( 'snel-newsletter-editor', 'snelNewsletterEditor', array(
        'restUrl'         => rest_url( 'snel-newsletter/v1' ),
        'nonce'           => wp_create_nonce( 'wp_rest' ),
        'subscriberCount' => $count,
        'tags'            => $tags,
        'tagCounts'       => $tag_counts,
        'senders'         => array(
            'broadcast'  => $nl_settings['from_email'] ?? '',
            'automation' => $nl_settings['auto_from_email'] ?: ( $nl_settings['from_email'] ?? '' ),
        ),
    ) );
} );

// Rename WP's publish buttons so the editor reads as "send".
// Hooked per screen: gettext runs on every string, so never register it globally.
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
