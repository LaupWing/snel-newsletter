<?php
/**
 * WP-CLI commands for Snel Newsletter.
 *
 * Usage:
 *   wp snel-newsletter test-send          — full queue test with 3 fake subscribers
 *   wp snel-newsletter test-send --fail   — same but simulates SES failures
 *   wp snel-newsletter clear-test         — removes all test data created by test-send
 *
 * @package SnelNewsletter
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

WP_CLI::add_command( 'snel-newsletter', 'Snel_Newsletter_CLI' );

class Snel_Newsletter_CLI {

    /**
     * Run a full queue test with fake subscribers and a log adapter.
     *
     * ## OPTIONS
     *
     * [--fail]
     * : Simulate adapter send failures to test retry logic.
     *
     * [--count=<number>]
     * : Number of fake subscribers to create. Default: 3.
     *
     * ## EXAMPLES
     *
     *   wp snel-newsletter test-send
     *   wp snel-newsletter test-send --fail
     *   wp snel-newsletter test-send --count=10
     *
     * @when after_wp_load
     */
    public function test_send( $args, $assoc_args ) {
        // This command inserts fake subscribers and runs the real pipeline; never on production.
        if ( function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'production' ) {
            WP_CLI::error( 'test-send refuses to run on a production environment.' );
        }

        global $wpdb;

        $fail  = isset( $assoc_args['fail'] );
        $count = isset( $assoc_args['count'] ) ? (int) $assoc_args['count'] : 3;

        WP_CLI::log( '' );
        WP_CLI::log( '=== Snel Newsletter — Queue Test ===' );
        WP_CLI::log( '' );

        // ── 1. Override active adapter with LogAdapter ────────────────────────
        require_once SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/adapters/LogAdapter.php';
        \Snel\Newsletter\Adapters\LogAdapter::reset();
        \Snel\Newsletter\Adapters\LogAdapter::$fail = $fail;
        \Snel\Newsletter\Adapters\Manager::register( 'log', \Snel\Newsletter\Adapters\LogAdapter::class );

        // Force settings to use log adapter + required from_email.
        $original_settings = get_option( 'snel_newsletter_settings', array() );
        update_option( 'snel_newsletter_settings', array_merge( $original_settings, array(
            'adapter'    => 'log',
            'from_email' => 'test@example.com',
            'from_name'  => 'Test Sender',
            'reply_to'   => 'test@example.com',
        ) ) );

        if ( $fail ) {
            WP_CLI::warning( 'Failure mode ON — all sends will fail (testing retry logic).' );
        }

        // ── 2. Insert fake subscribers ────────────────────────────────────────
        WP_CLI::log( "Creating $count fake subscribers..." );

        $sub_table = $wpdb->prefix . 'snel_subscribers';
        $sub_ids   = array();

        for ( $i = 1; $i <= $count; $i++ ) {
            $email = "test-subscriber-$i@snel-test.local";
            $token = wp_generate_password( 32, false );

            // Remove if exists from a previous run.
            $wpdb->delete( $sub_table, array( 'email' => $email ) );

            $wpdb->insert( $sub_table, array(
                'email'             => $email,
                'name'              => "Test User $i",
                'status'            => 'active',
                'unsubscribe_token' => $token,
            ) );

            $sub_ids[] = $wpdb->insert_id;
            WP_CLI::log( "  + $email (id: {$wpdb->insert_id})" );
        }

        // ── 3. Create a test campaign post ────────────────────────────────────
        WP_CLI::log( '' );
        WP_CLI::log( 'Creating test campaign...' );

        $campaign_id = wp_insert_post( array(
            'post_type'    => 'snel_newsletter',
            'post_title'   => 'Test Campaign — ' . date( 'Y-m-d H:i:s' ),
            'post_content' => '<p>Hello %%name%%,</p><p>This is a test email from the Snel Newsletter queue system.</p><p>If you see this, the queue is working correctly.</p>',
            'post_status'  => 'draft',
        ) );

        WP_CLI::log( "  Campaign ID: $campaign_id" );

        // ── 4. Queue the campaign ─────────────────────────────────────────────
        WP_CLI::log( '' );
        WP_CLI::log( 'Queuing campaign for all test subscribers...' );

        // Insert rows for the fake subscribers directly; queue_campaign() would
        // resolve the real audience and mass-mail the whole list.
        foreach ( $sub_ids as $sid ) {
            $wpdb->insert( $wpdb->prefix . 'snel_send_queue', array(
                'campaign_id'   => $campaign_id,
                'subscriber_id' => $sid,
            ), array( '%d', '%d' ) );
        }
        $total = count( $sub_ids );
        WP_CLI::log( "  Queued: $total emails" );

        // Verify queue state.
        $queue_table = $wpdb->prefix . 'snel_send_queue';
        $pending = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $queue_table WHERE campaign_id = %d AND status = 'pending'",
            $campaign_id
        ) );
        WP_CLI::log( "  Pending in queue: $pending" );

        // ── 5. Process batch ──────────────────────────────────────────────────
        WP_CLI::log( '' );
        WP_CLI::log( 'Running process_batch()...' );

        \Snel\Newsletter\Queue\Processor::process_batch();

        // ── 6. Results ────────────────────────────────────────────────────────
        WP_CLI::log( '' );
        WP_CLI::log( '=== Results ===' );
        WP_CLI::log( '' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT q.status, q.message_id, q.error_message, q.retries, s.email
             FROM $queue_table q
             INNER JOIN $sub_table s ON s.id = q.subscriber_id
             WHERE q.campaign_id = %d",
            $campaign_id
        ) );

        $table_data = array();
        foreach ( $rows as $row ) {
            $table_data[] = array(
                'email'        => $row->email,
                'status'       => $row->status,
                'message_id'   => $row->message_id ?: '—',
                'retries'      => $row->retries,
                'error'        => $row->error_message ?: '—',
            );
        }

        WP_CLI\Utils\format_items( 'table', $table_data, array( 'email', 'status', 'message_id', 'retries', 'error' ) );

        $log = \Snel\Newsletter\Adapters\LogAdapter::$log;
        WP_CLI::log( '' );
        WP_CLI::log( 'Adapter log (' . count( $log ) . ' sends recorded):' );
        foreach ( $log as $entry ) {
            WP_CLI::log( "  → {$entry['to']} | {$entry['subject']} | {$entry['time']}" );
        }

        $sent_count = get_post_meta( $campaign_id, '_snel_nl_sent_count', true );
        $status     = get_post_meta( $campaign_id, '_snel_nl_send_status', true );
        WP_CLI::log( '' );
        WP_CLI::log( "Campaign status : $status" );
        WP_CLI::log( "Sent count meta : $sent_count / $total" );

        if ( ! $fail && (int) $sent_count === $total ) {
            WP_CLI::success( 'All emails sent successfully. Queue is working.' );
        } elseif ( $fail ) {
            WP_CLI::warning( 'Failure mode was on — check retries column above.' );
        } else {
            WP_CLI::error( "Only $sent_count / $total emails sent. Check errors above." );
        }

        // ── 7. Restore original settings ──────────────────────────────────────
        update_option( 'snel_newsletter_settings', $original_settings );

        WP_CLI::log( '' );
        WP_CLI::log( 'Run `wp snel-newsletter clear-test` to clean up test data.' );
    }

    /**
     * Remove all test data created by test-send.
     *
     * @when after_wp_load
     */
    public function clear_test( $args, $assoc_args ) {
        global $wpdb;

        $sub_table   = $wpdb->prefix . 'snel_subscribers';
        $queue_table = $wpdb->prefix . 'snel_send_queue';

        // Remove test subscribers.
        $deleted_subs = $wpdb->query(
            "DELETE FROM $sub_table WHERE email LIKE 'test-subscriber-%@snel-test.local'"
        );

        // Remove test campaigns.
        $campaigns = get_posts( array(
            'post_type'   => 'snel_newsletter',
            'post_status' => 'any',
            'numberposts' => -1,
            's'           => 'Test Campaign —',
            'fields'      => 'ids',
        ) );

        foreach ( $campaigns as $id ) {
            $wpdb->delete( $queue_table, array( 'campaign_id' => $id ) );
            wp_delete_post( $id, true );
        }

        WP_CLI::success( "Cleaned up: $deleted_subs test subscribers, " . count( $campaigns ) . ' test campaigns.' );
    }
}
