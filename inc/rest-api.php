<?php
/**
 * REST API endpoints.
 *
 * @package SnelNewsletter
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
    $namespace = 'snel-newsletter/v1';

    // GET /settings — load settings.
    register_rest_route( $namespace, '/settings', array(
        'methods'             => 'GET',
        'callback'            => 'snel_newsletter_get_settings',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );

    // POST /settings — save settings.
    register_rest_route( $namespace, '/settings', array(
        'methods'             => 'POST',
        'callback'            => 'snel_newsletter_save_settings',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );

    // GET /subscribers — list subscribers.
    register_rest_route( $namespace, '/subscribers', array(
        'methods'             => 'GET',
        'callback'            => 'snel_newsletter_get_subscribers',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );

    // POST /subscribers — add a subscriber.
    register_rest_route( $namespace, '/subscribers', array(
        'methods'             => 'POST',
        'callback'            => 'snel_newsletter_add_subscriber',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );

    // PUT /subscribers/(?P<id>\d+) — update a subscriber.
    register_rest_route( $namespace, '/subscribers/(?P<id>\d+)', array(
        'methods'             => 'PUT',
        'callback'            => 'snel_newsletter_update_subscriber',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );

    // DELETE /subscribers/(?P<id>\d+) — delete a subscriber.
    register_rest_route( $namespace, '/subscribers/(?P<id>\d+)', array(
        'methods'             => 'DELETE',
        'callback'            => 'snel_newsletter_delete_subscriber',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );

    // POST /subscribers/bulk-delete — delete multiple subscribers.
    register_rest_route( $namespace, '/subscribers/bulk-delete', array(
        'methods'             => 'POST',
        'callback'            => 'snel_newsletter_bulk_delete_subscribers',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );

    // GET /tags — list all unique tags.
    register_rest_route( $namespace, '/tags', array(
        'methods'             => 'GET',
        'callback'            => 'snel_newsletter_get_tags',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );

    // POST /subscribers/(?P<id>\d+)/tags — add tags to a subscriber.
    register_rest_route( $namespace, '/subscribers/(?P<id>\d+)/tags', array(
        'methods'             => 'POST',
        'callback'            => 'snel_newsletter_add_subscriber_tags',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );

    // POST /subscribers/bulk-tag — add tag to multiple subscribers.
    register_rest_route( $namespace, '/subscribers/bulk-tag', array(
        'methods'             => 'POST',
        'callback'            => 'snel_newsletter_bulk_tag',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );
} );

// ─── Subscribers ─────────────────────────────────────────────────────────────

/**
 * List subscribers with search, tag filter, status filter, and pagination.
 */
function snel_newsletter_get_subscribers( WP_REST_Request $request ) {
    global $wpdb;

    $table      = snel_newsletter_subscribers_table();
    $tags_table = snel_newsletter_tags_table();
    $page       = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
    $per_page   = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ?: 20 ) );
    $search     = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
    $tag        = sanitize_text_field( $request->get_param( 'tag' ) ?: '' );
    $status     = sanitize_text_field( $request->get_param( 'status' ) ?: '' );

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

    // Count total.
    $count_sql = "SELECT COUNT(DISTINCT s.id) FROM $table s $join WHERE $where_sql";
    $total     = $values ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : (int) $wpdb->get_var( $count_sql );

    // Get rows.
    $query = "SELECT DISTINCT s.* FROM $table s $join WHERE $where_sql ORDER BY s.created_at DESC LIMIT %d OFFSET %d";
    $args  = array_merge( $values, array( $per_page, $offset ) );
    $rows  = $wpdb->get_results( $wpdb->prepare( $query, $args ) );

    // Attach tags to each subscriber.
    if ( $rows ) {
        $ids        = wp_list_pluck( $rows, 'id' );
        $ids_in     = implode( ',', array_map( 'intval', $ids ) );
        $tag_rows   = $wpdb->get_results( "SELECT subscriber_id, tag FROM $tags_table WHERE subscriber_id IN ($ids_in)" );
        $tag_map    = array();
        foreach ( $tag_rows as $tr ) {
            $tag_map[ $tr->subscriber_id ][] = $tr->tag;
        }
        foreach ( $rows as &$row ) {
            $row->tags = $tag_map[ $row->id ] ?? array();
        }
    }

    // Counts by status.
    $counts = array(
        'total'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ),
        'active'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'active'" ),
        'unsubscribed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'unsubscribed'" ),
        'bounced'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'bounced'" ),
    );

    return rest_ensure_response( array(
        'subscribers' => $rows,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $per_page,
        'pages'       => ceil( $total / $per_page ),
        'counts'      => $counts,
    ) );
}

