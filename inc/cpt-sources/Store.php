<?php
/**
 * CPT Sources — saved source configurations.
 *
 * One config per post type, stored in a single option.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\CptSources;

defined( 'ABSPATH' ) || exit;

class Store {

	const OPTION = 'snel_newsletter_cpt_sources';

	/**
	 * Default shape of a source config.
	 */
	public static function defaults() {
		return array(
			'post_type'   => '',
			'email_field' => '',
			'tag_field'   => '',
			'tag_source'  => 'meta',
			'manual_tags' => array(),
			'auto_sync'   => false,
			'last_sync'   => null,
			'last_result' => null,
		);
	}

	/**
	 * All saved configs, keyed by post type.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );

		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * One config, or null.
	 */
	public static function get( $post_type ) {
		$all = self::all();

		return isset( $all[ $post_type ] ) ? wp_parse_args( $all[ $post_type ], self::defaults() ) : null;
	}

	/**
	 * Save a config. Returns the stored config.
	 */
	public static function save( $post_type, $config ) {
		$all = self::all();

		$existing = $all[ $post_type ] ?? self::defaults();
		$merged   = wp_parse_args( $config, $existing );

		$merged['post_type']   = $post_type;
		$merged['tag_source']  = 'taxonomy' === $merged['tag_source'] ? 'taxonomy' : 'meta';
		$merged['auto_sync']   = (bool) $merged['auto_sync'];
		$merged['manual_tags'] = self::clean_tags( $merged['manual_tags'] );

		$all[ $post_type ] = $merged;
		update_option( self::OPTION, $all, false );

		return $merged;
	}

	public static function delete( $post_type ) {
		$all = self::all();
		unset( $all[ $post_type ] );
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Record the outcome of a sync run.
	 */
	public static function record_sync( $post_type, $result ) {
		$all = self::all();
		if ( ! isset( $all[ $post_type ] ) ) {
			return;
		}

		$all[ $post_type ]['last_sync']   = current_time( 'mysql' );
		$all[ $post_type ]['last_result'] = $result;
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Normalise a tag list: trim, drop empties, dedupe, cap length.
	 *
	 * @param array|string $tags
	 * @return string[]
	 */
	public static function clean_tags( $tags ) {
		if ( is_string( $tags ) ) {
			$tags = preg_split( '/[,;|]+/', $tags, -1, PREG_SPLIT_NO_EMPTY );
		}

		if ( ! is_array( $tags ) ) {
			return array();
		}

		$out = array();
		foreach ( $tags as $tag ) {
			$tag = trim( sanitize_text_field( (string) $tag ) );
			// The tag column is varchar(100).
			if ( '' !== $tag && ! in_array( $tag, $out, true ) ) {
				$out[] = mb_substr( $tag, 0, 100 );
			}
		}

		return $out;
	}
}
