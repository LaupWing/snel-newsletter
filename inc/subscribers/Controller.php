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
        $result = Model::list( array(
            'page'     => $request->get_param( 'page' ),
            'per_page' => $request->get_param( 'per_page' ),
            'search'   => sanitize_text_field( $request->get_param( 'search' ) ?: '' ),
            'tag'      => sanitize_text_field( $request->get_param( 'tag' ) ?: '' ),
            'status'   => sanitize_text_field( $request->get_param( 'status' ) ?: '' ),
        ) );

        $result['counts'] = Model::counts();

        return rest_ensure_response( $result );
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
        $params     = $request->get_json_params();
        $rows       = $params['subscribers'] ?? array();
        $tags       = array_map( 'sanitize_text_field', $params['tags'] ?? array() );
        $imported   = 0;
        $skipped    = 0;

        foreach ( $rows as $row ) {
            $email = sanitize_email( $row['email'] ?? '' );
            $name  = sanitize_text_field( $row['name'] ?? '' );

            if ( ! is_email( $email ) ) {
                $skipped++;
                continue;
            }

            $id = Model::create( $email, $name );

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
     * Rename a tag globally.
     *
     * Body: { new_tag: string }
     */
    public function rename_tag( \WP_REST_Request $request ) {
        $old_tag = sanitize_text_field( $request->get_param( 'tag' ) );
        $params  = $request->get_json_params();
        $new_tag = sanitize_text_field( $params['new_tag'] ?? '' );

        if ( ! $old_tag || ! $new_tag ) {
            return new \WP_Error( 'invalid', 'Old and new tag names required.', array( 'status' => 400 ) );
        }

        Model::rename_tag_global( $old_tag, $new_tag );

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Delete a tag from all subscribers.
     */
    public function delete_tag( \WP_REST_Request $request ) {
        $tag = sanitize_text_field( $request->get_param( 'tag' ) );

        if ( ! $tag ) {
            return new \WP_Error( 'invalid', 'Tag name required.', array( 'status' => 400 ) );
        }

        Model::delete_tag_global( $tag );

        return rest_ensure_response( array( 'success' => true ) );
    }
}
