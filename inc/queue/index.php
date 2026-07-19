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
 * Recurring watchdog — the durability backstop for the send queue.
 *
 * The queue drains via a self-rescheduling *single* event (process_queue). If
 * any link in that chain is lost — a fatal mid-batch, a daily-cap event that
 * WP-Cron never fires on a quiet site, a server restart — the queue orphans and
 * nothing drains it. A *recurring* event can't die that way: WordPress re-adds
 * it to the schedule after every run. So we keep one alive and let it re-arm the
 * drainer whenever work is due but no drainer is scheduled.
 */
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

// Keep the recurring watchdog scheduled. Runs on every request (init), so it
// self-registers on any traffic — front-end or admin — not just wp-admin loads.
add_action( 'init', function () {
    if ( ! wp_next_scheduled( SNEL_QUEUE_WATCHDOG_HOOK ) ) {
        wp_schedule_event( time() + 60, 'snel_five_minutes', SNEL_QUEUE_WATCHDOG_HOOK );
    }
} );

// The watchdog itself: if the drainer is gone but rows are due, re-arm it.
add_action( SNEL_QUEUE_WATCHDOG_HOOK, function () {
    if ( wp_next_scheduled( Snel\Newsletter\Queue\Processor::CRON_HOOK ) ) {
        return; // Drainer already alive — nothing to do.
    }

    global $wpdb;
    $has_work = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}snel_send_queue
         WHERE status IN ('pending', 'retrying')
            OR (status = 'delayed' AND delayed_until <= NOW())"
    );

    if ( $has_work ) {
        wp_schedule_single_event( time() + 5, Snel\Newsletter\Queue\Processor::CRON_HOOK );
        Snel\Newsletter\Logger\Logger::info( 'queue', 'Watchdog re-armed the drainer', array( 'rows' => $has_work ) );
    }
} );

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
