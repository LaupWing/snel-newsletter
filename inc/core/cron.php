<?php
// The cron map: every background task of the plugin, in one place.
//
//   queue drainer      every minute, reschedules itself   Queue\Processor::process_batch
//   queue watchdog     every 5 minutes                    Queue\Processor::watchdog
//   queue self-heal    on every admin page                below
//   automations tick   every minute, self-arming          Automations\Engine::tick
//   automations heal   on every admin page                below
//   sources sync       hourly + on save_post              CptSources\AutoSync (wires itself)

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Snel\Newsletter\Queue\Processor;
use Snel\Newsletter\Automations\Engine;
use Snel\Newsletter\Automations\Model as AutomationsModel;

add_action( Processor::CRON_HOOK, array( Processor::class, 'process_batch' ) );
add_action( Engine::CRON_HOOK, array( Engine::class, 'tick' ) );

const SNEL_QUEUE_WATCHDOG_HOOK = 'snel_newsletter_queue_watchdog';

add_filter( 'cron_schedules', function ( $schedules ) {
    if ( ! isset( $schedules['snel_five_minutes'] ) ) {
        $schedules['snel_five_minutes'] = array(
            'interval' => 300,
            'display'  => 'Every 5 Minutes (Snel Newsletter)',
        );
    }
    return $schedules;
} );

// On init (any traffic), so the watchdog survives even if wp-cron loses it.
add_action( 'init', function () {
    if ( ! wp_next_scheduled( SNEL_QUEUE_WATCHDOG_HOOK ) ) {
        wp_schedule_event( time() + 60, 'snel_five_minutes', SNEL_QUEUE_WATCHDOG_HOOK );
    }
} );
add_action( SNEL_QUEUE_WATCHDOG_HOOK, array( Processor::class, 'watchdog' ) );

// Self-heals: last line of defense when even the watchdog is gone (e.g. after an update).
add_action( 'admin_init', function () {
    if ( ! wp_next_scheduled( Processor::CRON_HOOK ) && Processor::has_pending_work() ) {
        wp_schedule_single_event( time() + 5, Processor::CRON_HOOK );
        Snel\Newsletter\Logger\Logger::info( 'queue', 'Cron hook was missing — rescheduled by self-heal' );
    }
} );

add_action( 'admin_init', function () {
    if ( ! wp_next_scheduled( Engine::CRON_HOOK ) && AutomationsModel::has_active_runs() ) {
        Engine::ensure_scheduled();
    }
} );
