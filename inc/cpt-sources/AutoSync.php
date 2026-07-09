<?php
/**
 * CPT Sources — auto-sync on post save.
 *
 * When a source has auto_sync enabled, any post of that type that gets
 * published pushes its email straight into the subscriber table.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\CptSources;

defined( 'ABSPATH' ) || exit;

class AutoSync {

	public function __construct() {
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
	}

	/**
	 * @param int      $post_id
	 * @param \WP_Post $post
	 * @param bool     $update
	 */
	public function on_save_post( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			return;
		}

		$config = Store::get( $post->post_type );

		if ( ! $config || ! $config['auto_sync'] || ! $config['email_field'] ) {
			return;
		}

		Importer::sync_post( $post_id, $config );
	}
}
