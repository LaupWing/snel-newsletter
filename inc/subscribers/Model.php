<?php
/**
 * Subscriber database queries.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Subscribers;

defined( 'ABSPATH' ) || exit;

class Model {

    private static $table = 'snel_subscribers';
    private static $tags_table = 'snel_subscriber_tags';

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . self::$table;
    }

    private static function tags_table() {
        global $wpdb;
        return $wpdb->prefix . self::$tags_table;
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

        if ( isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'unsubscribed', 'bounced', 'complained' ), true ) ) {
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
        $wpdb->delete( $tags_table, array( 'tag' => $old_tag ), array( '%s' ) );

        return true;
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
     * Get all unique tags with counts.
     */
    public static function all_tags() {
        global $wpdb;
        $tags_table = self::tags_table();

        return $wpdb->get_results( "SELECT tag, COUNT(*) as count FROM $tags_table GROUP BY tag ORDER BY tag ASC" ) ?: array();
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
