<?php
/**
 * Subscriber business logic.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Subscribers;

defined( 'ABSPATH' ) || exit;

class Controller {

    /**
     * List subscribers.
     */
    public function list( \WP_REST_Request $request ) {
        $filters = self::parse_filters( $request );

        if ( ! empty( $filters ) ) {
            // Advanced stacked filters (metrics, tags, status, search — all AND-ed).
            $result = Model::query(
                $filters,
                $request->get_param( 'page' ),
                $request->get_param( 'per_page' )
            );
        } else {
            // Legacy simple listing path.
            $result = Model::list( array(
                'page'     => $request->get_param( 'page' ),
                'per_page' => $request->get_param( 'per_page' ),
                'search'   => sanitize_text_field( $request->get_param( 'search' ) ?: '' ),
                'tag'      => sanitize_text_field( $request->get_param( 'tag' ) ?: '' ),
                'status'   => sanitize_text_field( $request->get_param( 'status' ) ?: '' ),
            ) );
        }

        $result['counts'] = Model::counts();

        return rest_ensure_response( $result );
    }

    /**
     * Every subscriber ID matching the current filter stack — for "select all
     * N matching" so a bulk action (delete/tag/enroll) can hit the whole set,
     * not just the visible page.
     *
     * Body: { filters: [ { field, operator, value }, ... ] }
     */
    public function query_ids( \WP_REST_Request $request ) {
        $filters = self::parse_filters( $request );
        $ids     = Model::ids_for_filters( $filters );

        return rest_ensure_response( array( 'ids' => $ids, 'total' => count( $ids ) ) );
    }

    /**
     * A subscriber's per-campaign send/open/click history.
     */
    public function history( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        return rest_ensure_response( array( 'history' => Model::history( $id ) ) );
    }

    /**
     * Pull the filter stack out of a request, from either a JSON `filters`
     * body/param or a `filters` query string, and sanitize each condition.
     */
    private static function parse_filters( \WP_REST_Request $request ) {
        $raw = $request->get_param( 'filters' );

        if ( is_string( $raw ) ) {
            $raw = json_decode( $raw, true );
        }

        if ( ! is_array( $raw ) ) {
            return array();
        }

        $clean = array();
        foreach ( $raw as $f ) {
            if ( ! is_array( $f ) || empty( $f['field'] ) ) {
                continue;
            }
            $clean[] = array(
                'field'    => sanitize_text_field( $f['field'] ),
                'operator' => sanitize_text_field( $f['operator'] ?? '' ),
                'value'    => is_scalar( $f['value'] ?? '' ) ? sanitize_text_field( (string) ( $f['value'] ?? '' ) ) : '',
            );
        }

        return $clean;
    }

    /**
     * Add a subscriber.
     */
    public function create( \WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $email  = sanitize_email( $params['email'] ?? '' );
        $name   = sanitize_text_field( $params['name'] ?? '' );
        $tags   = array_map( 'sanitize_text_field', $params['tags'] ?? array() );

        if ( ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', 'Invalid email address.', array( 'status' => 400 ) );
        }

        $id = Model::create( $email, $name );

        if ( ! $id ) {
            return new \WP_Error( 'duplicate', 'This email is already subscribed.', array( 'status' => 409 ) );
        }

        if ( $tags ) {
            Model::set_tags( $id, $tags );
        }

        return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
    }

    /**
     * Update a subscriber.
     */
    public function update( \WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $params = $request->get_json_params();

        $data = array();
        if ( isset( $params['name'] ) ) {
            $data['name'] = sanitize_text_field( $params['name'] );
        }
        if ( isset( $params['status'] ) ) {
            $data['status'] = sanitize_text_field( $params['status'] );
        }

        Model::update( $id, $data );

        if ( isset( $params['tags'] ) && is_array( $params['tags'] ) ) {
            $tags = array_map( 'sanitize_text_field', $params['tags'] );
            Model::set_tags( $id, $tags );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Delete a subscriber.
     */
    public function delete( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        Model::delete( $id );

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Bulk delete subscribers.
     */
    public function bulk_delete( \WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $ids    = array_map( 'intval', $params['ids'] ?? array() );

        if ( empty( $ids ) ) {
            return new \WP_Error( 'no_ids', 'No subscriber IDs provided.', array( 'status' => 400 ) );
        }

        $deleted = Model::bulk_delete( $ids );

        return rest_ensure_response( array( 'success' => true, 'deleted' => $deleted ) );
    }

    /**
     * Get all tags.
     */
    public function tags() {
        return rest_ensure_response( Model::all_tags() );
    }

    /**
     * Add tags to a subscriber.
     */
    public function add_tags( \WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $params = $request->get_json_params();
        $tags   = array_map( 'sanitize_text_field', $params['tags'] ?? array() );

        Model::add_tags( $id, $tags );

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Get all existing subscriber emails (for duplicate detection in import).
     */
    public function existing_emails() {
        return rest_ensure_response( Model::all_emails() );
    }

    /**
     * Bulk import subscribers.
     */
    public function import( \WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $rows     = $params['subscribers'] ?? array();
        $tags     = array_map( 'sanitize_text_field', $params['tags'] ?? array() );
        $status   = in_array( $params['status'] ?? 'active', array( 'active', 'inactive' ), true )
                    ? ( $params['status'] ?? 'active' )
                    : 'active';
        $imported = 0;
        $skipped  = 0;

        foreach ( $rows as $row ) {
            $email = sanitize_email( $row['email'] ?? '' );
            $name  = sanitize_text_field( $row['name'] ?? '' );

            if ( ! is_email( $email ) ) {
                $skipped++;
                continue;
            }

            $id = Model::create( $email, $name, $status );

            if ( ! $id ) {
                $skipped++;
                continue;
            }

            if ( $tags ) {
                Model::set_tags( $id, $tags );
            }

            $imported++;
        }

        return rest_ensure_response( array(
            'success'  => true,
            'imported' => $imported,
            'skipped'  => $skipped,
        ) );
    }

    /**
     * Bulk add/remove tags on multiple subscribers.
     *
     * Body: { ids: int[], add: string[], remove: string[] }
     */
    public function bulk_tag( \WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $ids    = array_map( 'intval', $params['ids'] ?? array() );
        $add    = array_map( 'sanitize_text_field', $params['add'] ?? array() );
        $remove = array_map( 'sanitize_text_field', $params['remove'] ?? array() );

        if ( empty( $ids ) ) {
            return new \WP_Error( 'invalid', 'IDs required.', array( 'status' => 400 ) );
        }

        foreach ( $add as $tag ) {
            if ( $tag ) {
                Model::bulk_add_tag( $ids, $tag );
            }
        }

        foreach ( $remove as $tag ) {
            if ( $tag ) {
                Model::bulk_remove_tag( $ids, $tag );
            }
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Create a tag before anyone carries it.
     *
     * Body: { tag: string, type: 'static'|'dynamic', metric?: string, operator?: string, threshold?: float }
     */
    public function create_tag( \WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $tag    = sanitize_text_field( $params['tag'] ?? '' );
        $type   = in_array( $params['type'] ?? 'static', array( 'static', 'dynamic' ), true )
                  ? $params['type']
                  : 'static';

        if ( ! $tag ) {
            return new \WP_Error( 'invalid', 'Tag name required.', array( 'status' => 400 ) );
        }

        $metric    = 'dynamic' === $type ? sanitize_text_field( $params['metric'] ?? '' ) : null;
        $operator  = 'dynamic' === $type ? sanitize_text_field( $params['operator'] ?? '' ) : null;
        $threshold = 'dynamic' === $type && isset( $params['threshold'] ) ? (float) $params['threshold'] : null;

        if ( ! Model::create_tag( $tag, $type, $metric ?: null, $operator ?: null, $threshold ) ) {
            return new \WP_Error( 'exists', 'That tag already exists.', array( 'status' => 409 ) );
        }

        // A dynamic tag is useless until it has matched someone — sync immediately.
        $synced = null;
        if ( 'dynamic' === $type && $metric && $operator && null !== $threshold ) {
            $synced = Model::sync_dynamic_tag( $tag );
        }

        return rest_ensure_response( array(
            'success' => true,
            'tag'     => $tag,
            'synced'  => $synced,
        ) );
    }

    /**
     * Update a tag — rename and/or set rule.
     *
     * Body: { new_tag?: string, type: 'static'|'dynamic', metric?: string, operator?: string, threshold?: float }
     */
    public function rename_tag( \WP_REST_Request $request ) {
        $old_tag = $this->tag_from_path( $request );
        $params  = $request->get_json_params();
        $new_tag = sanitize_text_field( $params['new_tag'] ?? $old_tag );
        $type    = in_array( $params['type'] ?? 'static', array( 'static', 'dynamic' ), true )
                   ? $params['type']
                   : 'static';

        if ( ! $old_tag ) {
            return new \WP_Error( 'invalid', 'Tag name required.', array( 'status' => 400 ) );
        }

        // Rename in subscriber_tags if the name changed.
        $renamed = null;
        if ( $new_tag && $new_tag !== $old_tag ) {
            $renamed = Model::rename_tag_global( $old_tag, $new_tag );
        }

        $target_tag = ( $new_tag && $new_tag !== $old_tag ) ? $new_tag : $old_tag;

        // Save rule (always upsert so static tags also get a row with type=static).
        $metric    = $type === 'dynamic' ? sanitize_text_field( $params['metric'] ?? '' ) : null;
        $operator  = $type === 'dynamic' ? sanitize_text_field( $params['operator'] ?? '' ) : null;
        $threshold = $type === 'dynamic' && isset( $params['threshold'] ) ? (float) $params['threshold'] : null;

        Model::save_tag_rule( $target_tag, $type, $metric ?: null, $operator ?: null, $threshold );

        // Auto-sync if dynamic.
        $synced = null;
        if ( $type === 'dynamic' && $metric && $operator && $threshold !== null ) {
            $synced = Model::sync_dynamic_tag( $target_tag );
        }

        return rest_ensure_response( array(
            'success' => true,
            'synced'  => $synced,
            'renamed' => $renamed,
            'tag'     => $target_tag,
        ) );
    }

    /**
     * Read a tag name out of the URL path.
     *
     * WP hands route params through still percent-encoded, so a tag like
     * "download: Gids 3.2" arrives as "download%3A%20Gids%203.2" and matches
     * nothing in the database. Decode before use.
     */
    private function tag_from_path( \WP_REST_Request $request ) {
        return sanitize_text_field( rawurldecode( (string) $request->get_param( 'tag' ) ) );
    }

    /**
     * Delete a tag from all subscribers.
     */
    public function delete_tag( \WP_REST_Request $request ) {
        $tag = $this->tag_from_path( $request );

        if ( ! $tag ) {
            return new \WP_Error( 'invalid', 'Tag name required.', array( 'status' => 400 ) );
        }

        Model::delete_tag_global( $tag );

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Manually sync a dynamic tag.
     */
    public function sync_tag( \WP_REST_Request $request ) {
        $tag = $this->tag_from_path( $request );

        if ( ! $tag ) {
            return new \WP_Error( 'invalid', 'Tag name required.', array( 'status' => 400 ) );
        }

        $count = Model::sync_dynamic_tag( $tag );

        return rest_ensure_response( array( 'success' => true, 'matched' => $count ) );
    }
}
