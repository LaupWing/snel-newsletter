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

        // Delete the old tag.
        return (int) $wpdb->delete( $tags_table, array( 'tag' => $old_tag ), array( '%s' ) );
    }

    /**
     * Delete a tag from all subscribers.
     */
    public static function delete_tag_global( $tag ) {
        global $wpdb;
        $tags_table = self::tags_table();

        $wpdb->delete( $tags_table, array( 'tag' => $tag ), array( '%s' ) );

        return true;
    }

    /**
     * Get all unique tags with counts and rule info.
     */
    public static function all_tags() {
        global $wpdb;
        $tags_table  = self::tags_table();
        $rules_table = self::rules_table();

        return $wpdb->get_results(
            "SELECT t.tag, COUNT(*) as count,
                    COALESCE(r.type, 'static') as type,
                    r.metric, r.operator, r.threshold
             FROM $tags_table t
             LEFT JOIN $rules_table r ON r.tag = t.tag
             GROUP BY t.tag, r.type, r.metric, r.operator, r.threshold
             ORDER BY t.tag ASC"
        ) ?: array();
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
