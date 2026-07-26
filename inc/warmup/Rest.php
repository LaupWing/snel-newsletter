<?php
/**
 * Warmup REST routes.
 *
 * @package SnelNewsletter
 */

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

    public function status() {
        $enabled = Settings::is_enabled();
        $day     = Settings::current_day();
        $cap     = Ramp::cap_for_day( $day );
        $sent    = $enabled ? (int) get_option( Guard::OPT_DAILY_SENT, 0 ) : 0;

        return rest_ensure_response( array(
            'enabled'     => $enabled,
            'started_at'  => Settings::started_at(),
            'day'         => $day,
            'cap_today'   => $cap,
            'sent_today'  => $sent,
            'remaining'   => $cap !== null ? max( 0, $cap - $sent ) : null,
        ) );
    }

    public function enable() {
        Settings::enable();

        \Snel\Newsletter\Logger\Logger::info( 'warmup', 'Warmup enabled', array(
            'day'        => Settings::current_day(),
            'cap'        => Ramp::cap_for_day( Settings::current_day() ),
            'started_at' => Settings::started_at(),
        ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function disable() {
        Settings::disable();

        \Snel\Newsletter\Logger\Logger::info( 'warmup', 'Warmup disabled' );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function restart() {
        Settings::reset_ramp();

        \Snel\Newsletter\Logger\Logger::info( 'warmup', 'Warmup ramp restarted at day 1', array(
            'started_at' => Settings::started_at(),
        ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function permission_check() {
        return current_user_can( 'manage_options' );
    }
}
