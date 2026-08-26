<?php
// One config per source id, all together in a single option.

namespace Snel\Newsletter\CptSources;

defined( 'ABSPATH' ) || exit;

class Store {

	const OPTION = 'snel_newsletter_cpt_sources';

	public static function defaults() {
		return array(
			'id'          => '',
			'kind'        => 'cpt',
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

	public static function all() {
		$saved = get_option( self::OPTION, array() );

		return is_array( $saved ) ? $saved : array();
	}

	public static function get( $id ) {
		$all = self::all();

		return isset( $all[ $id ] ) ? wp_parse_args( $all[ $id ], self::defaults() ) : null;
	}

	public static function save( $id, $config ) {
		$all = self::all();

		$existing = $all[ $id ] ?? self::defaults();
		$merged   = wp_parse_args( $config, $existing );

		$merged['id']          = $id;
		$merged['kind']        = 'custom' === $merged['kind'] ? 'custom' : 'cpt';
		$merged['post_type']   = 'cpt' === $merged['kind'] ? $id : null;
		$merged['tag_source']  = 'taxonomy' === $merged['tag_source'] ? 'taxonomy' : 'meta';
		$merged['auto_sync']   = (bool) $merged['auto_sync'];
		$merged['manual_tags'] = self::clean_tags( $merged['manual_tags'] );

		$all[ $id ] = $merged;
		update_option( self::OPTION, $all, false );

		return $merged;
	}

	public static function delete( $id ) {
		$all = self::all();
		unset( $all[ $id ] );
		update_option( self::OPTION, $all, false );
	}

	public static function record_sync( $id, $result ) {
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) {
			return;
		}

		$all[ $id ]['last_sync']   = current_time( 'mysql' );
		$all[ $id ]['last_result'] = $result;
		update_option( self::OPTION, $all, false );
	}

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
