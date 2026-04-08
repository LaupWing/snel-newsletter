<?php
/**
 * Campaign business logic.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Campaigns;

defined( 'ABSPATH' ) || exit;

class Controller {

    /**
     * List campaigns.
     */
    public function list( \WP_REST_Request $request ) {
        $result = Model::list( array(
            'page'     => $request->get_param( 'page' ),
            'per_page' => $request->get_param( 'per_page' ),
            'search'   => sanitize_text_field( $request->get_param( 'search' ) ?: '' ),
            'status'   => sanitize_text_field( $request->get_param( 'status' ) ?: '' ),
        ) );

        $result['counts'] = Model::counts();

        return rest_ensure_response( $result );
    }

    /**
     * Get a single campaign.
     */
    public function get( \WP_REST_Request $request ) {
        $id       = (int) $request->get_param( 'id' );
        $campaign = Model::find( $id );

        if ( ! $campaign ) {
            return new \WP_Error( 'not_found', 'Campaign not found.', array( 'status' => 404 ) );
        }

        return rest_ensure_response( $campaign );
    }

    /**
     * Delete a campaign.
     */
    public function delete( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        Model::delete( $id );

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Duplicate a campaign.
     */
    public function duplicate( \WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $new_id = Model::duplicate( $id );

        if ( ! $new_id ) {
            return new \WP_Error( 'duplicate_failed', 'Could not duplicate campaign.', array( 'status' => 500 ) );
        }

        return rest_ensure_response( array( 'success' => true, 'id' => $new_id ) );
    }
}
