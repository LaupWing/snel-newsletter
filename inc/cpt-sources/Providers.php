<?php

namespace Snel\Newsletter\CptSources;

defined( 'ABSPATH' ) || exit;

// Non-post-type sources (custom tables, form plugins, external lists) register via the
// `snel_newsletter_sources` filter: [ 'id' => [ label, description, count: fn, rows: fn ] ].
class Providers {

	public static function all(): array {
		$raw = apply_filters( 'snel_newsletter_sources', array() );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();

		foreach ( $raw as $id => $source ) {
			$id = sanitize_key( $id );

			if ( ! $id || empty( $source['rows'] ) || ! is_callable( $source['rows'] ) ) {
				continue;
			}

			$out[ $id ] = array(
				'id'          => $id,
				'label'       => $source['label'] ?? $id,
				'description' => $source['description'] ?? '',
				'count'       => isset( $source['count'] ) && is_callable( $source['count'] )
					? (int) call_user_func( $source['count'] )
					: null,
				'rows'        => $source['rows'],
			);
		}

		return $out;
	}

	public static function get( string $id ): ?array {
		$all = self::all();

		return $all[ $id ] ?? null;
	}

	public static function rows( string $id ): array {
		$source = self::get( $id );

		if ( ! $source ) {
			return array();
		}

		$rows = call_user_func( $source['rows'] );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();

		foreach ( $rows as $i => $row ) {
			$email = trim( (string) ( $row['email'] ?? '' ) );

			if ( '' === $email ) {
				continue;
			}

			$out[] = array(
				'id'    => $row['id'] ?? $i,
				'email' => $email,
				'name'  => (string) ( $row['name'] ?? '' ),
				'tags'  => Store::clean_tags( $row['tags'] ?? array() ),
			);
		}

		return $out;
	}

	// Shaped like a scanned post type so the UI can render both.
	public static function as_source( array $provider ): array {
		return array(
			'id'           => $provider['id'],
			'kind'         => 'custom',
			'post_type'    => null,
			'label'        => $provider['label'],
			'description'  => $provider['description'],
			'count'        => $provider['count'],
			'email_fields' => array(),
			'tag_fields'   => array(),
			'connectable'  => true,
		);
	}
}
