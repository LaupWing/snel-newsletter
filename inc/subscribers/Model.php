<?php
/**
 * Subscriber database queries.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Subscribers;

defined( 'ABSPATH' ) || exit;

class Model {

    private static $table       = 'snel_subscribers';
    private static $tags_table  = 'snel_subscriber_tags';
    private static $rules_table = 'snel_tag_rules';

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . self::$table;
    }

    private static function tags_table() {
        global $wpdb;
        return $wpdb->prefix . self::$tags_table;
    }

    private static function rules_table() {
        global $wpdb;
        return $wpdb->prefix . self::$rules_table;
    }

    /**
     * List subscribers with filters and pagination.
     */
    public static function list( $args = array() ) {
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

        // Count.
        $count_sql = "SELECT COUNT(DISTINCT s.id) FROM $table s $join WHERE $where_sql";
        $total     = $values ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : (int) $wpdb->get_var( $count_sql );

        // Rows.
        $query = "SELECT DISTINCT s.* FROM $table s $join WHERE $where_sql ORDER BY s.created_at DESC LIMIT %d OFFSET %d";
        $args  = array_merge( $values, array( $per_page, $offset ) );
        $rows  = $wpdb->get_results( $wpdb->prepare( $query, $args ) );

        // Attach tags.
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

    /**
     * SQL expression per engagement metric, computed off the tracking table.
     * Mirrors the metric definitions used by dynamic tags (see sync_dynamic_tag).
     * NULLIF guards division-by-zero for subscribers with no campaigns — a NULL
     * result simply fails any HAVING comparison, excluding them, which is right.
     */
    private static function metric_expr( $metric ) {
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

    /**
     * Turn an array of filter conditions into SQL fragments.
     *
     * Each condition is { field, operator, value }. Fields split three ways:
     *  - metrics (open_rate, clicks, …) → HAVING on a grouped tracking join
     *  - status / search               → plain WHERE on the subscribers table
     *  - tag                           → EXISTS / NOT EXISTS on the tags table
     *
     * All conditions are AND-ed. Returns where/having SQL plus their params in
     * the order they must be bound (where first, then having).
     *
     * @return array { where: string[], where_params: array, having: string[], having_params: array, needs_tracking: bool }
     */
    private static function build_conditions( $filters ) {
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

            // Time-windowed engagement → EXISTS on recent tracking rows.
            // "opened_in_days" / "clicked_in_days", value = number of days.
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

            // Metric → HAVING clause.
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

            // Status → WHERE.
            if ( $field === 'status' ) {
                if ( $value === '' ) {
                    continue;
                }
                $where[]        = 's.status = %s';
                $where_params[] = sanitize_text_field( $value );
                continue;
            }

            // Search → WHERE (email or name).
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

            // Tag → EXISTS / NOT EXISTS.
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

    /**
     * Query subscribers by a stack of filter conditions (all AND-ed), paginated.
     *
     * @param array $filters  Array of { field, operator, value }.
     * @return array { subscribers, total, page, per_page, pages }
     */
    public static function query( $filters, $page = 1, $per_page = 20 ) {
        global $wpdb;

        $table    = self::table();
        $tracking = $wpdb->prefix . 'snel_tracking';
        $page     = max( 1, (int) $page );
        $per_page = max( 1, min( 100, (int) $per_page ) );
        $offset   = ( $page - 1 ) * $per_page;

        $c          = self::build_conditions( $filters );
        $where_sql  = $c['where'] ? 'WHERE ' . implode( ' AND ', $c['where'] ) : '';
        $having_sql = $c['having'] ? 'HAVING ' . implode( ' AND ', $c['having'] ) : '';

        // The tracking join + GROUP BY are only needed when a metric filter is
        // in play; otherwise it's a plain filtered table scan.
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

    /**
     * Return every subscriber ID matching a filter stack — no pagination.
     * Powers "select all N matching" so bulk actions can act on the whole set.
     */
    public static function ids_for_filters( $filters ) {
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

    /**
     * A subscriber's send history: every campaign they were queued for, with
     * whether they opened/clicked it. Powers the "review list" modal so you can
     * see who actually engaged with which email.
     */
    public static function history( $subscriber_id ) {
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

    /**
     * Get status counts.
     */
    public static function counts() {
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

    /**
     * Create a subscriber. Returns insert ID or false on duplicate.
     */
    public static function create( $email, $name = '', $status = 'active' ) {
        global $wpdb;
        $table = self::table();

        // Check duplicate.
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

    /**
     * Update a subscriber's fields.
     */
    public static function update( $id, $data ) {
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

    /**
     * Delete a subscriber and their tags.
     */
    public static function delete( $id ) {
        global $wpdb;

        $wpdb->delete( self::tags_table(), array( 'subscriber_id' => $id ), array( '%d' ) );
        $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );

        return true;
    }

    /**
     * Delete multiple subscribers.
     */
    public static function bulk_delete( $ids ) {
        global $wpdb;

        $ids_in = implode( ',', array_map( 'intval', $ids ) );

        $wpdb->query( "DELETE FROM " . self::tags_table() . " WHERE subscriber_id IN ($ids_in)" );
        $wpdb->query( "DELETE FROM " . self::table() . " WHERE id IN ($ids_in)" );

        return count( $ids );
    }

    /**
     * Replace all tags for a subscriber.
     */
    public static function set_tags( $id, $tags ) {
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

    /**
     * Add tags to a subscriber (ignores duplicates).
     */
    public static function add_tags( $id, $tags ) {
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

    /**
     * Add a tag to multiple subscribers.
     */
    public static function bulk_add_tag( $ids, $tag ) {
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

    /**
     * Remove a tag from multiple subscribers.
     */
    public static function bulk_remove_tag( $ids, $tag ) {
        global $wpdb;
        $tags_table = self::tags_table();
        $ids_in     = implode( ',', array_map( 'intval', $ids ) );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $tags_table WHERE subscriber_id IN ($ids_in) AND tag = %s",
            $tag
        ) );

        return true;
    }

    /**
     * Rename a tag across all subscribers.
     */
    /**
     * Rename a tag everywhere. Returns how many subscriber rows were moved.
     */
    public static function rename_tag_global( $old_tag, $new_tag ) {
        global $wpdb;
        $tags_table = self::tags_table();

        // Insert new tag for subscribers who don't already have it.
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO $tags_table (subscriber_id, tag)
             SELECT subscriber_id, %s FROM $tags_table WHERE tag = %s",
            $new_tag, $old_tag
        ) );

        // The caller upserts a rule under the new name, so drop the old rule
        // row — otherwise the old name lingers on as an empty tag.
        $wpdb->delete( self::rules_table(), array( 'tag' => $old_tag ), array( '%s' ) );

        // Delete the old tag.
        return (int) $wpdb->delete( $tags_table, array( 'tag' => $old_tag ), array( '%s' ) );
    }

    /**
     * Delete a tag from all subscribers, and forget its rule.
     */
    public static function delete_tag_global( $tag ) {
        global $wpdb;

        $wpdb->delete( self::tags_table(), array( 'tag' => $tag ), array( '%s' ) );
        $wpdb->delete( self::rules_table(), array( 'tag' => $tag ), array( '%s' ) );

        return true;
    }

    /**
     * Create a tag that has no subscribers yet.
     *
     * Tags normally exist only because a subscriber carries one. Creating an
     * empty tag therefore means writing a rule row and nothing else — that row
     * is what keeps the tag visible in the list until someone is tagged.
     *
     * @return bool False if the tag already exists.
     */
    public static function create_tag( $tag, $type = 'static', $metric = null, $operator = null, $threshold = null ) {
        if ( self::tag_exists( $tag ) ) {
            return false;
        }

        self::save_tag_rule( $tag, $type, $metric, $operator, $threshold );

        return true;
    }

    /**
     * Does this tag exist — either on a subscriber, or as a rule?
     */
    public static function tag_exists( $tag ) {
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

    /**
     * Get all unique tags with counts and rule info.
     */
    public static function all_tags() {
        global $wpdb;
        $tags_table  = self::tags_table();
        $rules_table = self::rules_table();

        // A tag lives in two places: on subscribers, and (optionally) as a rule.
        // Union both so a freshly created tag with no subscribers still shows up.
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

    /**
     * Save rule for a tag (upsert).
     */
    public static function save_tag_rule( $tag, $type, $metric = null, $operator = null, $threshold = null ) {
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

    /**
     * Evaluate a dynamic tag rule and sync subscriber_tags for it.
     * Returns count of subscribers now tagged.
     */
    public static function sync_dynamic_tag( $tag ) {
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

        // Build the subquery for the metric value per subscriber.
        switch ( $rule->metric ) {
            case 'open_rate':
                // (distinct opens / distinct campaigns received) * 100
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

        // Find matching subscriber IDs.
        $matching_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT s.id
             FROM $subs_table s
             INNER JOIN $tracking_table tr ON tr.subscriber_id = s.id
             GROUP BY s.id
             HAVING $metric_expr $sql_op %f",
            $threshold
        ) );

        // Clear all current assignments for this dynamic tag.
        $wpdb->delete( $tags_table, array( 'tag' => $tag ), array( '%s' ) );

        // Re-insert matching.
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

    /**
     * Get all subscriber emails (for duplicate checking).
     */
    public static function all_emails() {
        global $wpdb;
        $table = self::table();

        return $wpdb->get_col( "SELECT email FROM $table" );
    }

    /**
     * Find a subscriber id by email. Returns int or null.
     */
    public static function find_by_email( $email ) {
        global $wpdb;
        $table = self::table();

        $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE email = %s", $email ) );

        return $id ? (int) $id : null;
    }

    /**
     * Attach tags array to a list of subscriber rows.
     */
    private static function attach_tags( $rows ) {
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
