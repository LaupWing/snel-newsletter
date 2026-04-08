<?php
/**
 * Tracking REST route definitions.
 *
 * These endpoints are PUBLIC (no auth) — they're hit by email clients
 * loading images (open pixel) and subscribers clicking links.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Tracking;

defined( 'ABSPATH' ) || exit;

class Rest {

    private $controller;
    private $namespace = 'snel-newsletter/v1';

    public function __construct() {
        $this->controller = new Controller();
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // Open pixel — public, no auth.
        register_rest_route( $this->namespace, '/t/open', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'open' ),
            'permission_callback' => '__return_true',
        ) );

        // Click redirect — public, no auth.
        register_rest_route( $this->namespace, '/t/click', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'click' ),
            'permission_callback' => '__return_true',
        ) );

        // Unsubscribe — public, no auth.
        register_rest_route( $this->namespace, '/t/unsubscribe', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'unsubscribe' ),
            'permission_callback' => '__return_true',
        ) );

        // Webhook — public, no auth (SES/SendGrid POSTs here).
        register_rest_route( $this->namespace, '/webhook/(?P<slug>[a-z]+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'webhook' ),
            'permission_callback' => '__return_true',
        ) );
    }
}
