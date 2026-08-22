<?php

defined( 'ABSPATH' ) || exit;

// SOT:CAMPAIGN-CPT
// Not public (no front-end URL), menu is built in admin.php, REST on for Gutenberg.
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
        'show_in_menu' => false,
        'show_in_rest' => true,
        'supports'     => array( 'title', 'editor', 'custom-fields' ),
        'has_archive'  => false,
        'rewrite'      => false,
    ) );

    // Exposed to REST so the sidebar can save the chosen tags.
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

    // Custom-list audience: filter rules, same engine as the subscribers page.
    // When set, the campaign sends to everyone matching instead of by tag.
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

    // Workflow emails stay drafts: the broadcast pipeline fires on publish,
    // the automation engine sends the draft content directly.
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
