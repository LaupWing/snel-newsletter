<?php
/**
 * Sender REST route definitions.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Sender;

defined( 'ABSPATH' ) || exit;

class Rest {

    private $controller;
    private $namespace = 'snel-newsletter/v1';

    public function __construct() {
        $this->controller = new Controller();
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/campaigns/(?P<id>\d+)/preview', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'preview' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/campaigns/(?P<id>\d+)/send-test', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'send_test' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );
    }

    public function permission_check() {
        return current_user_can( 'manage_options' );
    }
}
