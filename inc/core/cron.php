<?php
// Safety net next to the queue watchdog: if the drainer event is gone but rows wait, re-arm it on admin load.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_init', function () {
    if ( wp_next_scheduled( Snel\Newsletter\Queue\Processor::CRON_HOOK ) ) {
        return;
    }

    if ( Snel\Newsletter\Queue\Processor::has_pending_work() ) {
        wp_schedule_single_event( time() + 5, Snel\Newsletter\Queue\Processor::CRON_HOOK );
        Snel\Newsletter\Logger\Logger::info( 'queue', 'Cron hook was missing — rescheduled by self-heal' );
    }
} );
