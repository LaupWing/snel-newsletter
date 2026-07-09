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
	 * Scanned post types + registered custom providers, each merged with its
	 * saved config (if any).
	 */
	public function scan() {
		$sources = Scanner::scan();

		foreach ( Providers::all() as $provider ) {
			$sources[] = Providers::as_source( $provider );
		}

		$saved = Store::all();

		foreach ( $sources as &$source ) {
			$source['config'] = isset( $saved[ $source['id'] ] )
				? wp_parse_args( $saved[ $source['id'] ], Store::defaults() )
				: null;
		}
		unset( $source );

		// Connectable first, then custom providers above empty post types.
		usort( $sources, function ( $a, $b ) {
			if ( $a['connectable'] !== $b['connectable'] ) {
				return $b['connectable'] <=> $a['connectable'];
			}
			return (int) $b['count'] <=> (int) $a['count'];
		} );

		return rest_ensure_response( $sources );
	}

	/**
	 * GET /cpt-sources/preview
	 */
	public function preview( $request ) {
		$id = sanitize_key( $request->get_param( 'id' ) ?: $request->get_param( 'post_type' ) );

		if ( ! $id ) {
			return new \WP_Error( 'missing_id', 'A source id is required.', array( 'status' => 400 ) );
		}

		$manual_tags = Store::clean_tags( (string) $request->get_param( 'manual_tags' ) );

		// Custom provider.
		if ( Providers::get( $id ) ) {
			$rows = array();
			foreach ( Providers::rows( $id ) as $row ) {
				$rows[] = array(
					'id'    => $row['id'],
					'title' => $row['name'],
					'email' => $row['email'],
					'tags'  => $row['tags'],
				);
			}

			return rest_ensure_response( Scanner::preview_rows( $rows, $manual_tags ) );
		}

		// Post type.
		if ( ! post_type_exists( $id ) ) {
			return new \WP_Error( 'invalid_source', 'Unknown source.', array( 'status' => 400 ) );
		}

		$email_field = sanitize_text_field( (string) $request->get_param( 'email_field' ) );

		if ( ! $email_field ) {
			return new \WP_Error( 'missing_email_field', 'An email field is required.', array( 'status' => 400 ) );
		}

		$tag_field  = sanitize_text_field( (string) $request->get_param( 'tag_field' ) );
		$tag_source = 'taxonomy' === $request->get_param( 'tag_source' ) ? 'taxonomy' : 'meta';

		return rest_ensure_response(
			Scanner::preview( $id, $email_field, $tag_field, $tag_source, $manual_tags )
		);
	}

	/**
	 * Build a Store config from the request, or a WP_Error.
	 *
	 * @return array|\WP_Error
	 */
	private function config_from_request( $request ) {
		$id = sanitize_key( $request->get_param( 'id' ) ?: $request->get_param( 'post_type' ) );

		if ( ! $id ) {
			return new \WP_Error( 'missing_id', 'A source id is required.', array( 'status' => 400 ) );
		}

		$is_custom = (bool) Providers::get( $id );

		if ( ! $is_custom && ! post_type_exists( $id ) ) {
			return new \WP_Error( 'invalid_source', 'Unknown source.', array( 'status' => 400 ) );
		}

		$config = array(
			'kind'        => $is_custom ? 'custom' : 'cpt',
			'manual_tags' => $request->get_param( 'manual_tags' ),
			'auto_sync'   => (bool) $request->get_param( 'auto_sync' ),
		);

		if ( ! $is_custom ) {
			$email_field = sanitize_text_field( (string) $request->get_param( 'email_field' ) );

			if ( ! $email_field ) {
				return new \WP_Error( 'missing_email_field', 'An email field is required.', array( 'status' => 400 ) );
			}

			$config['email_field'] = $email_field;
			$config['tag_field']   = sanitize_text_field( (string) $request->get_param( 'tag_field' ) );
			$config['tag_source']  = $request->get_param( 'tag_source' );
		}

		return array( $id, $config );
	}

	/**
	 * POST /cpt-sources — save (or update) a source config.
	 */
	public function save( $request ) {
		$parsed = $this->config_from_request( $request );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		list( $id, $config ) = $parsed;

		return rest_ensure_response( array(
			'success' => true,
			'config'  => Store::save( $id, $config ),
		) );
	}

	/**
	 * DELETE /cpt-sources/(?P<id>[\w-]+)
	 */
	public function delete( $request ) {
		Store::delete( sanitize_key( $request->get_param( 'id' ) ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * POST /cpt-sources/(?P<id>[\w-]+)/import
	 *
	 * Saves the posted config, then runs a full import.
	 */
	public function import( $request ) {
		$parsed = $this->config_from_request( $request );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		list( $id, $config ) = $parsed;

		$stored = Store::save( $id, $config );
		$result = Importer::run( $stored );
		Store::record_sync( $id, $result );

		return rest_ensure_response( array(
			'success' => true,
			'result'  => $result,
			'config'  => Store::get( $id ),
		) );
	}
}
