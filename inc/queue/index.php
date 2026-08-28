<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Queue on shutdown, not mid-request: Gutenberg's REST save persists meta AFTER
// the publish transition, so queueing there reads stale meta and a tag-targeted
// campaign would broadcast to the full list. By shutdown all meta is saved.
function snel_newsletter_queue_on_shutdown( int $post_id ): void {
    static $queued = array();
    if ( isset( $queued[ $post_id ] ) ) return;
    $queued[ $post_id ] = true;

    add_action( 'shutdown', function () use ( $post_id ) {
        $tags = get_post_meta( $post_id, '_snel_nl_tags', true ) ?: array();
        Snel\Newsletter\Queue\Processor::queue_campaign( $post_id, $tags );
    } );
}

// Queue on campaign publish — immediate and scheduled (future → publish) alike.
add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
    if ( $post->post_type !== 'snel_newsletter' ) return;
    if ( $new_status !== 'publish' || $old_status === 'publish' ) return;

    snel_newsletter_queue_on_shutdown( $post->ID );
}, 10, 3 );

// Explicit hook for when WordPress auto-publishes a scheduled campaign.
add_action( 'future_to_publish', function ( $post ) {
    if ( $post->post_type !== 'snel_newsletter' ) return;

    $send_status = get_post_meta( $post->ID, '_snel_nl_send_status', true );
    if ( $send_status === 'sending' || $send_status === 'sent' ) return;

    snel_newsletter_queue_on_shutdown( $post->ID );
} );
