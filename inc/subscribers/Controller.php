<?php

namespace Snel\Newsletter\Subscribers;

defined( 'ABSPATH' ) || exit;

// SOT:CONTROLLER — read request, validate, call Model, return response. No SQL here.
class Controller {

    public function list( \WP_REST_Request $request ) {
        $filters = self::parse_filters( $request );

        if ( ! empty( $filters ) ) {
            $result = Model::query(
                $filters,
                $request->get_param( 'page' ),
                $request->get_param( 'per_page' )
            );
        } else {
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

    // Backs "select all N matching" so bulk actions hit the whole filtered set, not one page.
    public function query_ids( \WP_REST_Request $request ) {
        $filters = self::parse_filters( $request );
        $ids     = Model::ids_for_filters( $filters );

        return rest_ensure_response( array( 'ids' => $ids, 'total' => count( $ids ) ) );
    }

    public function history( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        return rest_ensure_response( array( 'history' => Model::history( $id ) ) );
    }

    // `filters` may arrive as a JSON string (query) or a decoded array (body).
    private static function parse_filters( \WP_REST_Request $request ): array {
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

    public function delete( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        Model::delete( $id );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function bulk_delete( \WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $ids    = array_map( 'intval', $params['ids'] ?? array() );

        if ( empty( $ids ) ) {
            return new \WP_Error( 'no_ids', 'No subscriber IDs provided.', array( 'status' => 400 ) );
        }

        $deleted = Model::bulk_delete( $ids );

        return rest_ensure_response( array( 'success' => true, 'deleted' => $deleted ) );
    }

    public function tags() {
        return rest_ensure_response( Model::all_tags() );
    }

    public function audience_count( \WP_REST_Request $request ) {
        $tags = array_filter( explode( ',', (string) $request->get_param( 'tags' ) ) );

        return rest_ensure_response( array(
            'count' => Model::count_for_tags( $tags ),
        ) );
    }

    public function add_tags( \WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $params = $request->get_json_params();
        $tags   = array_map( 'sanitize_text_field', $params['tags'] ?? array() );

        Model::add_tags( $id, $tags );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function existing_emails() {
        return rest_ensure_response( Model::all_emails() );
    }

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

        // A dynamic tag is useless until it has matched someone, so sync immediately.
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

        $renamed = null;
        if ( $new_tag && $new_tag !== $old_tag ) {
            $renamed = Model::rename_tag_global( $old_tag, $new_tag );
        }

        $target_tag = ( $new_tag && $new_tag !== $old_tag ) ? $new_tag : $old_tag;

        // Always upsert so static tags also get a rule row with type=static.
        $metric    = $type === 'dynamic' ? sanitize_text_field( $params['metric'] ?? '' ) : null;
        $operator  = $type === 'dynamic' ? sanitize_text_field( $params['operator'] ?? '' ) : null;
        $threshold = $type === 'dynamic' && isset( $params['threshold'] ) ? (float) $params['threshold'] : null;

        Model::save_tag_rule( $target_tag, $type, $metric ?: null, $operator ?: null, $threshold );

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

    // WP hands route params through still percent-encoded ("download%3A%20Gids"), so decode first.
    private function tag_from_path( \WP_REST_Request $request ): string {
        return sanitize_text_field( rawurldecode( (string) $request->get_param( 'tag' ) ) );
    }

    public function delete_tag( \WP_REST_Request $request ) {
        $tag = $this->tag_from_path( $request );

        if ( ! $tag ) {
            return new \WP_Error( 'invalid', 'Tag name required.', array( 'status' => 400 ) );
        }

        Model::delete_tag_global( $tag );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function sync_tag( \WP_REST_Request $request ) {
        $tag = $this->tag_from_path( $request );

        if ( ! $tag ) {
            return new \WP_Error( 'invalid', 'Tag name required.', array( 'status' => 400 ) );
        }

        $count = Model::sync_dynamic_tag( $tag );

        return rest_ensure_response( array( 'success' => true, 'matched' => $count ) );
    }
}
