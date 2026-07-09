<?php
/**
 * CPT Sources — import posts as subscribers.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\CptSources;

use Snel\Newsletter\Subscribers\Model;

defined( 'ABSPATH' ) || exit;

class Importer {

	/**
	 * Import every post of a configured source.
	 *
	 * @param array $config A Store config.
	 * @return array Counts.
	 */
	public static function run( $config ) {
		$ids = get_posts( array(
			'post_type'      => $config['post_type'],
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$result = array(
			'imported' => 0,
			'tagged'   => 0,
			'skipped'  => 0,
			'invalid'  => 0,
		);

		foreach ( $ids as $post_id ) {
			$outcome = self::sync_post( $post_id, $config );

			if ( isset( $result[ $outcome ] ) ) {
				$result[ $outcome ]++;
			}
		}

		return $result;
	}

	/**
	 * Sync one post into the subscriber table.
	 *
	 * Idempotent: an existing subscriber gets their tags topped up, never
	 * replaced, so re-running never clobbers manual tagging.
	 *
	 * @param int   $post_id
	 * @param array $config
	 * @return string 'imported' | 'tagged' | 'skipped' | 'invalid'
	 */
	public static function sync_post( $post_id, $config ) {
		$email = trim( (string) get_post_meta( $post_id, $config['email_field'], true ) );

		if ( '' === $email ) {
			return 'skipped';
		}

		if ( ! is_email( $email ) ) {
			return 'invalid';
		}

		$tags = array_merge(
			$config['manual_tags'],
			Scanner::read_tags( $post_id, $config['tag_field'], $config['tag_source'] )
		);
		$tags = Store::clean_tags( $tags );

		$name = get_the_title( $post_id );

		$subscriber_id = Model::create( $email, $name );

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
}
