<?php
/**
 * Seeds a demo "Welcome series" automation with mock subscribers, sends, opens and clicks,
 * so every node in the builder has something to show when you double-click it.
 *
 * Everything it creates is marked with the `demo` tag / _snel_demo meta, so `--clean` can
 * remove all of it and leave real data untouched.
 *
 * Usage:  php seed-welcome.php            (seed)
 *         php seed-welcome.php --clean    (remove everything it created)
 */

require_once '/Users/locnguyen/Local Sites/boilerplate-custom-theme/app/public/wp-load.php';

// The events table is new in 1.8.0 — admin_init hasn't run in CLI, so ensure it exists.
\Snel\Newsletter\Automations\Install::create_tables();

global $wpdb;

$subs_t   = $wpdb->prefix . 'snel_subscribers';
$stags_t  = $wpdb->prefix . 'snel_subscriber_tags';
$auto_t   = $wpdb->prefix . 'snel_automations';
$runs_t   = $wpdb->prefix . 'snel_automation_runs';
$events_t = $wpdb->prefix . 'snel_automation_events';
$queue_t  = $wpdb->prefix . 'snel_send_queue';
$track_t  = $wpdb->prefix . 'snel_tracking';

$DEMO_TAG = 'demo-welcome';

/* ------------------------------------------------------------------ clean */

$demo_campaign_ids = get_posts( array(
    'post_type'   => 'snel_newsletter',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields'      => 'ids',
    'meta_key'    => '_snel_demo',
    'meta_value'  => '1',
) );

$demo_sub_ids = $wpdb->get_col( $wpdb->prepare(
    "SELECT subscriber_id FROM $stags_t WHERE tag = %s",
    $DEMO_TAG
) );

if ( in_array( '--clean', $argv, true ) ) {
    $demo_autos = $wpdb->get_col( "SELECT id FROM $auto_t WHERE name LIKE 'Welcome series (demo)%'" );

    foreach ( $demo_autos as $aid ) {
        $wpdb->delete( $runs_t, array( 'automation_id' => $aid ) );
        $wpdb->delete( $events_t, array( 'automation_id' => $aid ) );
        $wpdb->delete( $auto_t, array( 'id' => $aid ) );
    }
    foreach ( $demo_campaign_ids as $cid ) {
        $wpdb->delete( $queue_t, array( 'campaign_id' => $cid ) );
        $wpdb->delete( $track_t, array( 'campaign_id' => $cid ) );
        wp_delete_post( $cid, true );
    }
    foreach ( $demo_sub_ids as $sid ) {
        $wpdb->delete( $stags_t, array( 'subscriber_id' => $sid ) );
        $wpdb->delete( $subs_t, array( 'id' => $sid ) );
    }

    echo "Removed demo: " . count( $demo_autos ) . " automation(s), "
        . count( $demo_campaign_ids ) . " campaign(s), "
        . count( $demo_sub_ids ) . " subscriber(s).\n";
    exit;
}

if ( $demo_sub_ids ) {
    echo "Demo data already exists. Run with --clean first to reseed.\n";
    exit;
}

/* ----------------------------------------------------------------- create */

// 1. Three campaigns for the series, plus a re-engagement nudge.
$campaigns = array();
foreach ( array(
    'welcome'  => 'Welcome to Snelstack 👋',
    'value'    => 'The 3 things that make a site fast',
    'offer'    => 'Want us to audit your site? (free)',
    'nudge'    => 'Did we lose you?',
) as $key => $subject ) {
    $cid = wp_insert_post( array(
        'post_type'    => 'snel_newsletter',
        'post_status'  => 'draft',
        'post_title'   => $subject,
        'post_content' => "<p>Demo content for: {$subject}</p>",
    ) );
    update_post_meta( $cid, '_snel_demo', '1' );
    $campaigns[ $key ] = $cid;
}

// 2. The automation itself.
$steps = array(
    array( 'type' => 'email', 'campaign_id' => $campaigns['welcome'] ),
    array( 'type' => 'wait',  'days' => 2, 'hours' => 0 ),
    array( 'type' => 'email', 'campaign_id' => $campaigns['value'] ),
    array( 'type' => 'wait',  'days' => 3, 'hours' => 0 ),
    array(
        'type'      => 'condition',
        'mode'      => 'opened',
        'threshold' => 50,
        'yes'       => array(
            array( 'type' => 'label', 'tag' => 'engaged' ),
            array( 'type' => 'email', 'campaign_id' => $campaigns['offer'] ),
        ),
        'no'        => array(
            array( 'type' => 'email', 'campaign_id' => $campaigns['nudge'] ),
        ),
    ),
);

$wpdb->insert( $auto_t, array(
    'name'         => 'Welcome series (demo)',
    'status'       => 'active',
    'trigger_type' => 'tag',
    'trigger_tag'  => 'new-lead',
    'steps'        => wp_json_encode( $steps ),
) );
$auto_id = (int) $wpdb->insert_id;

