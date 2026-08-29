<?php

namespace Snel\Newsletter\Settings;

defined( 'ABSPATH' ) || exit;

class Rest {

    private Controller $controller;
    private string $namespace = 'snel-newsletter/v1';

    public function __construct() {
        $this->controller = new Controller();
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/settings', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'get' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/settings', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'save' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/settings/test-email', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'test_email' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/logs', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'get_logs' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/logs/download', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'download_logs' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );
    }

    public function permission_check(): bool {
        return current_user_can( 'manage_options' );
    }
}
