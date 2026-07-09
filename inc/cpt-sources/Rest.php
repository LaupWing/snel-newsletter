<?php
/**
 * CPT Sources REST route definitions.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\CptSources;

defined( 'ABSPATH' ) || exit;

class Rest {

	private $controller;
	private $namespace = 'snel-newsletter/v1';

	public function __construct() {
		$this->controller = new Controller();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/cpt-sources/scan', array(
			'methods'             => 'GET',
			'callback'            => array( $this->controller, 'scan' ),
			'permission_callback' => array( $this, 'permission_check' ),
		) );

		register_rest_route( $this->namespace, '/cpt-sources/preview', array(
			'methods'             => 'GET',
			'callback'            => array( $this->controller, 'preview' ),
			'permission_callback' => array( $this, 'permission_check' ),
		) );

		register_rest_route( $this->namespace, '/cpt-sources', array(
			'methods'             => 'POST',
			'callback'            => array( $this->controller, 'save' ),
			'permission_callback' => array( $this, 'permission_check' ),
		) );

		register_rest_route( $this->namespace, '/cpt-sources/(?P<id>[\w-]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( $this->controller, 'delete' ),
			'permission_callback' => array( $this, 'permission_check' ),
		) );

		register_rest_route( $this->namespace, '/cpt-sources/(?P<id>[\w-]+)/import', array(
			'methods'             => 'POST',
			'callback'            => array( $this->controller, 'import' ),
			'permission_callback' => array( $this, 'permission_check' ),
		) );
	}

	public function permission_check() {
		return current_user_can( 'manage_options' );
	}
}