// 3. Mock subscribers.
$people = array(
    array( 'Sanne de Vries',   'sanne@example.com' ),
    array( 'Daan Jansen',      'daan@example.com' ),
    array( 'Fleur Bakker',     'fleur@example.com' ),
    array( 'Lars van Dijk',    'lars@example.com' ),
    array( 'Noor Visser',      'noor@example.com' ),
    array( 'Bram Smit',        'bram@example.com' ),
    array( 'Julia Meijer',     'julia@example.com' ),
    array( 'Thijs de Boer',    'thijs@example.com' ),
    array( 'Eva Mulder',       'eva@example.com' ),
    array( 'Sem Kuiper',       'sem@example.com' ),
    array( 'Lotte Peters',     'lotte@example.com' ),
    array( 'Finn Hendriks',    'finn@example.com' ),
);

$ago = function ( $days, $hours = 0 ) {
    return gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - ( $days * DAY_IN_SECONDS ) - ( $hours * HOUR_IN_SECONDS ) );
};

$sub_ids = array();
foreach ( $people as $i => $p ) {
    $wpdb->insert( $subs_t, array(
        'email'             => $p[1],
        'name'              => $p[0],
        'status'            => ( $i === 11 ) ? 'unsubscribed' : 'active',
        'unsubscribe_token' => wp_generate_password( 32, false ),
        'created_at'        => $ago( 10 - $i * 0.5 ),
    ) );
    $sid = (int) $wpdb->insert_id;
    $sub_ids[] = $sid;

    foreach ( array( $DEMO_TAG, 'new-lead' ) as $t ) {
        $wpdb->insert( $stags_t, array( 'subscriber_id' => $sid, 'tag' => $t ) );
    }
}

/* Helper writers. */
$event = function ( $sid, $path, $type, $detail, $when, $message = '', $level = 'info' ) use ( $wpdb, $events_t, $auto_id ) {
    $wpdb->insert( $events_t, array(
        'automation_id' => $auto_id,
        'subscriber_id' => $sid,
        'step_path'     => null === $path ? '' : wp_json_encode( $path ),
        'step_type'     => $type,
        'detail'        => (string) $detail,
        'level'         => $level,
        'message'       => $message,
        'created_at'    => $when,
    ) );
};
$sent = function ( $sid, $cid, $when ) use ( $wpdb, $queue_t ) {
    $wpdb->insert( $queue_t, array(
        'campaign_id'   => $cid,
        'subscriber_id' => $sid,
        'status'        => 'sent',
        'sent_at'       => $when,
        'created_at'    => $when,
    ) );
};
$track = function ( $sid, $cid, $type, $when ) use ( $wpdb, $track_t ) {
    $wpdb->insert( $track_t, array(
        'campaign_id'   => $cid,
        'subscriber_id' => $sid,
        'type'          => $type,
        'created_at'    => $when,
    ) );
};

/*
 * 4. Walk each mock subscriber a realistic distance into the flow.
 *    depth: how far they got. opens: which emails they opened.
 */
$profiles = array(
    // [ depth, opened_welcome, opened_value, clicked ]
    array( 'done_yes', true,  true,  true  ),
    array( 'done_yes', true,  true,  false ),
    array( 'done_yes', true,  true,  true  ),
    array( 'done_no',  true,  false, false ),
    array( 'done_no',  false, false, false ),
    array( 'done_no',  true,  false, false ),
    array( 'waiting2', true,  true,  false ), // parked at the 3-day wait
    array( 'waiting2', true,  false, false ),
    array( 'waiting2', false, false, false ),
    array( 'waiting1', true,  false, false ), // parked at the 2-day wait
    array( 'waiting1', false, false, false ),
    array( 'exited',   true,  false, false ), // unsubscribed mid-flow
);

