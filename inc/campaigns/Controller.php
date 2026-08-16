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
            'type'     => sanitize_text_field( $request->get_param( 'type' ) ?: '' ),
        ) );

        $result['counts'] = Model::counts();

        return rest_ensure_response( $result );
    }

    /**
     * Aggregate stats + recent campaigns for the dashboard overview.
     */
    public function dashboard( \WP_REST_Request $request ) {
        $subscribers = \Snel\Newsletter\Subscribers\Model::counts();
        $campaigns   = Model::counts();
        $performance = Model::performance();
        $recent      = Model::list( array( 'per_page' => 5 ) );

        $recent_campaigns = array_map( function ( $c ) {
            $recipients = (int) $c['recipients'];
            return array(
                'id'         => $c['id'],
                'subject'    => $c['subject'],
                'status'     => $c['status'],
                'recipients' => $recipients,
                'open_rate'  => $recipients > 0 ? (int) round( (int) $c['opened'] / $recipients * 100 ) : 0,
                'click_rate' => $recipients > 0 ? (int) round( (int) $c['clicked'] / $recipients * 100 ) : 0,
                'sent_at'    => $c['sent_at'],
                'created_at' => $c['created_at'],
            );
        }, $recent['campaigns'] );

        return rest_ensure_response( array(
            'subscribers'     => (int) ( $subscribers['active'] ?? 0 ),
            'campaignsSent'   => (int) ( $campaigns['sent'] ?? 0 ),
            'avgOpenRate'     => $performance['avg_open_rate'],
            'avgClickRate'    => $performance['avg_click_rate'],
            'recentCampaigns' => $recent_campaigns,
        ) );
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
                    MAX(CASE WHEN t.type = 'open'  THEN 1 ELSE 0 END) AS opened,
                    MAX(CASE WHEN t.type = 'click' THEN 1 ELSE 0 END) AS clicked
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
     * Cancel a sending or scheduled campaign — halt every unsent queued email.
     */
    public function cancel( \WP_REST_Request $request ) {
        $id       = (int) $request->get_param( 'id' );
        $campaign = Model::find( $id );

        if ( ! $campaign ) {
            return new \WP_Error( 'not_found', 'Campaign not found.', array( 'status' => 404 ) );
        }

        if ( ! in_array( $campaign['status'], array( 'sending', 'scheduled' ), true ) ) {
            return new \WP_Error( 'not_cancellable', 'Only sending or scheduled campaigns can be cancelled.', array( 'status' => 400 ) );
        }

        $stopped = Model::cancel( $id );

        return rest_ensure_response( array( 'success' => true, 'cancelled' => $stopped ) );
    }

    /**
     * Resume a cancelled campaign — requeue its unsent emails (audience-checked)
     * and restart the drainer. Already-sent subscribers are never requeued.
     */
    public function resume( \WP_REST_Request $request ) {
        $id       = (int) $request->get_param( 'id' );
        $campaign = Model::find( $id );

        if ( ! $campaign ) {
            return new \WP_Error( 'not_found', 'Campaign not found.', array( 'status' => 404 ) );
        }

        if ( $campaign['status'] !== 'cancelled' ) {
            return new \WP_Error( 'not_resumable', 'Only cancelled campaigns can be resumed.', array( 'status' => 400 ) );
        }

        $resumed = \Snel\Newsletter\Queue\Processor::resume_campaign( $id );

        if ( $resumed === 0 ) {
            return new \WP_Error( 'nothing_to_resume', 'No unsent emails left to resume for this campaign.', array( 'status' => 400 ) );
        }

        return rest_ensure_response( array( 'success' => true, 'resumed' => $resumed ) );
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
