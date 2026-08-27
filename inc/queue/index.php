<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( Snel\Newsletter\Queue\Processor::CRON_HOOK, array( 'Snel\Newsletter\Queue\Processor', 'process_batch' ) );

// Watchdog: the drainer is a self-rescheduling *single* event; if that chain dies
// (fatal mid-batch, lost cron, restart) the queue orphans. A *recurring* event
// survives — WP re-adds it after every run — so it re-arms the drainer when needed.
const SNEL_QUEUE_WATCHDOG_HOOK = 'snel_newsletter_queue_watchdog';

// Custom 5-minute schedule (WP only ships hourly/twicedaily/daily).
add_filter( 'cron_schedules', function ( $schedules ) {
    if ( ! isset( $schedules['snel_five_minutes'] ) ) {
        $schedules['snel_five_minutes'] = array(
            'interval' => 300,
            'display'  => 'Every 5 Minutes (Snel Newsletter)',
        );
    }
    return $schedules;
} );

// Runs on every request (init), so it self-registers on any traffic —
// front-end or admin — not just wp-admin loads.
add_action( 'init', function () {
    if ( ! wp_next_scheduled( SNEL_QUEUE_WATCHDOG_HOOK ) ) {
        wp_schedule_event( time() + 60, 'snel_five_minutes', SNEL_QUEUE_WATCHDOG_HOOK );
    }
} );

// Re-arm the drainer if it's gone, or pull it forward if it was parked far out
// (capped lane paused until midnight) while sendable rows are waiting.
add_action( SNEL_QUEUE_WATCHDOG_HOOK, function () {
    $next = wp_next_scheduled( Snel\Newsletter\Queue\Processor::CRON_HOOK );

    // A run is already imminent — nothing to do.
    if ( $next && $next <= time() + 120 ) {
        return;
    }

    if ( Snel\Newsletter\Queue\Processor::has_pending_work() ) {
        Snel\Newsletter\Queue\Processor::ensure_soon();
        Snel\Newsletter\Logger\Logger::info( 'queue', 'Watchdog armed the drainer', array(
            'was_next' => $next ? gmdate( 'c', $next ) : null,
        ) );
    }
} );

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