foreach ( $sub_ids as $i => $sid ) {
    list( $depth, $open_welcome, $open_value, $clicked ) = $profiles[ $i ];

    $t0 = $ago( 9 - $i * 0.4 );

    $event( $sid, null, 'enroll', '', $t0, 'Entered the automation' );

    // Step [0] — welcome email.
    $event( $sid, array( 0 ), 'email', $campaigns['welcome'], $t0, 'Queued "Welcome to Snelstack"' );
    $sent( $sid, $campaigns['welcome'], $t0 );
    if ( $open_welcome ) {
        $track( $sid, $campaigns['welcome'], 'open', gmdate( 'Y-m-d H:i:s', strtotime( $t0 ) + 3600 ) );
    }

    if ( 'waiting1' === $depth ) {
        $wpdb->insert( $runs_t, array(
            'automation_id' => $auto_id, 'subscriber_id' => $sid,
            'position' => wp_json_encode( array( 2 ) ), 'status' => 'waiting',
            'next_run_at' => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + DAY_IN_SECONDS ),
            'created_at' => $t0,
        ) );
        $r1 = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + DAY_IN_SECONDS );
        $event( $sid, array( 1 ), 'wait', $r1, $t0, "Waiting 2 days (172800s) — resumes $r1" );
        continue;
    }

    // Step [1] — 2-day wait (already passed).
    $t1 = gmdate( 'Y-m-d H:i:s', strtotime( $t0 ) + 2 * DAY_IN_SECONDS );
    $event( $sid, array( 1 ), 'wait', $t1, $t0, "Waiting 2 days (172800s) — resumes $t1" );

    if ( 'exited' === $depth ) {
        $event( $sid, null, 'exit', 'unsubscribed', $t1, 'Left the automation — subscriber is unsubscribed, no further emails', 'warning' );
        $wpdb->insert( $runs_t, array(
            'automation_id' => $auto_id, 'subscriber_id' => $sid,
            'position' => wp_json_encode( array( 2 ) ), 'status' => 'exited',
            'next_run_at' => null, 'created_at' => $t0,
        ) );
        continue;
    }

    // Step [2] — value email.
    $event( $sid, array( 2 ), 'email', $campaigns['value'], $t1, 'Queued "The 3 things that make a site fast"' );
    $sent( $sid, $campaigns['value'], $t1 );
    if ( $open_value ) {
        $track( $sid, $campaigns['value'], 'open', gmdate( 'Y-m-d H:i:s', strtotime( $t1 ) + 7200 ) );
        if ( $clicked ) {
            $track( $sid, $campaigns['value'], 'click', gmdate( 'Y-m-d H:i:s', strtotime( $t1 ) + 7300 ) );
        }
    }

    if ( 'waiting2' === $depth ) {
        $wpdb->insert( $runs_t, array(
            'automation_id' => $auto_id, 'subscriber_id' => $sid,
            'position' => wp_json_encode( array( 4 ) ), 'status' => 'waiting',
            'next_run_at' => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + 2 * DAY_IN_SECONDS ),
            'created_at' => $t0,
        ) );
        $r2 = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + 2 * DAY_IN_SECONDS );
        $event( $sid, array( 3 ), 'wait', $r2, $t1, "Waiting 3 days (259200s) — resumes $r2" );
        continue;
    }

    // Step [3] — 3-day wait (passed).
    $t2 = gmdate( 'Y-m-d H:i:s', strtotime( $t1 ) + 3 * DAY_IN_SECONDS );
    $event( $sid, array( 3 ), 'wait', $t2, $t1, "Waiting 3 days (259200s) — resumes $t2" );

    // Step [4] — condition on "opened the previous email" (the value email).
    $took_yes = $open_value;
    $event( $sid, array( 4 ), 'condition', $took_yes ? 'yes' : 'no', $t2,
        $took_yes
            ? 'Took the YES branch — opened the previous email'
            : 'Took the NO branch — did not open the previous email' );

    if ( $took_yes ) {
        // [4].yes[0] label, [4].yes[1] offer email
        $event( $sid, array( 4, 'yes', 0 ), 'label', 'engaged', $t2, 'Tagged "engaged"' );
        $wpdb->insert( $stags_t, array( 'subscriber_id' => $sid, 'tag' => 'engaged' ) );

        $event( $sid, array( 4, 'yes', 1 ), 'email', $campaigns['offer'], $t2, 'Queued "Want us to audit your site? (free)"' );
        $sent( $sid, $campaigns['offer'], $t2 );
        if ( $clicked ) {
            $track( $sid, $campaigns['offer'], 'open', gmdate( 'Y-m-d H:i:s', strtotime( $t2 ) + 5400 ) );
            $track( $sid, $campaigns['offer'], 'click', gmdate( 'Y-m-d H:i:s', strtotime( $t2 ) + 5500 ) );
        }
    } else {
        // [4].no[0] nudge email
        $event( $sid, array( 4, 'no', 0 ), 'email', $campaigns['nudge'], $t2, 'Queued "Did we lose you?"' );
        $sent( $sid, $campaigns['nudge'], $t2 );
    }

    $event( $sid, null, 'complete', '', $t2, 'Reached the end of the automation' );
    $wpdb->insert( $runs_t, array(
        'automation_id' => $auto_id, 'subscriber_id' => $sid,
        'position' => wp_json_encode( array( 5 ) ), 'status' => 'completed',
        'next_run_at' => null, 'created_at' => $t0,
    ) );
}

echo "Seeded automation #{$auto_id} 'Welcome series (demo)'\n";
echo "  campaigns:   " . implode( ', ', $campaigns ) . "\n";
echo "  subscribers: " . count( $sub_ids ) . "\n";
echo "  runs:        " . $wpdb->get_var( "SELECT COUNT(*) FROM $runs_t WHERE automation_id = $auto_id" ) . "\n";
echo "  events:      " . $wpdb->get_var( "SELECT COUNT(*) FROM $events_t WHERE automation_id = $auto_id" ) . "\n";
echo "\nRemove it all later with:  php seed-welcome.php --clean\n";
