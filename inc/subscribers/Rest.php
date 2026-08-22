<?php

namespace Snel\Newsletter\Subscribers;

defined( 'ABSPATH' ) || exit;

// SOT:REST — admin routes are wired here only; every route carries a manage_options permission_callback.
class Rest {

    private $controller;
    private $namespace = 'snel-newsletter/v1';

    public function __construct() {
        $this->controller = new Controller();
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/subscribers', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'list' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/subscribers', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'create' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/subscribers/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this->controller, 'update' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/subscribers/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this->controller, 'delete' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/subscribers/bulk-delete', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'bulk_delete' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/subscribers/query-ids', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'query_ids' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/subscribers/(?P<id>\d+)/history', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'history' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/tags', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'tags' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/tags', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'create_tag' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/audience/count', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'audience_count' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/subscribers/(?P<id>\d+)/tags', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'add_tags' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/subscribers/bulk-tag', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'bulk_tag' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/tags/(?P<tag>[^/]+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this->controller, 'rename_tag' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/tags/(?P<tag>[^/]+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this->controller, 'delete_tag' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/tags/(?P<tag>[^/]+)/sync', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'sync_tag' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/subscribers/import', array(
            'methods'             => 'POST',
            'callback'            => array( $this->controller, 'import' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( $this->namespace, '/subscribers/emails', array(
            'methods'             => 'GET',
            'callback'            => array( $this->controller, 'existing_emails' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );
    }

    public function permission_check(): bool {
        return current_user_can( 'manage_options' );
    }
}
