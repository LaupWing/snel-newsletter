<?php
/**
 * Queue feature — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/Install.php';
require_once __DIR__ . '/Processor.php';

// Register the cron hook.
add_action( Snel\Newsletter\Queue\Processor::CRON_HOOK, array( 'Snel\Newsletter\Queue\Processor', 'process_batch' ) );

/**
 * Hook into campaign publish to queue emails.
 * Handles both immediate publish and scheduled (future → publish) campaigns.
 */
add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
    if ( $post->post_type !== 'snel_newsletter' ) return;
    if ( $new_status !== 'publish' || $old_status === 'publish' ) return;

    $tags = get_post_meta( $post->ID, '_snel_nl_tags', true ) ?: array();
    Snel\Newsletter\Queue\Processor::queue_campaign( $post->ID, $tags );
}, 10, 3 );

// Explicit hook for when WordPress auto-publishes a scheduled campaign.
add_action( 'future_to_publish', function ( $post ) {
    if ( $post->post_type !== 'snel_newsletter' ) return;

    $send_status = get_post_meta( $post->ID, '_snel_nl_send_status', true );
    if ( $send_status === 'sending' || $send_status === 'sent' ) return;

    $tags = get_post_meta( $post->ID, '_snel_nl_tags', true ) ?: array();
    Snel\Newsletter\Queue\Processor::queue_campaign( $post->ID, $tags );
} );
