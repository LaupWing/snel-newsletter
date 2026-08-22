<?php

namespace Snel\Newsletter\Subscribers;

defined( 'ABSPATH' ) || exit;

// SOT:MODEL — all SQL for a domain lives in its Model; Controller and Rest never query.
class Model {

    private static $table       = 'snel_subscribers';
    private static $tags_table  = 'snel_subscriber_tags';
    private static $rules_table = 'snel_tag_rules';

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . self::$table;
    }

    private static function tags_table(): string {
        global $wpdb;
        return $wpdb->prefix . self::$tags_table;
    }

    private static function rules_table(): string {
        global $wpdb;
        return $wpdb->prefix . self::$rules_table;
    }

    // Legacy list; the subscribers page moves to query() with the filter engine.
    public static function list( array $args = array() ): array {
        global $wpdb;

        $table      = self::table();
        $tags_table = self::tags_table();

        $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
        $per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
        $search   = $args['search'] ?? '';
        $tag      = $args['tag'] ?? '';
        $status   = $args['status'] ?? '';

        $where  = array( '1=1' );
        $values = array();

        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '(s.email LIKE %s OR s.name LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        if ( $status ) {
            $where[]  = 's.status = %s';
            $values[] = $status;
        }

        $join = '';
        if ( $tag ) {
            $join     = "INNER JOIN $tags_table t ON t.subscriber_id = s.id";
            $where[]  = 't.tag = %s';
            $values[] = $tag;
        }

        $where_sql = implode( ' AND ', $where );
        $offset    = ( $page - 1 ) * $per_page;

        $count_sql = "SELECT COUNT(DISTINCT s.id) FROM $table s $join WHERE $where_sql";
        $total     = $values ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : (int) $wpdb->get_var( $count_sql );

        $query = "SELECT DISTINCT s.* FROM $table s $join WHERE $where_sql ORDER BY s.created_at DESC LIMIT %d OFFSET %d";
        $args  = array_merge( $values, array( $per_page, $offset ) );
        $rows  = $wpdb->get_results( $wpdb->prepare( $query, $args ) );

        if ( $rows ) {
            $rows = self::attach_tags( $rows );
        }

        return array(
            'subscribers' => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'pages'       => (int) ceil( $total / $per_page ),
        );
    }

    // Same metric definitions as sync_dynamic_tag(); keep them in step.
    // NULLIF makes subscribers without campaigns NULL, so they fail every HAVING compare.
    private static function metric_expr( string $metric ): ?string {
        switch ( $metric ) {
            case 'open_rate':
                return "ROUND(SUM(CASE WHEN tr.type = 'open' THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT tr.campaign_id),0) * 100, 2)";
            case 'click_rate':
                return "ROUND(COUNT(DISTINCT CASE WHEN tr.type = 'click' THEN tr.campaign_id END) / NULLIF(COUNT(DISTINCT tr.campaign_id),0) * 100, 2)";
            case 'opens':
                return "SUM(CASE WHEN tr.type = 'open' THEN 1 ELSE 0 END)";
            case 'clicks':
                return "SUM(CASE WHEN tr.type = 'click' THEN 1 ELSE 0 END)";
            case 'emails_received':
                return "COUNT(DISTINCT tr.campaign_id)";
            default:
                return null;
        }
    }

    // Metrics become HAVING on a grouped tracking join, status/search plain WHERE, tag EXISTS.
    // Params come back in bind order: where first, then having.
    private static function build_conditions( array $filters ): array {
        global $wpdb;
        $tags_table = self::tags_table();

        $op_map = array( 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<=', 'eq' => '=' );

        $where          = array();
        $where_params   = array();
        $having         = array();
        $having_params  = array();
        $needs_tracking = false;

        foreach ( (array) $filters as $f ) {
            $field    = $f['field'] ?? '';
            $operator = $f['operator'] ?? '';
            $value    = $f['value'] ?? '';

            if ( $field === 'opened_in_days' || $field === 'clicked_in_days' ) {
                $days = (int) $value;
                if ( $days <= 0 ) {
                    continue;
                }
                $tracking       = $wpdb->prefix . 'snel_tracking';
                $type           = $field === 'opened_in_days' ? 'open' : 'click';
                $where[]        = "EXISTS (SELECT 1 FROM $tracking tw WHERE tw.subscriber_id = s.id AND tw.type = %s AND tw.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY))";
                $where_params[] = $type;
                $where_params[] = $days;
                continue;
            }

            $expr = self::metric_expr( $field );
            if ( $expr ) {
                $sql_op = $op_map[ $operator ] ?? null;
                if ( ! $sql_op || $value === '' ) {
                    continue;
                }
                $needs_tracking  = true;
                $having[]        = "$expr $sql_op %f";
                $having_params[] = (float) $value;
                continue;
            }

            if ( $field === 'status' ) {
                if ( $value === '' ) {
                    continue;
                }
                $where[]        = 's.status = %s';
                $where_params[] = sanitize_text_field( $value );
                continue;
            }

            if ( $field === 'search' ) {
                if ( $value === '' ) {
                    continue;
                }
                $like           = '%' . $wpdb->esc_like( $value ) . '%';
                $where[]        = '(s.email LIKE %s OR s.name LIKE %s)';
                $where_params[] = $like;
                $where_params[] = $like;
                continue;
            }

            if ( $field === 'tag' ) {
                if ( $value === '' ) {
                    continue;
                }
                $exists         = $operator === 'not_has' ? 'NOT EXISTS' : 'EXISTS';
                $where[]        = "$exists (SELECT 1 FROM $tags_table te WHERE te.subscriber_id = s.id AND te.tag = %s)";
                $where_params[] = sanitize_text_field( $value );
                continue;
            }
        }

        return array(
            'where'          => $where,
            'where_params'   => $where_params,
            'having'         => $having,
            'having_params'  => $having_params,
            'needs_tracking' => $needs_tracking,
        );
    }

    public static function query( array $filters, $page = 1, $per_page = 20 ): array {
        global $wpdb;

        $table    = self::table();
        $tracking = $wpdb->prefix . 'snel_tracking';
        $page     = max( 1, (int) $page );
        $per_page = max( 1, min( 100, (int) $per_page ) );
        $offset   = ( $page - 1 ) * $per_page;

        $c          = self::build_conditions( $filters );
        $where_sql  = $c['where'] ? 'WHERE ' . implode( ' AND ', $c['where'] ) : '';
        $having_sql = $c['having'] ? 'HAVING ' . implode( ' AND ', $c['having'] ) : '';

        if ( $c['needs_tracking'] ) {
            $join     = "LEFT JOIN $tracking tr ON tr.subscriber_id = s.id";
            $group    = 'GROUP BY s.id';
            $inner    = "SELECT s.id FROM $table s $join $where_sql $group $having_sql";
            $count_sql = "SELECT COUNT(*) FROM ( $inner ) x";
        } else {
            $join      = '';
            $group     = '';
            $count_sql = "SELECT COUNT(*) FROM $table s $where_sql";
        }

        $all_params = array_merge( $c['where_params'], $c['having_params'] );

        $total = $all_params
            ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $all_params ) )
            : (int) $wpdb->get_var( $count_sql );

        $rows_sql = "SELECT s.* FROM $table s $join $where_sql $group $having_sql
                     ORDER BY s.created_at DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $all_params, array( $per_page, $offset ) ) ) );

        if ( $rows ) {
            $rows = self::attach_tags( $rows );
        }

        return array(
            'subscribers' => $rows ?: array(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'pages'       => (int) ceil( $total / $per_page ),
        );
    }

    public static function ids_for_filters( array $filters ): array {
        global $wpdb;

        $table    = self::table();
        $tracking = $wpdb->prefix . 'snel_tracking';

        $c          = self::build_conditions( $filters );
        $where_sql  = $c['where'] ? 'WHERE ' . implode( ' AND ', $c['where'] ) : '';
        $having_sql = $c['having'] ? 'HAVING ' . implode( ' AND ', $c['having'] ) : '';

        if ( $c['needs_tracking'] ) {
            $sql = "SELECT s.id FROM $table s
                    LEFT JOIN $tracking tr ON tr.subscriber_id = s.id
                    $where_sql GROUP BY s.id $having_sql";
        } else {
            $sql = "SELECT s.id FROM $table s $where_sql";
        }

        $params = array_merge( $c['where_params'], $c['having_params'] );
        $ids    = $params
            ? $wpdb->get_col( $wpdb->prepare( $sql, $params ) )
            : $wpdb->get_col( $sql );

        return array_map( 'intval', $ids );
    }

    public static function history( int $subscriber_id ): array {
        global $wpdb;

        $queue    = $wpdb->prefix . 'snel_send_queue';
        $tracking = $wpdb->prefix . 'snel_tracking';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT q.campaign_id, p.post_title AS subject, q.status, q.sent_at,
                    MAX(CASE WHEN t.type = 'open'  THEN 1 ELSE 0 END) AS opened,
                    MAX(CASE WHEN t.type = 'click' THEN 1 ELSE 0 END) AS clicked
             FROM $queue q
             LEFT JOIN {$wpdb->posts} p ON p.ID = q.campaign_id
             LEFT JOIN $tracking t ON t.campaign_id = q.campaign_id AND t.subscriber_id = q.subscriber_id
             WHERE q.subscriber_id = %d
             GROUP BY q.campaign_id, p.post_title, q.status, q.sent_at
             ORDER BY q.sent_at DESC, q.campaign_id DESC",
            $subscriber_id
        ) ) ?: array();
    }

    public static function counts(): array {
        global $wpdb;
        $table = self::table();

        return array(
            'total'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ),
            'active'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'active'" ),
            'unsubscribed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'unsubscribed'" ),
            'bounced'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'bounced'" ),
            'complained'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'complained'" ),
        );
    }

    public static function active_counts_by_tag(): array {
        global $wpdb;
        $table      = self::table();
        $tags_table = self::tags_table();

        $rows = $wpdb->get_results(
            "SELECT t.tag, COUNT(DISTINCT t.subscriber_id) AS count
             FROM $tags_table t
             INNER JOIN $table s ON s.id = t.subscriber_id
             WHERE s.status = 'active'
             GROUP BY t.tag"
        ) ?: array();

        $counts = array();
        foreach ( $rows as $row ) {
            $counts[ $row->tag ] = (int) $row->count;
        }
        return $counts;
    }

    public static function count_for_tags( array $tags ): int {
        global $wpdb;

        $tags = array_filter( array_map( 'sanitize_text_field', $tags ) );
        if ( empty( $tags ) ) {
            return 0;
        }

        $table        = self::table();
        $tags_table   = self::tags_table();
        $placeholders = implode( ',', array_fill( 0, count( $tags ), '%s' ) );

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT s.id) FROM $table s
             INNER JOIN $tags_table t ON t.subscriber_id = s.id
             WHERE s.status = 'active' AND t.tag IN ($placeholders)",
            $tags
        ) );
    }

    public static function create( string $email, string $name = '', string $status = 'active' ) {
        global $wpdb;
        $table = self::table();

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE email = %s", $email ) );
        if ( $exists ) {
            return false;
        }

        $token = wp_generate_password( 32, false );

        $wpdb->insert( $table, array(
            'email'             => $email,
            'name'              => $name,
            'status'            => $status,
            'unsubscribe_token' => $token,
        ), array( '%s', '%s', '%s', '%s' ) );

        return $wpdb->insert_id;
    }

    public static function update( int $id, array $data ): bool {
        global $wpdb;
        $table = self::table();

        $update = array();
        $format = array();

        if ( isset( $data['name'] ) ) {
            $update['name'] = $data['name'];
            $format[]       = '%s';
        }

        if ( isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'inactive', 'unsubscribed', 'bounced', 'complained' ), true ) ) {
            $update['status'] = $data['status'];
            $format[]         = '%s';
        }

        if ( $update ) {
            $wpdb->update( $table, $update, array( 'id' => $id ), $format, array( '%d' ) );
        }

        return true;
    }

    public static function delete( int $id ): bool {
        global $wpdb;

        $wpdb->delete( self::tags_table(), array( 'subscriber_id' => $id ), array( '%d' ) );
        $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );

        return true;
    }

    public static function bulk_delete( array $ids ): int {
        global $wpdb;

        $ids_in = implode( ',', array_map( 'intval', $ids ) );

        $wpdb->query( "DELETE FROM " . self::tags_table() . " WHERE subscriber_id IN ($ids_in)" );
        $wpdb->query( "DELETE FROM " . self::table() . " WHERE id IN ($ids_in)" );

        return count( $ids );
    }

    public static function set_tags( int $id, array $tags ): bool {
        global $wpdb;
        $tags_table = self::tags_table();

        $wpdb->delete( $tags_table, array( 'subscriber_id' => $id ), array( '%d' ) );

        foreach ( $tags as $tag ) {
            if ( $tag ) {
                $wpdb->insert( $tags_table, array(
                    'subscriber_id' => $id,
                    'tag'           => $tag,
                ), array( '%d', '%s' ) );
            }
        }

        if ( $tags ) {
            do_action( 'snel_newsletter_tags_added', (int) $id, array_values( array_filter( $tags ) ) );
        }

        return true;
    }

    public static function add_tags( int $id, array $tags ): bool {
        global $wpdb;
        $tags_table = self::tags_table();

        foreach ( $tags as $tag ) {
            if ( $tag ) {
                $wpdb->query( $wpdb->prepare(
                    "INSERT IGNORE INTO $tags_table (subscriber_id, tag) VALUES (%d, %s)",
                    $id, $tag
                ) );
            }
        }

        if ( $tags ) {
            do_action( 'snel_newsletter_tags_added', (int) $id, array_values( array_filter( $tags ) ) );
        }

        return true;
    }

    public static function bulk_add_tag( array $ids, string $tag ): bool {
        global $wpdb;
        $tags_table = self::tags_table();

        foreach ( $ids as $id ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO $tags_table (subscriber_id, tag) VALUES (%d, %s)",
                (int) $id, $tag
            ) );
            do_action( 'snel_newsletter_tags_added', (int) $id, array( $tag ) );
        }

        return true;
    }

    public static function bulk_remove_tag( array $ids, string $tag ): bool {
        global $wpdb;
        $tags_table = self::tags_table();
        $ids_in     = implode( ',', array_map( 'intval', $ids ) );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $tags_table WHERE subscriber_id IN ($ids_in) AND tag = %s",
            $tag
        ) );

        return true;
    }

    public static function rename_tag_global( string $old_tag, string $new_tag ): int {
        global $wpdb;
        $tags_table = self::tags_table();

        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO $tags_table (subscriber_id, tag)
             SELECT subscriber_id, %s FROM $tags_table WHERE tag = %s",
            $new_tag, $old_tag
        ) );

        // The caller upserts a rule under the new name; without dropping the old
        // rule row the old name lingers on as an empty tag.
        $wpdb->delete( self::rules_table(), array( 'tag' => $old_tag ), array( '%s' ) );

        return (int) $wpdb->delete( $tags_table, array( 'tag' => $old_tag ), array( '%s' ) );
    }

    public static function delete_tag_global( string $tag ): bool {
        global $wpdb;

        $wpdb->delete( self::tags_table(), array( 'tag' => $tag ), array( '%s' ) );
        $wpdb->delete( self::rules_table(), array( 'tag' => $tag ), array( '%s' ) );

        return true;
    }

    // Tags only exist through subscribers, so an empty tag is just a rule row;
    // that row keeps it visible in the list until someone is tagged.
    public static function create_tag( string $tag, string $type = 'static', ?string $metric = null, ?string $operator = null, $threshold = null ): bool {
        if ( self::tag_exists( $tag ) ) {
            return false;
        }

        self::save_tag_rule( $tag, $type, $metric, $operator, $threshold );

        return true;
    }

    public static function tag_exists( string $tag ): bool {
        global $wpdb;
        $tags_table  = self::tags_table();
        $rules_table = self::rules_table();

        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT 1 FROM $tags_table WHERE tag = %s
             UNION
             SELECT 1 FROM $rules_table WHERE tag = %s
             LIMIT 1",
            $tag,
            $tag
        ) );
    }

    public static function all_tags(): array {
        global $wpdb;
        $tags_table  = self::tags_table();
        $rules_table = self::rules_table();

        // A tag lives on subscribers and optionally as a rule; union both so a
        // freshly created tag with no subscribers still shows up.
        $rows = $wpdb->get_results(
            "SELECT all_tags.tag,
                    ( SELECT COUNT(*) FROM $tags_table st WHERE st.tag = all_tags.tag ) as count,
                    COALESCE(r.type, 'static') as type,
                    r.metric, r.operator, r.threshold
             FROM (
                 SELECT DISTINCT tag FROM $tags_table
                 UNION
                 SELECT tag FROM $rules_table
             ) all_tags
             LEFT JOIN $rules_table r ON r.tag = all_tags.tag
             ORDER BY all_tags.tag ASC"
        ) ?: array();

        foreach ( $rows as $row ) {
            $row->count = (int) $row->count;
        }

        return $rows;
    }

    public static function save_tag_rule( string $tag, string $type, ?string $metric = null, ?string $operator = null, $threshold = null ): bool {
        global $wpdb;
        $rules_table = self::rules_table();

        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $rules_table WHERE tag = %s", $tag ) );

        if ( $existing ) {
            $wpdb->update(
                $rules_table,
                array(
                    'type'      => $type,
                    'metric'    => $metric,
                    'operator'  => $operator,
                    'threshold' => $threshold,
                ),
                array( 'tag' => $tag ),
                array( '%s', '%s', '%s', '%f' ),
                array( '%s' )
            );
        } else {
            $wpdb->insert(
                $rules_table,
                array(
                    'tag'       => $tag,
                    'type'      => $type,
                    'metric'    => $metric,
                    'operator'  => $operator,
                    'threshold' => $threshold,
                ),
                array( '%s', '%s', '%s', '%s', '%f' )
            );
        }

        return true;
    }

    public static function sync_dynamic_tag( string $tag ): int {
        global $wpdb;

        $rules_table    = self::rules_table();
        $tags_table     = self::tags_table();
        $tracking_table = $wpdb->prefix . 'snel_tracking';
        $subs_table     = self::table();

        $rule = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $rules_table WHERE tag = %s AND type = 'dynamic'",
            $tag
        ) );

        if ( ! $rule || ! $rule->metric || ! $rule->operator || $rule->threshold === null ) {
            return 0;
        }

        $op_map = array(
            'gt'  => '>',
            'gte' => '>=',
            'lt'  => '<',
            'lte' => '<=',
            'eq'  => '=',
        );

        $sql_op = $op_map[ $rule->operator ] ?? null;
        if ( ! $sql_op ) return 0;

        $threshold = (float) $rule->threshold;

        switch ( $rule->metric ) {
            case 'open_rate':
                $metric_expr = "ROUND(SUM(CASE WHEN tr.type = 'open' THEN 1 ELSE 0 END) / COUNT(DISTINCT tr.campaign_id) * 100, 2)";
                break;
            case 'click_rate':
                $metric_expr = "ROUND(COUNT(DISTINCT CASE WHEN tr.type = 'click' THEN tr.campaign_id END) / COUNT(DISTINCT tr.campaign_id) * 100, 2)";
                break;
            case 'opens':
                $metric_expr = "SUM(CASE WHEN tr.type = 'open' THEN 1 ELSE 0 END)";
                break;
            case 'clicks':
                $metric_expr = "SUM(CASE WHEN tr.type = 'click' THEN 1 ELSE 0 END)";
                break;
            case 'emails_received':
                $metric_expr = "COUNT(DISTINCT tr.campaign_id)";
                break;
            default:
                return 0;
        }

        $matching_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT s.id
             FROM $subs_table s
             INNER JOIN $tracking_table tr ON tr.subscriber_id = s.id
             GROUP BY s.id
             HAVING $metric_expr $sql_op %f",
            $threshold
        ) );

        $wpdb->delete( $tags_table, array( 'tag' => $tag ), array( '%s' ) );

        if ( $matching_ids ) {
            foreach ( $matching_ids as $id ) {
                $wpdb->query( $wpdb->prepare(
                    "INSERT IGNORE INTO $tags_table (subscriber_id, tag) VALUES (%d, %s)",
                    (int) $id, $tag
                ) );
            }
        }

        return count( $matching_ids );
    }

    public static function all_emails(): array {
        global $wpdb;
        $table = self::table();

        return $wpdb->get_col( "SELECT email FROM $table" );
    }

    public static function find_by_email( string $email ): ?int {
        global $wpdb;
        $table = self::table();

        $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE email = %s", $email ) );

        return $id ? (int) $id : null;
    }

    private static function attach_tags( array $rows ): array {
        global $wpdb;
        $tags_table = self::tags_table();

        $ids    = wp_list_pluck( $rows, 'id' );
        $ids_in = implode( ',', array_map( 'intval', $ids ) );

        $tag_rows = $wpdb->get_results( "SELECT subscriber_id, tag FROM $tags_table WHERE subscriber_id IN ($ids_in)" );
        $tag_map  = array();

        foreach ( $tag_rows as $tr ) {
            $tag_map[ $tr->subscriber_id ][] = $tr->tag;
        }

        foreach ( $rows as &$row ) {
            $row->tags = $tag_map[ $row->id ] ?? array();
        }

        return $rows;
    }
}
