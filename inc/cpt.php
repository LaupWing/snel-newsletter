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
        'supports'     => array( 'title', 'editor', 'custom-fields' ),
        'has_archive'  => false,
        'rewrite'      => false,
    ) );

    // Recipient tags chosen in the editor sidebar. Exposed to REST so the
    // block editor can persist the selection to post meta.
    register_post_meta( 'snel_newsletter', '_snel_nl_tags', array(
        'type'          => 'array',
        'single'        => true,
        'default'       => array(),
        'show_in_rest'  => array(
            'schema' => array(
                'type'  => 'array',
                'items' => array( 'type' => 'string' ),
            ),
        ),
        'auth_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
    ) );

    // Which audience mode the editor picked: '', 'all', 'tags', or 'custom'.
    // Empty means nothing chosen yet — the editor blocks publishing until set.
    register_post_meta( 'snel_newsletter', '_snel_nl_audience', array(
        'type'          => 'string',
        'single'        => true,
        'default'       => '',
        'show_in_rest'  => true,
        'auth_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
    ) );

    // Custom-list audience: a stack of { field, operator, value } filter
    // conditions (same engine as the subscribers page). When set, the campaign
    // sends to everyone matching instead of by tag.
    register_post_meta( 'snel_newsletter', '_snel_nl_audience_filters', array(
        'type'          => 'array',
        'single'        => true,
        'default'       => array(),
        'show_in_rest'  => array(
            'schema' => array(
                'type'  => 'array',
                'items' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'field'    => array( 'type' => 'string' ),
                        'operator' => array( 'type' => 'string' ),
                        'value'    => array( 'type' => 'string' ),
                    ),
                ),
            ),
        ),
        'auth_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
    ) );

    // Marks a campaign as a workflow (automation) email rather than a one-time
    // broadcast. Workflow emails stay drafts — the broadcast pipeline only fires
    // on publish, while the automation engine sends the draft content directly.
    register_post_meta( 'snel_newsletter', '_snel_nl_is_workflow', array(
        'type'          => 'string',
        'single'        => true,
        'default'       => '',
        'show_in_rest'  => true,
        'auth_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
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
 * Register newsletter block category.
 */
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

/**
 * Restrict available blocks in the newsletter editor.
 */
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

    // Fetch real tags and subscriber count from DB.
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
            // Automation falls back to the broadcast sender when not set.
            'automation' => $nl_settings['auto_from_email'] ?: ( $nl_settings['from_email'] ?? '' ),
        ),
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
