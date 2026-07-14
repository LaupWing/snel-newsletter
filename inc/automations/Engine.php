<?php
/**
 * Automation engine — enrolls subscribers and walks them through steps.
 *
 * A run's position is a JSON path into the steps array:
 *   [2]           → root step index 2
 *   [2,"yes",0]   → first step inside the "yes" branch of the condition at root index 2
 *
 * Email steps reuse the send queue, so tracking, unsubscribe, and warmup
 * caps all apply to automation emails exactly like campaign sends.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Automations;

defined( 'ABSPATH' ) || exit;

class Engine {

    const CRON_HOOK     = 'snel_newsletter_automations_tick';
    const CRON_INTERVAL = 60;
    const BATCH_SIZE    = 100;
    const MAX_STEPS     = 50; // Safety cap per run per tick.

    /**
     * Enroll subscribers into an automation. Only active subscribers enter;
     * a subscriber can only be enrolled once per automation.
     *
     * @return int Number of subscribers newly enrolled.
     */
    public static function enroll( $automation_id, $subscriber_ids ) {
        global $wpdb;

        $subscriber_ids = array_filter( array_map( 'intval', (array) $subscriber_ids ) );
        if ( ! $subscriber_ids || ! Model::get( $automation_id ) ) {
            return 0;
        }

        $subs_table = $wpdb->prefix . 'snel_subscribers';
        $ids_in     = implode( ',', $subscriber_ids );
        $active_ids = $wpdb->get_col( "SELECT id FROM $subs_table WHERE id IN ($ids_in) AND status = 'active'" );

        if ( ! $active_ids ) {
            return 0;
        }

        $runs   = Model::runs_table();
        $now    = current_time( 'mysql' );
        $values = array();
        $place  = array();
        foreach ( $active_ids as $sid ) {
            $values[] = $automation_id;
            $values[] = (int) $sid;
            $values[] = $now;
            $place[]  = "(%d, %d, '[0]', 'active', %s)";
        }

        $place_sql = implode( ', ', $place );
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO $runs (automation_id, subscriber_id, position, status, next_run_at) VALUES $place_sql",
            $values
        ) );

        $enrolled = (int) $wpdb->rows_affected;

        if ( $enrolled ) {
            self::ensure_scheduled();
            \Snel\Newsletter\Logger\Logger::info( 'automations', 'Subscribers enrolled', array(
                'automation_id' => $automation_id,
                'enrolled'      => $enrolled,
            ) );
        }

        return $enrolled;
    }

    /**
     * Tag-added trigger — enroll into any active automation watching one of these tags.
     */
    public static function on_tags_added( $subscriber_id, $tags ) {
        global $wpdb;

        $tags = array_filter( array_map( 'strval', (array) $tags ) );
        if ( ! $tags ) {
            return;
        }

        $table        = Model::table();
        $placeholders = implode( ',', array_fill( 0, count( $tags ), '%s' ) );
        $ids          = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM $table WHERE status = 'active' AND trigger_type = 'tag' AND trigger_tag IN ($placeholders)",
            $tags
        ) );

        foreach ( $ids as $automation_id ) {
            self::enroll( (int) $automation_id, array( $subscriber_id ) );
        }
    }

    /**
     * Process due runs. Called by WP Cron.
     */
    public static function tick() {
        global $wpdb;

        $runs_table = Model::runs_table();
        $auto_table = Model::table();

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.* FROM $runs_table r
             INNER JOIN $auto_table a ON a.id = r.automation_id AND a.status = 'active'
             WHERE r.status IN ('active', 'waiting') AND r.next_run_at <= %s
             ORDER BY r.next_run_at ASC
             LIMIT %d",
            current_time( 'mysql' ),
            self::BATCH_SIZE
        ) );

        $automations = array();
        foreach ( $rows as $run ) {
            if ( ! isset( $automations[ $run->automation_id ] ) ) {
                $automations[ $run->automation_id ] = Model::get( (int) $run->automation_id );
            }
            self::process_run( $run, $automations[ $run->automation_id ] );
        }

        // Keep ticking while there is (or will be) work.
        $pending = $wpdb->get_var(
            "SELECT COUNT(*) FROM $runs_table r
             INNER JOIN $auto_table a ON a.id = r.automation_id AND a.status = 'active'
             WHERE r.status IN ('active', 'waiting')"
        );
        if ( $pending ) {
            self::ensure_scheduled( self::CRON_INTERVAL );
        }
    }

    public static function ensure_scheduled( $delay = 5 ) {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + $delay, self::CRON_HOOK );
        }
    }

    /**
     * Execute steps for one run until it waits, exits, or completes.
     */
    private static function process_run( $run, $automation ) {
        global $wpdb;

        if ( ! $automation ) {
            self::update_run( $run->id, array( 'status' => 'exited' ) );
            return;
        }

        // Unsubscribed/bounced mid-flow → stop, never email them again.
        $sub_status = $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}snel_subscribers WHERE id = %d",
            $run->subscriber_id
        ) );
        if ( $sub_status !== 'active' ) {
            self::update_run( $run->id, array( 'status' => 'exited' ) );
            return;
        }

        $steps = $automation['steps'];
        $path  = json_decode( $run->position, true );
        if ( ! is_array( $path ) || ! $path ) {
            $path = array( 0 );
        }

        for ( $guard = 0; $guard < self::MAX_STEPS; $guard++ ) {
            $step = self::step_at( $steps, $path );

            if ( null === $step ) {
                if ( count( $path ) === 3 ) {
                    // Branch finished → continue after the condition.
                    $path = array( $path[0] + 1 );
                    continue;
                }
                self::update_run( $run->id, array( 'status' => 'completed', 'position' => wp_json_encode( $path ), 'next_run_at' => null ) );
                return;
            }

            switch ( $step['type'] ?? '' ) {
                case 'email':
                    self::send_step_email( $step, $run );
                    $path = self::advance( $path );
                    break;

                case 'label':
                    if ( ! empty( $step['tag'] ) ) {
                        \Snel\Newsletter\Subscribers\Model::add_tags( (int) $run->subscriber_id, array( $step['tag'] ) );
                    }
                    $path = self::advance( $path );
                    break;

                case 'wait':
                    $days    = max( 0, (int) ( $step['days'] ?? 0 ) );
                    $hours   = max( 0, (int) ( $step['hours'] ?? 0 ) );
                    $seconds = max( 60, $days * DAY_IN_SECONDS + $hours * HOUR_IN_SECONDS );
                    self::update_run( $run->id, array(
                        'status'      => 'waiting',
                        'position'    => wp_json_encode( self::advance( $path ) ),
                        'next_run_at' => date( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + $seconds ),
                    ) );
                    return;

                case 'condition':
                    if ( count( $path ) === 3 ) {
                        // Nested conditions aren't supported — skip.
                        $path = self::advance( $path );
                        break;
                    }
                    if ( ( $step['mode'] ?? 'opened' ) === 'open_rate' ) {
                        $result = self::open_rate_above( (int) $run->subscriber_id, (float) ( $step['threshold'] ?? 0 ) );
                    } else {
                        $result = self::opened_previous_email( $steps, $path[0], (int) $run->subscriber_id );
                    }
                    $path = array( $path[0], $result ? 'yes' : 'no', 0 );
                    break;

                default:
                    $path = self::advance( $path );
            }
        }

        // Safety cap hit — persist position and pick up next tick.
        self::update_run( $run->id, array( 'position' => wp_json_encode( $path ) ) );
    }

    private static function send_step_email( $step, $run ) {
        global $wpdb;

        $campaign_id = (int) ( $step['campaign_id'] ?? 0 );
        if ( ! $campaign_id || ! get_post( $campaign_id ) ) {
            \Snel\Newsletter\Logger\Logger::warning( 'automations', 'Email step skipped — campaign missing', array(
                'campaign_id'   => $campaign_id,
                'automation_id' => $run->automation_id,
            ) );
            return;
        }

        $queue = $wpdb->prefix . 'snel_send_queue';
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO $queue (campaign_id, subscriber_id) VALUES (%d, %d)",
            $campaign_id,
            $run->subscriber_id
        ) );

        if ( ! wp_next_scheduled( \Snel\Newsletter\Queue\Processor::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + 5, \Snel\Newsletter\Queue\Processor::CRON_HOOK );
        }
    }

    /**
     * Did the subscriber open the nearest email step above this condition?
     */
    private static function opened_previous_email( $steps, $condition_index, $subscriber_id ) {
        global $wpdb;

        $campaign_id = 0;
        for ( $i = $condition_index - 1; $i >= 0; $i-- ) {
            if ( ( $steps[ $i ]['type'] ?? '' ) === 'email' && ! empty( $steps[ $i ]['campaign_id'] ) ) {
                $campaign_id = (int) $steps[ $i ]['campaign_id'];
                break;
            }
        }

        if ( ! $campaign_id ) {
            return false;
        }

        $tracking = $wpdb->prefix . 'snel_tracking';
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $tracking WHERE campaign_id = %d AND subscriber_id = %d AND type = 'open' LIMIT 1",
            $campaign_id,
            $subscriber_id
        ) );
    }

    /**
     * Is the subscriber's lifetime open rate above the threshold (percent)?
     * Same formula as dynamic tag rules: opens / distinct campaigns received.
     */
    private static function open_rate_above( $subscriber_id, $threshold ) {
        global $wpdb;

        $tracking = $wpdb->prefix . 'snel_tracking';
        $rate     = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(CASE WHEN type = 'open' THEN 1 ELSE 0 END) / COUNT(DISTINCT campaign_id) * 100
             FROM $tracking WHERE subscriber_id = %d",
            $subscriber_id
        ) );

        return null !== $rate && (float) $rate > $threshold;
    }

    private static function step_at( $steps, $path ) {
        if ( count( $path ) === 1 ) {
            return $steps[ $path[0] ] ?? null;
        }
        if ( count( $path ) === 3 ) {
            $branch = $steps[ $path[0] ][ $path[1] ] ?? null;
            return is_array( $branch ) ? ( $branch[ $path[2] ] ?? null ) : null;
        }
        return null;
    }

    private static function advance( $path ) {
        $path[ count( $path ) - 1 ]++;
        return $path;
    }

    private static function update_run( $run_id, $fields ) {
        global $wpdb;
        $fields['updated_at'] = current_time( 'mysql' );
        $wpdb->update( Model::runs_table(), $fields, array( 'id' => $run_id ) );
    }
}
