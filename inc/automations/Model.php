<?php

namespace Snel\Newsletter\Automations;

defined( 'ABSPATH' ) || exit;

// Steps are a JSON array: email {campaign_id}, wait {days, hours}, label {tag},
// condition {mode, threshold, yes: [...], no: [...]}. Conditions can't nest inside branches.
class Model {

    // Runs still in flight for an active automation; used by the cron self-heal.
    public static function has_active_runs(): bool {
        global $wpdb;
        return (bool) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}snel_automation_runs r
             INNER JOIN {$wpdb->prefix}snel_automations a ON a.id = r.automation_id AND a.status = 'active'
             WHERE r.status IN ('active', 'waiting', 'processing')"
        );
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'snel_automations';
    }

    public static function runs_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'snel_automation_runs';
    }

    public static function events_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'snel_automation_events';
    }

    // Same events table the node inspector reads, but ordered by time instead of by step:
    // that's what you want when chasing "why did this subscriber get that email".
    public static function logs( int $automation_id, int $limit = 500 ): array {
        global $wpdb;

        $subs   = $wpdb->prefix . 'snel_subscribers';
        $events = self::events_table();

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, e.step_path, e.step_type, e.detail, e.level, e.message, e.created_at,
                    s.id AS subscriber_id, s.email, s.name
             FROM $events e
             LEFT JOIN $subs s ON s.id = e.subscriber_id
             WHERE e.automation_id = %d
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT %d",
            $automation_id,
            $limit
        ), ARRAY_A );

        return $rows ?: array();
    }

    // Everyone enrolled and where they stand. `position` is the step the run executes NEXT,
    // so for a waiting run it's the step that fires once next_run_at passes, not the wait itself.
    public static function runs_list( int $automation_id ): array {
        global $wpdb;

        $subs = $wpdb->prefix . 'snel_subscribers';
        $runs = self::runs_table();

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.id AS run_id, r.position, r.status, r.next_run_at,
                    r.created_at AS enrolled_at, r.updated_at,
                    s.id, s.email, s.name, s.status AS subscriber_status
             FROM $runs r
             INNER JOIN $subs s ON s.id = r.subscriber_id
             WHERE r.automation_id = %d
             ORDER BY FIELD(r.status, 'active', 'waiting', 'completed', 'exited'), r.updated_at DESC
             LIMIT 1000",
            $automation_id
        ), ARRAY_A );

        return array_map( function ( $row ) {
            $row['position'] = json_decode( $row['position'], true ) ?: array( 0 );
            $row['id']       = (int) $row['id'];
            return $row;
        }, $rows ?: array() );
    }

    // Who passed through one node. The trigger node reads the runs table (enrolment = created_at);
    // every other node reads events. $path is a JSON step path like "[2]", or the literal "trigger".
    public static function step_subscribers( int $automation_id, string $path ): array {
        global $wpdb;

        $subs   = $wpdb->prefix . 'snel_subscribers';
        $events = self::events_table();
        $runs   = self::runs_table();

        if ( 'trigger' === $path ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.id, s.email, s.name, s.status AS subscriber_status,
                        r.created_at AS at, r.status AS run_status
                 FROM $runs r
                 INNER JOIN $subs s ON s.id = r.subscriber_id
                 WHERE r.automation_id = %d
                 ORDER BY r.created_at DESC
                 LIMIT 500",
                $automation_id
            ), ARRAY_A );

            return array( 'type' => 'trigger', 'subscribers' => $rows ?: array() );
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.email, s.name, s.status AS subscriber_status,
                    e.created_at AS at, e.step_type, e.detail
             FROM $events e
             INNER JOIN $subs s ON s.id = e.subscriber_id
             WHERE e.automation_id = %d AND e.step_path = %s
             ORDER BY e.created_at DESC
             LIMIT 500",
            $automation_id,
            $path
        ), ARRAY_A );

        if ( ! $rows ) {
            return array( 'type' => '', 'subscribers' => array() );
        }

        $type = $rows[0]['step_type'];

        // Email nodes: enrich with delivery + open state for that campaign.
        if ( 'email' === $type ) {
            $campaign_id = (int) $rows[0]['detail'];
            $queue       = $wpdb->prefix . 'snel_send_queue';
            $tracking    = $wpdb->prefix . 'snel_tracking';

            foreach ( $rows as &$row ) {
                $q = $wpdb->get_row( $wpdb->prepare(
                    "SELECT status, sent_at FROM $queue WHERE campaign_id = %d AND subscriber_id = %d",
                    $campaign_id,
                    $row['id']
                ), ARRAY_A );

                $row['send_status'] = $q['status'] ?? 'pending';
                $row['sent_at']     = $q['sent_at'] ?? null;
                $row['opened_at']   = $wpdb->get_var( $wpdb->prepare(
                    "SELECT MIN(created_at) FROM $tracking
                     WHERE campaign_id = %d AND subscriber_id = %d AND type = 'open'",
                    $campaign_id,
                    $row['id']
                ) );
                $row['clicked']     = (bool) $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM $tracking
                     WHERE campaign_id = %d AND subscriber_id = %d AND type = 'click' LIMIT 1",
                    $campaign_id,
                    $row['id']
                ) );
            }
            unset( $row );
        }

        return array( 'type' => $type, 'subscribers' => $rows );
    }

    public static function all(): array {
        global $wpdb;
        $table = self::table();
        $runs  = self::runs_table();

        $rows = $wpdb->get_results(
            "SELECT a.*,
                COALESCE(SUM(r.id IS NOT NULL), 0)              AS enrolled,
                COALESCE(SUM(r.status IN ('active','waiting')), 0) AS in_progress,
                COALESCE(SUM(r.status = 'completed'), 0)        AS completed
             FROM $table a
             LEFT JOIN $runs r ON r.automation_id = a.id
             GROUP BY a.id
             ORDER BY a.id DESC"
        );

        return array_map( array( __CLASS__, 'format' ), $rows ?: array() );
    }

    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
        return $row ? self::format( $row ) : null;
    }

    public static function create( array $data ): int {
        global $wpdb;

        $wpdb->insert( self::table(), array(
            'name'         => $data['name'],
            'status'       => $data['status'] ?? 'paused',
            'trigger_type' => $data['trigger_type'] ?? 'tag',
            'trigger_tag'  => $data['trigger_tag'] ?? '',
            'steps'        => wp_json_encode( $data['steps'] ?? array() ),
        ), array( '%s', '%s', '%s', '%s', '%s' ) );

        return $wpdb->insert_id;
    }

    public static function update( int $id, array $data ): bool {
        global $wpdb;

        $fields  = array( 'updated_at' => current_time( 'mysql' ) );
        $formats = array( '%s' );

        foreach ( array( 'name', 'status', 'trigger_type', 'trigger_tag' ) as $key ) {
            if ( isset( $data[ $key ] ) ) {
                $fields[ $key ] = $data[ $key ];
                $formats[]      = '%s';
            }
        }
        if ( isset( $data['steps'] ) ) {
            $fields['steps'] = wp_json_encode( $data['steps'] );
            $formats[]       = '%s';
        }

        return false !== $wpdb->update( self::table(), $fields, array( 'id' => $id ), $formats, array( '%d' ) );
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        $wpdb->delete( self::runs_table(), array( 'automation_id' => $id ), array( '%d' ) );
        $wpdb->delete( self::events_table(), array( 'automation_id' => $id ), array( '%d' ) );
        return false !== $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }

    public static function run_counts( int $id ): array {
        global $wpdb;
        $runs = self::runs_table();

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS enrolled,
                COALESCE(SUM(status IN ('active','waiting')), 0) AS in_progress,
                COALESCE(SUM(status = 'completed'), 0)           AS completed
             FROM $runs WHERE automation_id = %d",
            $id
        ) );

        return array(
            'enrolled'    => (int) ( $row->enrolled ?? 0 ),
            'in_progress' => (int) ( $row->in_progress ?? 0 ),
            'completed'   => (int) ( $row->completed ?? 0 ),
        );
    }

    // Sent/opened per email step's campaign, scoped to this automation's enrolled subscribers.
    public static function email_stats( array $automation ): array {
        global $wpdb;

        $queue    = $wpdb->prefix . 'snel_send_queue';
        $tracking = $wpdb->prefix . 'snel_tracking';
        $runs     = self::runs_table();

        $stats = array();
        foreach ( self::collect_campaign_ids( $automation['steps'] ) as $cid ) {
            $stats[ $cid ] = array(
                'sent'   => (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM $queue q
                     INNER JOIN $runs r ON r.subscriber_id = q.subscriber_id AND r.automation_id = %d
                     WHERE q.campaign_id = %d AND q.status = 'sent'",
                    $automation['id'], $cid
                ) ),
                'opened' => (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(DISTINCT t.subscriber_id) FROM $tracking t
                     INNER JOIN $runs r ON r.subscriber_id = t.subscriber_id AND r.automation_id = %d
                     WHERE t.campaign_id = %d AND t.type = 'open'",
                    $automation['id'], $cid
                ) ),
            );
        }

        return $stats;
    }

    private static function collect_campaign_ids( $steps ): array {
        $ids = array();
        foreach ( (array) $steps as $step ) {
            if ( ( $step['type'] ?? '' ) === 'email' && ! empty( $step['campaign_id'] ) ) {
                $ids[] = (int) $step['campaign_id'];
            } elseif ( ( $step['type'] ?? '' ) === 'condition' ) {
                $ids = array_merge( $ids, self::collect_campaign_ids( $step['yes'] ?? array() ), self::collect_campaign_ids( $step['no'] ?? array() ) );
            }
        }
        return array_unique( $ids );
    }

    private static function format( object $row ): array {
        return array(
            'id'           => (int) $row->id,
            'name'         => $row->name,
            'status'       => $row->status,
            'trigger_type' => $row->trigger_type,
            'trigger_tag'  => $row->trigger_tag,
            'steps'        => json_decode( $row->steps ?: '[]', true ) ?: array(),
            'created_at'   => $row->created_at,
            'updated_at'   => $row->updated_at,
            'enrolled'     => isset( $row->enrolled ) ? (int) $row->enrolled : null,
            'in_progress'  => isset( $row->in_progress ) ? (int) $row->in_progress : null,
            'completed'    => isset( $row->completed ) ? (int) $row->completed : null,
        );
    }
}