/**
 * Add a new subscriber.
 */
function snel_newsletter_add_subscriber( WP_REST_Request $request ) {
    global $wpdb;

    $params = $request->get_json_params();
    $email  = sanitize_email( $params['email'] ?? '' );
    $name   = sanitize_text_field( $params['name'] ?? '' );
    $tags   = array_map( 'sanitize_text_field', $params['tags'] ?? array() );

    if ( ! is_email( $email ) ) {
        return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'snel-newsletter' ), array( 'status' => 400 ) );
    }

    $table = snel_newsletter_subscribers_table();

    // Check for duplicate.
    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE email = %s", $email ) );
    if ( $exists ) {
        return new WP_Error( 'duplicate', __( 'This email is already subscribed.', 'snel-newsletter' ), array( 'status' => 409 ) );
    }

    $token = wp_generate_password( 32, false );

    $wpdb->insert( $table, array(
        'email'             => $email,
        'name'              => $name,
        'status'            => 'active',
        'unsubscribe_token' => $token,
    ), array( '%s', '%s', '%s', '%s' ) );

    $subscriber_id = $wpdb->insert_id;

    // Add tags.
    if ( $tags && $subscriber_id ) {
        $tags_table = snel_newsletter_tags_table();
        foreach ( $tags as $tag ) {
            if ( $tag ) {
                $wpdb->insert( $tags_table, array(
                    'subscriber_id' => $subscriber_id,
                    'tag'           => $tag,
                ), array( '%d', '%s' ) );
            }
        }
    }

    return rest_ensure_response( array( 'success' => true, 'id' => $subscriber_id ) );
}

/**
 * Update a subscriber (name, status, tags).
 */
function snel_newsletter_update_subscriber( WP_REST_Request $request ) {
    global $wpdb;

    $id     = (int) $request->get_param( 'id' );
    $params = $request->get_json_params();
    $table  = snel_newsletter_subscribers_table();

    $update = array();
    $format = array();

    if ( isset( $params['name'] ) ) {
        $update['name'] = sanitize_text_field( $params['name'] );
        $format[]       = '%s';
    }

    if ( isset( $params['status'] ) && in_array( $params['status'], array( 'active', 'unsubscribed', 'bounced' ), true ) ) {
        $update['status'] = $params['status'];
        $format[]         = '%s';
    }

    if ( $update ) {
        $wpdb->update( $table, $update, array( 'id' => $id ), $format, array( '%d' ) );
    }

    // Replace tags if provided.
    if ( isset( $params['tags'] ) && is_array( $params['tags'] ) ) {
        $tags_table = snel_newsletter_tags_table();
        $wpdb->delete( $tags_table, array( 'subscriber_id' => $id ), array( '%d' ) );
        foreach ( $params['tags'] as $tag ) {
            $tag = sanitize_text_field( $tag );
            if ( $tag ) {
                $wpdb->insert( $tags_table, array(
                    'subscriber_id' => $id,
                    'tag'           => $tag,
                ), array( '%d', '%s' ) );
            }
        }
    }

    return rest_ensure_response( array( 'success' => true ) );
}

/**
 * Delete a single subscriber.
 */
function snel_newsletter_delete_subscriber( WP_REST_Request $request ) {
    global $wpdb;

    $id = (int) $request->get_param( 'id' );

    $table      = snel_newsletter_subscribers_table();
    $tags_table = snel_newsletter_tags_table();

    $wpdb->delete( $tags_table, array( 'subscriber_id' => $id ), array( '%d' ) );
    $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

    return rest_ensure_response( array( 'success' => true ) );
}

/**
 * Bulk delete subscribers.
 */
