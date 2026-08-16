<?php
/**
 * Campaign REST route definitions.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Campaigns;

defined( 'ABSPATH' ) || exit;

class Rest {

    private $controller;
    private $namespace = 'snel-newsletter/v1';

    public function __construct() {
        $this->controller = new Controller();
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/dashboard', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'dashboard' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/campaigns', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'list' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/campaigns/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'get' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/campaigns/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this->controller, 'delete' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/campaigns/(?P<id>\d+)/duplicate', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'duplicate' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/campaigns/(?P<id>\d+)/cancel', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'cancel' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/campaigns/(?P<id>\d+)/resume', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'resume' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/campaigns/(?P<id>\d+)/stats', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'stats' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );
    }

    public function permission_check() {
        return current_user_can( 'manage_options' );
    }
}
