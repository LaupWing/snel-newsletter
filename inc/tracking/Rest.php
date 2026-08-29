<?php

namespace Snel\Newsletter\Tracking;

defined( 'ABSPATH' ) || exit;

// Every route here is deliberately public (no auth): email clients load the
// pixel and subscribers click links without a WP session.
class Rest {

    private Controller $controller;
    private string $namespace = 'snel-newsletter/v1';

    public function __construct() {
        $this->controller = new Controller();
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/t/open', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'open' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $this->namespace, '/t/click', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'click' ),
            'permission_callback' => '__return_true',
        ) );

        // GET is the human footer link; POST is Gmail/Yahoo one-click
        // (List-Unsubscribe-Post) and must be accepted or people report spam.
        register_rest_route( $this->namespace, '/t/unsubscribe', array(
            'methods'             => array( 'GET', 'POST' ),
            'callback'            => array( $this->controller, 'unsubscribe' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $this->namespace, '/webhook/(?P<slug>[a-z]+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'webhook' ),
            'permission_callback' => '__return_true',
        ) );
    }
}
