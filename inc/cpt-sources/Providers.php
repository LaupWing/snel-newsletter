<?php
/**
 * Custom source providers.
 *
 * Anything that isn't a post type — a custom DB table, a form plugin, an
 * external list — registers itself through the `snel_newsletter_sources`
 * filter and becomes selectable alongside the scanned post types.
 *
 *   add_filter( 'snel_newsletter_sources', function ( $sources ) {
 *       $sources['my_leads'] = [
 *           'label'       => 'CTA Leads',
 *           'description' => 'Emails captured by download CTAs.',
 *           'count'       => fn() => 42,
 *           'rows'        => fn() => [
 *               [ 'email' => 'a@b.nl', 'name' => 'Anna', 'tags' => [ 'download' ] ],
 *           ],
 *       ];
 *       return $sources;
 *   } );
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\CptSources;

defined( 'ABSPATH' ) || exit;

class Providers {

	/**
	 * All registered custom sources, normalised.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
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

	/**
	 * One registered source, or null.
	 */
	public static function get( $id ) {
		$all = self::all();

		return $all[ $id ] ?? null;
	}

	/**
	 * Pull the rows from a provider, normalised to { email, name, tags }.
	 *
	 * @param string $id
	 * @return array[]
	 */
	public static function rows( $id ) {
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

	/**
	 * Shape a provider like a scanned post type so the UI can render both.
	 */
	public static function as_source( $provider ) {
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