function snel_newsletter_bulk_delete_subscribers( WP_REST_Request $request ) {
    global $wpdb;

    $params = $request->get_json_params();
    $ids    = array_map( 'intval', $params['ids'] ?? array() );

    if ( empty( $ids ) ) {
        return new WP_Error( 'no_ids', __( 'No subscriber IDs provided.', 'snel-newsletter' ), array( 'status' => 400 ) );
    }

    $table      = snel_newsletter_subscribers_table();
    $tags_table = snel_newsletter_tags_table();
    $ids_in     = implode( ',', $ids );

    $wpdb->query( "DELETE FROM $tags_table WHERE subscriber_id IN ($ids_in)" );
    $wpdb->query( "DELETE FROM $table WHERE id IN ($ids_in)" );

    return rest_ensure_response( array( 'success' => true, 'deleted' => count( $ids ) ) );
}

/**
 * Get all unique tags with counts.
 */
function snel_newsletter_get_tags() {
    global $wpdb;

    $tags_table = snel_newsletter_tags_table();
    $rows = $wpdb->get_results( "SELECT tag, COUNT(*) as count FROM $tags_table GROUP BY tag ORDER BY tag ASC" );

    return rest_ensure_response( $rows ?: array() );
}

/**
 * Add tags to a subscriber.
 */
function snel_newsletter_add_subscriber_tags( WP_REST_Request $request ) {
    global $wpdb;

    $id   = (int) $request->get_param( 'id' );
    $params = $request->get_json_params();
    $tags = array_map( 'sanitize_text_field', $params['tags'] ?? array() );

    $tags_table = snel_newsletter_tags_table();

    foreach ( $tags as $tag ) {
        if ( $tag ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO $tags_table (subscriber_id, tag) VALUES (%d, %s)",
                $id, $tag
            ) );
        }
    }

    return rest_ensure_response( array( 'success' => true ) );
}

/**
 * Bulk add tag to multiple subscribers.
 */
function snel_newsletter_bulk_tag( WP_REST_Request $request ) {
    global $wpdb;

    $params = $request->get_json_params();
    $ids    = array_map( 'intval', $params['ids'] ?? array() );
    $tag    = sanitize_text_field( $params['tag'] ?? '' );

    if ( empty( $ids ) || ! $tag ) {
        return new WP_Error( 'invalid', __( 'IDs and tag required.', 'snel-newsletter' ), array( 'status' => 400 ) );
    }

    $tags_table = snel_newsletter_tags_table();

    foreach ( $ids as $id ) {
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO $tags_table (subscriber_id, tag) VALUES (%d, %s)",
            $id, $tag
        ) );
    }

    return rest_ensure_response( array( 'success' => true ) );
}

// ─── Settings ────────────────────────────────────────────────────────────────

/**
 * Get newsletter settings.
 */
function snel_newsletter_get_settings() {
    $settings = get_option( 'snel_newsletter_settings', array() );

    // Mask the secret key for frontend display.
    $masked = $settings;
    if ( ! empty( $masked['ses_secret_key'] ) ) {
        $key = $masked['ses_secret_key'];
        $masked['ses_secret_key'] = str_repeat( '*', max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 );
    }

    return rest_ensure_response( $masked );
}

/**
 * Save newsletter settings.
 */
function snel_newsletter_save_settings( WP_REST_Request $request ) {
    $params   = $request->get_json_params();
    $settings = get_option( 'snel_newsletter_settings', array() );

    $fields = array(
        'ses_access_key' => 'sanitize_text_field',
        'ses_region'     => 'sanitize_text_field',
        'from_name'      => 'sanitize_text_field',
        'from_email'     => 'sanitize_email',
        'reply_to'       => 'sanitize_email',
    );

    foreach ( $fields as $key => $sanitizer ) {
        if ( isset( $params[ $key ] ) ) {
            $settings[ $key ] = call_user_func( $sanitizer, $params[ $key ] );
        }
    }

    // Secret key: only update if not masked (contains actual key, not asterisks).
    if ( isset( $params['ses_secret_key'] ) && strpos( $params['ses_secret_key'], '*' ) === false ) {
        $settings['ses_secret_key'] = sanitize_text_field( $params['ses_secret_key'] );
    }

    update_option( 'snel_newsletter_settings', $settings );

    return rest_ensure_response( array( 'success' => true ) );
}
