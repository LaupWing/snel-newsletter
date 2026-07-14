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

        // Who was already in before we insert? INSERT IGNORE won't tell us afterwards, and
        // created_at can't either (MySQL writes it in UTC, current_time() is site-local).
        $already = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT subscriber_id FROM $runs WHERE automation_id = %d AND subscriber_id IN ($ids_in)",
            $automation_id
        ) ) );

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

        // One log line per subscriber: entered, or already in and skipped.
        foreach ( $active_ids as $sid ) {
            $sid  = (int) $sid;
            $stub = (object) array(
                'automation_id' => $automation_id,
                'subscriber_id' => $sid,
            );

            if ( in_array( $sid, $already, true ) ) {
                self::log_event(
                    $stub, null, 'enroll', '',
                    'Already enrolled — skipped, a subscriber only enters once',
                    'warning'
                );
            } else {
                self::log_event( $stub, null, 'enroll', '', 'Entered the automation' );
            }
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
     * Everyone who carries the trigger tag but never entered the automation.
     *
     * Enrollment only happens the moment a tag is applied, so anyone tagged
     * while the automation was paused is stranded. This is who they are.
     *
     * @return int[] Subscriber IDs.
     */
    public static function pending_by_tag( $automation_id ) {
        global $wpdb;

        $automation = Model::get( $automation_id );

        if ( ! $automation || 'tag' !== $automation['trigger_type'] || ! $automation['trigger_tag'] ) {
            return array();
        }

        $subs = $wpdb->prefix . 'snel_subscribers';
        $tags = $wpdb->prefix . 'snel_subscriber_tags';
        $runs = Model::runs_table();

        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT s.id
             FROM $subs s
             INNER JOIN $tags t ON t.subscriber_id = s.id AND t.tag = %s
             LEFT JOIN $runs r ON r.subscriber_id = s.id AND r.automation_id = %d
             WHERE s.status = 'active' AND r.id IS NULL",
            $automation['trigger_tag'],
            $automation_id
        ) ) );
    }

    /**
     * Backfill: enroll everyone already carrying the trigger tag.
     *
     * @return int Number newly enrolled.
     */
    public static function enroll_by_tag( $automation_id ) {
        $ids = self::pending_by_tag( $automation_id );

        return $ids ? self::enroll( $automation_id, $ids ) : 0;
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
            self::log_event( $run, null, 'exit', '', 'Automation was deleted — run stopped', 'warning' );
            self::update_run( $run->id, array( 'status' => 'exited' ) );
            return;
        }

        // Unsubscribed/bounced mid-flow → stop, never email them again.
        $sub_status = $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}snel_subscribers WHERE id = %d",
            $run->subscriber_id
        ) );
        if ( $sub_status !== 'active' ) {
            self::log_event(
                $run, null, 'exit', (string) $sub_status,
                sprintf( 'Left the automation — subscriber is %s, no further emails', $sub_status ),
                'warning'
            );
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
                self::log_event( $run, null, 'complete', '', 'Reached the end of the automation' );
                self::update_run( $run->id, array( 'status' => 'completed', 'position' => wp_json_encode( $path ), 'next_run_at' => null ) );
                return;
            }

            switch ( $step['type'] ?? '' ) {
                case 'email':
                    // Logs its own outcome — queued, duplicate, or missing campaign.
                    self::send_step_email( $step, $run, $path );
                    $path = self::advance( $path );
                    break;

                case 'label':
                    $tag = (string) ( $step['tag'] ?? '' );
                    if ( $tag ) {
                        \Snel\Newsletter\Subscribers\Model::add_tags( (int) $run->subscriber_id, array( $tag ) );
                        self::log_event( $run, $path, 'label', $tag, sprintf( 'Tagged "%s"', $tag ) );
                    } else {
                        self::log_event( $run, $path, 'label', '', 'Label step has no tag set — skipped', 'warning' );
                    }
                    $path = self::advance( $path );
                    break;

                case 'wait':
                    $days    = max( 0, (int) ( $step['days'] ?? 0 ) );
                    $hours   = max( 0, (int) ( $step['hours'] ?? 0 ) );
                    $seconds = max( 60, $days * DAY_IN_SECONDS + $hours * HOUR_IN_SECONDS );
                    $resume  = date( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + $seconds );

                    self::log_event(
                        $run, $path, 'wait', $resume,
                        sprintf(
                            'Waiting %s (%ds) — resumes %s',
                            self::human_wait( $days, $hours ),
                            $seconds,
                            $resume
                        )
                    );

                    self::update_run( $run->id, array(
                        'status'      => 'waiting',
                        'position'    => wp_json_encode( self::advance( $path ) ),
                        'next_run_at' => $resume,
                    ) );
                    return;

                case 'condition':
                    if ( count( $path ) === 3 ) {
                        // Nested conditions aren't supported — skip.
                        self::log_event( $run, $path, 'condition', '', 'Nested condition is not supported — skipped', 'warning' );
                        $path = self::advance( $path );
                        break;
                    }

                    if ( ( $step['mode'] ?? 'opened' ) === 'open_rate' ) {
                        $threshold = (float) ( $step['threshold'] ?? 0 );
                        $result    = self::open_rate_above( (int) $run->subscriber_id, $threshold );
                        $why       = sprintf( 'lifetime open rate %s %s%%', $result ? 'above' : 'not above', $threshold );
                    } else {
                        $result = self::opened_previous_email( $steps, $path[0], (int) $run->subscriber_id );
                        $why    = $result ? 'opened the previous email' : 'did not open the previous email';
                    }

                    self::log_event(
                        $run, $path, 'condition', $result ? 'yes' : 'no',
                        sprintf( 'Took the %s branch — %s', $result ? 'YES' : 'NO', $why )
                    );

                    $path = array( $path[0], $result ? 'yes' : 'no', 0 );
                    break;

                default:
                    $path = self::advance( $path );
            }
        }

        // Safety cap hit — persist position and pick up next tick.
        self::update_run( $run->id, array( 'position' => wp_json_encode( $path ) ) );
    }

    /**
     * Queue one email step. Returns the log line describing what happened, so the caller
     * records exactly one event per attempt — queued, suppressed as a duplicate, or failed.
     *
     * The send queue has a UNIQUE(campaign_id, subscriber_id), so a subscriber can never be
     * sent the same campaign twice. INSERT IGNORE swallows that silently; we surface it.
     */
    private static function send_step_email( $step, $run, $path ) {
        global $wpdb;

        $campaign_id = (int) ( $step['campaign_id'] ?? 0 );
        $post        = $campaign_id ? get_post( $campaign_id ) : null;

        if ( ! $post ) {
            \Snel\Newsletter\Logger\Logger::warning( 'automations', 'Email step skipped — campaign missing', array(
                'campaign_id'   => $campaign_id,
                'automation_id' => $run->automation_id,
            ) );
            self::log_event(
                $run, $path, 'email', (string) $campaign_id,
                sprintf( 'Not sent — campaign #%d no longer exists', $campaign_id ),
                'error'
            );
            return;
        }

        $queue = $wpdb->prefix . 'snel_send_queue';
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO $queue (campaign_id, subscriber_id) VALUES (%d, %d)",
            $campaign_id,
            $run->subscriber_id
        ) );

        // 0 rows means the UNIQUE key rejected it — this subscriber already has this campaign.
        if ( 0 === (int) $wpdb->rows_affected ) {
            self::log_event(
                $run, $path, 'email', (string) $campaign_id,
                sprintf( 'Already queued "%s" — duplicate suppressed, not sent twice', $post->post_title ),
                'warning'
            );
            return;
        }

        self::log_event(
            $run, $path, 'email', (string) $campaign_id,
            sprintf( 'Queued "%s"', $post->post_title )
        );

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

    private static function human_wait( $days, $hours ) {
        $parts = array();
        if ( $days ) {
            $parts[] = sprintf( _n( '%d day', '%d days', $days, 'snel-newsletter' ), $days );
        }
        if ( $hours ) {
            $parts[] = sprintf( _n( '%d hour', '%d hours', $hours, 'snel-newsletter' ), $hours );
        }
        return $parts ? implode( ' ', $parts ) : '1 minute (minimum)';
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

    /**
     * Record that a subscriber executed a step. This is the only history we keep —
     * the run row itself holds just the current position — so it's what the node
     * inspector reads to answer "who went through here, and what happened".
     *
     * @param string $detail Step-specific: campaign id, tag, resume time, or yes/no.
     */
    private static function log_event( $run, $path, $type, $detail = '', $message = '', $level = 'info' ) {
        global $wpdb;

        $wpdb->insert(
            Model::events_table(),
            array(
                'automation_id' => (int) $run->automation_id,
                'subscriber_id' => (int) $run->subscriber_id,
                'step_path'     => null === $path ? '' : wp_json_encode( $path ),
                'step_type'     => $type,
                'detail'        => (string) $detail,
                'level'         => $level,
                'message'       => (string) $message,
                'created_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }
}
