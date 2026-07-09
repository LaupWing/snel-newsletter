<?php
/**
 * CPT Sources — REST business logic.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\CptSources;

defined( 'ABSPATH' ) || exit;

class Controller {

	/**
	 * GET /cpt-sources/scan
	 *
	 * Discovered post types, each merged with its saved config (if any).
	 */
	public function scan() {
		$sources = Scanner::scan();
		$saved   = Store::all();

		foreach ( $sources as &$source ) {
			$source['config'] = isset( $saved[ $source['post_type'] ] )
				? wp_parse_args( $saved[ $source['post_type'] ], Store::defaults() )
				: null;
		}

		return rest_ensure_response( $sources );
	}

	/**
	 * GET /cpt-sources/preview
	 */
	public function preview( $request ) {
		$post_type = sanitize_key( $request->get_param( 'post_type' ) );

		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			return new \WP_Error( 'invalid_post_type', 'Unknown post type.', array( 'status' => 400 ) );
		}

		$email_field = sanitize_text_field( (string) $request->get_param( 'email_field' ) );

		if ( ! $email_field ) {
			return new \WP_Error( 'missing_email_field', 'An email field is required.', array( 'status' => 400 ) );
		}

		$tag_field   = sanitize_text_field( (string) $request->get_param( 'tag_field' ) );
		$tag_source  = 'taxonomy' === $request->get_param( 'tag_source' ) ? 'taxonomy' : 'meta';
		$manual_tags = Store::clean_tags( (string) $request->get_param( 'manual_tags' ) );

		$preview = Scanner::preview( $post_type, $email_field, $tag_field, $tag_source );

		// Manual tags apply to every row — show them in the preview.
		if ( $manual_tags ) {
			foreach ( $preview['rows'] as &$row ) {
				$row['tags'] = array_values( array_unique( array_merge( $manual_tags, $row['tags'] ) ) );
			}
		}

		return rest_ensure_response( $preview );
	}

	/**
	 * POST /cpt-sources
	 *
	 * Save (or update) a source config.
	 */
	public function save( $request ) {
		$post_type = sanitize_key( $request->get_param( 'post_type' ) );

		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			return new \WP_Error( 'invalid_post_type', 'Unknown post type.', array( 'status' => 400 ) );
		}

		$email_field = sanitize_text_field( (string) $request->get_param( 'email_field' ) );

		if ( ! $email_field ) {
			return new \WP_Error( 'missing_email_field', 'An email field is required.', array( 'status' => 400 ) );
		}

		$config = Store::save( $post_type, array(
			'email_field' => $email_field,
			'tag_field'   => sanitize_text_field( (string) $request->get_param( 'tag_field' ) ),
			'tag_source'  => $request->get_param( 'tag_source' ),
			'manual_tags' => $request->get_param( 'manual_tags' ),
			'auto_sync'   => (bool) $request->get_param( 'auto_sync' ),
		) );

		return rest_ensure_response( array( 'success' => true, 'config' => $config ) );
	}

	/**
	 * DELETE /cpt-sources/(?P<post_type>[\w-]+)
	 */
	public function delete( $request ) {
		Store::delete( sanitize_key( $request->get_param( 'post_type' ) ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * POST /cpt-sources/(?P<post_type>[\w-]+)/import
	 *
	 * Saves the posted config, then runs a full import.
	 */
	public function import( $request ) {
		$post_type = sanitize_key( $request->get_param( 'post_type' ) );

		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			return new \WP_Error( 'invalid_post_type', 'Unknown post type.', array( 'status' => 400 ) );
		}

		$email_field = sanitize_text_field( (string) $request->get_param( 'email_field' ) );

		if ( ! $email_field ) {
			return new \WP_Error( 'missing_email_field', 'An email field is required.', array( 'status' => 400 ) );
		}

		$config = Store::save( $post_type, array(
			'email_field' => $email_field,
			'tag_field'   => sanitize_text_field( (string) $request->get_param( 'tag_field' ) ),
			'tag_source'  => $request->get_param( 'tag_source' ),
			'manual_tags' => $request->get_param( 'manual_tags' ),
			'auto_sync'   => (bool) $request->get_param( 'auto_sync' ),
		) );

		$result = Importer::run( $config );
		Store::record_sync( $post_type, $result );

		return rest_ensure_response( array(
			'success' => true,
			'result'  => $result,
			'config'  => Store::get( $post_type ),
		) );
	}
}
