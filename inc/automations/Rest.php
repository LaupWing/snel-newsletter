<?php
/**
 * Automations REST route definitions.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Automations;

defined( 'ABSPATH' ) || exit;

class Rest {

    private $controller;
    private $namespace = 'snel-newsletter/v1';

    public function __construct() {
        $this->controller = new Controller();
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/automations', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'list' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/automations', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'create' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/automations/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'get' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/automations/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this->controller, 'update' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/automations/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this->controller, 'delete' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/automations/(?P<id>\d+)/enroll', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'enroll' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        // Node inspector — who passed through one step.
        register_rest_route( $this->namespace, '/automations/(?P<id>\d+)/step', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'step' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );
    }

    public function permission_check() {
        return current_user_can( 'manage_options' );
    }
}
