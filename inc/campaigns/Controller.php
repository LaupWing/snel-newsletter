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
     * Get campaign stats + subscriber send list.
     */
    public function stats( \WP_REST_Request $request ) {
        global $wpdb;

        $id       = (int) $request->get_param( 'id' );
        $campaign = Model::find( $id );

        if ( ! $campaign ) {
            return new \WP_Error( 'not_found', 'Campaign not found.', array( 'status' => 404 ) );
        }

        $queue    = $wpdb->prefix . 'snel_send_queue';
        $subs     = $wpdb->prefix . 'snel_subscribers';
        $tracking = $wpdb->prefix . 'snel_tracking';

        $failed = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $queue WHERE campaign_id = %d AND status = 'failed'",
            $id
        ) );

        $subscribers = $wpdb->get_results( $wpdb->prepare(
            "SELECT q.subscriber_id, q.status, q.sent_at, s.email, s.name,
                    MAX(CASE WHEN t.event = 'open'  THEN 1 ELSE 0 END) AS opened,
                    MAX(CASE WHEN t.event = 'click' THEN 1 ELSE 0 END) AS clicked
             FROM $queue q
             INNER JOIN $subs s ON s.id = q.subscriber_id
             LEFT JOIN $tracking t ON t.campaign_id = q.campaign_id AND t.subscriber_id = q.subscriber_id
             WHERE q.campaign_id = %d
             GROUP BY q.subscriber_id, q.status, q.sent_at, s.email, s.name
             ORDER BY q.id ASC
             LIMIT 50",
            $id
        ) );

        return rest_ensure_response( array_merge( $campaign, array(
            'failed'      => $failed,
            'subscribers' => $subscribers ?: array(),
        ) ) );
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
