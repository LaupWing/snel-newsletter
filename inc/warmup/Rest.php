<?php
namespace Snel\Newsletter\Warmup;

defined( 'ABSPATH' ) || exit;

class Rest {

    private $namespace = 'snel-newsletter/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/warmup', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'status' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/warmup/enable', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'enable' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/warmup/disable', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'disable' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/warmup/restart', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'restart' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );
    }

    public function status(): array {
        $out = array();
        foreach ( Settings::lanes() as $lane ) {
            $enabled = Settings::is_enabled( $lane );
            $day     = Settings::current_day( $lane );
            $cap     = Ramp::cap_for_day( $day );
            $sent    = $enabled ? Guard::sent_today( $lane ) : 0;

            $out[ $lane ] = array(
                'enabled'    => $enabled,
                'started_at' => Settings::started_at( $lane ),
                'day'        => $day,
                'cap_today'  => $cap,
                'sent_today' => $sent,
                'remaining'  => $cap !== null ? max( 0, $cap - $sent ) : null,
            );
        }

        return rest_ensure_response( $out );
    }

    private function lane_param( \WP_REST_Request $request ): string {
        $lane = sanitize_text_field( (string) ( $request->get_param( 'lane' ) ?? '' ) );
        return in_array( $lane, Settings::lanes(), true ) ? $lane : Settings::LANE_BROADCAST;
    }

    public function enable( \WP_REST_Request $request ) {
        $lane = $this->lane_param( $request );
        Settings::enable( $lane );

        \Snel\Newsletter\Logger\Logger::info( 'warmup', 'Warmup enabled', array(
            'lane'       => $lane,
            'day'        => Settings::current_day( $lane ),
            'started_at' => Settings::started_at( $lane ),
        ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function disable( \WP_REST_Request $request ) {
        $lane = $this->lane_param( $request );
        Settings::disable( $lane );

        \Snel\Newsletter\Logger\Logger::info( 'warmup', 'Warmup disabled', array( 'lane' => $lane ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function restart( \WP_REST_Request $request ) {
        $lane = $this->lane_param( $request );
        Settings::reset_ramp( $lane );

        \Snel\Newsletter\Logger\Logger::info( 'warmup', 'Warmup ramp restarted at day 1', array(
            'lane'       => $lane,
            'started_at' => Settings::started_at( $lane ),
        ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function permission_check() {
        return current_user_can( 'manage_options' );
    }
}
