<?php

namespace Snel\Newsletter\CptSources;

defined( 'ABSPATH' ) || exit;

class Controller {

	public function scan(): \WP_REST_Response {
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

	public function preview( \WP_REST_Request $request ) {
		$id = sanitize_key( $request->get_param( 'id' ) ?: $request->get_param( 'post_type' ) );

		if ( ! $id ) {
			return new \WP_Error( 'missing_id', 'A source id is required.', array( 'status' => 400 ) );
		}

		$manual_tags = Store::clean_tags( (string) $request->get_param( 'manual_tags' ) );

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

	// Returns array( $id, $config ) or a WP_Error.
	private function config_from_request( \WP_REST_Request $request ) {
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

	public function save( \WP_REST_Request $request ) {
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

	public function delete( \WP_REST_Request $request ): \WP_REST_Response {
		Store::delete( sanitize_key( $request->get_param( 'id' ) ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function import( \WP_REST_Request $request ) {
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
