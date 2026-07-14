<?php
/**
 * Automations REST controller.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Automations;

defined( 'ABSPATH' ) || exit;

class Controller {

    public function list( \WP_REST_Request $request ) {
        return rest_ensure_response( Model::all() );
    }

    public function get( \WP_REST_Request $request ) {
        $automation = Model::get( (int) $request->get_param( 'id' ) );
        if ( ! $automation ) {
            return new \WP_Error( 'not_found', 'Automation not found', array( 'status' => 404 ) );
        }

        $automation = array_merge( $automation, Model::run_counts( $automation['id'] ) );
        $automation['email_stats'] = Model::email_stats( $automation );

        return rest_ensure_response( $automation );
    }

    public function create( \WP_REST_Request $request ) {
        $name = sanitize_text_field( $request->get_param( 'name' ) ?: '' );
        if ( ! $name ) {
            return new \WP_Error( 'invalid', 'Name is required', array( 'status' => 400 ) );
        }

        $id = Model::create( array(
            'name'         => $name,
            'status'       => 'paused',
            'trigger_type' => $this->sanitize_trigger_type( $request->get_param( 'trigger_type' ) ),
            'trigger_tag'  => sanitize_text_field( $request->get_param( 'trigger_tag' ) ?: '' ),
            'steps'        => $this->sanitize_steps( $request->get_param( 'steps' ) ),
        ) );

        return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
    }

    public function update( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        if ( ! Model::get( $id ) ) {
            return new \WP_Error( 'not_found', 'Automation not found', array( 'status' => 404 ) );
        }

        $data = array();
        if ( null !== $request->get_param( 'name' ) ) {
            $data['name'] = sanitize_text_field( $request->get_param( 'name' ) );
        }
        if ( null !== $request->get_param( 'status' ) ) {
            $data['status'] = $request->get_param( 'status' ) === 'active' ? 'active' : 'paused';
        }
        if ( null !== $request->get_param( 'trigger_type' ) ) {
            $data['trigger_type'] = $this->sanitize_trigger_type( $request->get_param( 'trigger_type' ) );
        }
        if ( null !== $request->get_param( 'trigger_tag' ) ) {
            $data['trigger_tag'] = sanitize_text_field( $request->get_param( 'trigger_tag' ) );
        }
        if ( null !== $request->get_param( 'steps' ) ) {
            $data['steps'] = $this->sanitize_steps( $request->get_param( 'steps' ) );
        }

        Model::update( $id, $data );

        // Activating may make waiting runs due again.
        if ( ( $data['status'] ?? '' ) === 'active' ) {
            Engine::ensure_scheduled();
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function delete( \WP_REST_Request $request ) {
        Model::delete( (int) $request->get_param( 'id' ) );
        return rest_ensure_response( array( 'success' => true ) );
    }

    public function enroll( \WP_REST_Request $request ) {
        $id  = (int) $request->get_param( 'id' );
        $ids = $request->get_param( 'subscriber_ids' );

        if ( ! Model::get( $id ) ) {
            return new \WP_Error( 'not_found', 'Automation not found', array( 'status' => 404 ) );
        }
        if ( ! is_array( $ids ) || ! $ids ) {
            return new \WP_Error( 'invalid', 'subscriber_ids is required', array( 'status' => 400 ) );
        }

        $enrolled = Engine::enroll( $id, $ids );

        return rest_ensure_response( array( 'success' => true, 'enrolled' => $enrolled ) );
    }

    /**
     * Node inspector — the subscribers who passed through one step.
     * `path` is the step's JSON path ("[2]", "[2,\"yes\",0]") or "trigger".
     */
    public function step( \WP_REST_Request $request ) {
        $id   = (int) $request->get_param( 'id' );
        $path = (string) $request->get_param( 'path' );

        if ( ! Model::get( $id ) ) {
            return new \WP_Error( 'not_found', 'Automation not found', array( 'status' => 404 ) );
        }
        if ( 'trigger' !== $path && ! is_array( json_decode( $path, true ) ) ) {
            return new \WP_Error( 'invalid', 'path must be a JSON step path or "trigger"', array( 'status' => 400 ) );
        }

        return rest_ensure_response( Model::step_subscribers( $id, $path ) );
    }

    private function sanitize_trigger_type( $type ) {
        return in_array( $type, array( 'tag', 'manual' ), true ) ? $type : 'tag';
    }

    /**
     * Whitelist step types and their fields. Conditions only at root level.
     */
    private function sanitize_steps( $steps, $allow_condition = true ) {
        $clean = array();

        foreach ( (array) $steps as $step ) {
            if ( ! is_array( $step ) ) {
                continue;
            }

            switch ( $step['type'] ?? '' ) {
                case 'email':
                    $clean[] = array( 'type' => 'email', 'campaign_id' => (int) ( $step['campaign_id'] ?? 0 ) );
                    break;
                case 'wait':
                    $clean[] = array(
                        'type'  => 'wait',
                        'days'  => max( 0, (int) ( $step['days'] ?? 0 ) ),
                        'hours' => max( 0, (int) ( $step['hours'] ?? 0 ) ),
                    );
                    break;
                case 'label':
                    $clean[] = array( 'type' => 'label', 'tag' => sanitize_text_field( $step['tag'] ?? '' ) );
                    break;
                case 'condition':
                    if ( $allow_condition ) {
                        $mode    = in_array( $step['mode'] ?? '', array( 'opened', 'open_rate' ), true ) ? $step['mode'] : 'opened';
                        $clean[] = array(
                            'type'      => 'condition',
                            'mode'      => $mode,
                            'threshold' => min( 100, max( 0, (float) ( $step['threshold'] ?? 50 ) ) ),
                            'yes'       => $this->sanitize_steps( $step['yes'] ?? array(), false ),
                            'no'        => $this->sanitize_steps( $step['no'] ?? array(), false ),
                        );
                    }
                    break;
            }
        }

        return $clean;
    }
}
