<?php
/**
 * CPT Sources — import posts or provider rows as subscribers.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\CptSources;

use Snel\Newsletter\Subscribers\Model;

defined( 'ABSPATH' ) || exit;

class Importer {

	/**
	 * Import everything a configured source yields.
	 *
	 * @param array $config A Store config.
	 * @return array Counts.
	 */
	public static function run( $config ) {
		if ( 'custom' === ( $config['kind'] ?? 'cpt' ) ) {
			return self::run_custom( $config );
		}

		return self::run_cpt( $config );
	}

	/**
	 * Import every post of a post-type source.
	 */
	private static function run_cpt( $config ) {
		$ids = get_posts( array(
			'post_type'      => $config['post_type'],
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$result = self::empty_result();

		foreach ( $ids as $post_id ) {
			$outcome = self::sync_post( $post_id, $config );
			$result[ $outcome ]++;
		}

		return $result;
	}

	/**
	 * Import every row a custom provider yields.
	 */
	private static function run_custom( $config ) {
		$rows   = Providers::rows( $config['id'] );
		$result = self::empty_result();

		foreach ( $rows as $row ) {
			$outcome = self::sync_row( $row['email'], $row['name'], $row['tags'], $config );
			$result[ $outcome ]++;
		}

		return $result;
	}

	/**
	 * Sync one post into the subscriber table.
	 *
	 * @return string 'imported' | 'tagged' | 'skipped' | 'invalid'
	 */
	public static function sync_post( $post_id, $config ) {
		$email = trim( (string) get_post_meta( $post_id, $config['email_field'], true ) );
		$tags  = Scanner::read_tags( $post_id, $config['tag_field'], $config['tag_source'] );

		return self::sync_row( $email, get_the_title( $post_id ), $tags, $config );
	}

	/**
	 * Upsert one email into the subscriber table.
	 *
	 * Idempotent: an existing subscriber gets their tags topped up, never
	 * replaced, so re-running never clobbers manual tagging.
	 *
	 * @param string   $email
	 * @param string   $name
	 * @param string[] $tags   Tags from the source itself.
	 * @param array    $config
	 * @return string 'imported' | 'tagged' | 'skipped' | 'invalid' | 'junk'
	 */
	public static function sync_row( $email, $name, $tags, $config ) {
		$email = trim( (string) $email );

		if ( '' === $email ) {
			return 'skipped';
		}

		if ( ! is_email( $email ) ) {
			return 'invalid';
		}

		// Valid shape, guaranteed bounce. Keeping it out protects the sender reputation
		// of the whole list, which is worth more than one dead address.
		if ( \Snel\Newsletter\Subscribers\Validator::is_junk( $email ) ) {
			return 'junk';
		}

		$tags = Store::clean_tags( array_merge( $config['manual_tags'] ?? array(), (array) $tags ) );

		$subscriber_id = Model::create( $email, (string) $name );

		if ( $subscriber_id ) {
			if ( $tags ) {
				Model::add_tags( $subscriber_id, $tags );
			}
			return 'imported';
		}

		// Already a subscriber — top up tags only.
		$existing_id = Model::find_by_email( $email );

		if ( $existing_id && $tags ) {
			Model::add_tags( $existing_id, $tags );
			return 'tagged';
		}

		return 'skipped';
	}

	private static function empty_result() {
		return array(
			'imported' => 0,
			'tagged'   => 0,
			'skipped'  => 0,
			'invalid'  => 0,
			'junk'     => 0,
		);
	}
}
