<?php
/**
 * CPT Sources — auto-sync.
 *
 * Post-type sources sync on publish. Custom providers have no save hook, so
 * they sync on demand (call snel_newsletter_sync_source) and hourly via cron.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\CptSources;

defined( 'ABSPATH' ) || exit;

class AutoSync {

	const CRON_HOOK = 'snel_newsletter_sync_custom_sources';

	public function __construct() {
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
		add_action( self::CRON_HOOK, array( $this, 'sync_custom_sources' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );

		// Let a theme or plugin push a source immediately after it writes a row.
		add_action( 'snel_newsletter_sync_source', array( $this, 'sync_source' ) );
	}

	/**
	 * A post-type source syncs the moment a post is published.
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

		if ( ! $config || ! $config['auto_sync'] || 'cpt' !== $config['kind'] || ! $config['email_field'] ) {
			return;
		}

		Importer::sync_post( $post_id, $config );
	}

	/**
	 * Run one source now, regardless of kind. Respects auto_sync being off
	 * only for the cron path — an explicit call always runs.
	 *
	 * @param string $id
	 * @return array|null Counts, or null if the source isn't configured.
	 */
	public function sync_source( $id ) {
		$config = Store::get( $id );

		if ( ! $config ) {
			return null;
		}

		$result = Importer::run( $config );
		Store::record_sync( $id, $result );

		return $result;
	}

	/**
	 * Cron: sync every source that has auto_sync enabled, whatever its kind.
	 *
	 * Post-type sources can't rely on save_post alone: a form plugin inserts the
	 * post first and writes the email meta after, so the hook reads an empty
	 * field and skips the row for good. This sweep re-reads the meta once it is
	 * actually there.
	 */
	public function sync_custom_sources() {
		foreach ( Store::all() as $id => $config ) {
			$config = wp_parse_args( $config, Store::defaults() );

			if ( ! $config['auto_sync'] ) {
				continue;
			}

			if ( 'cpt' === $config['kind'] && ! $config['email_field'] ) {
				continue;
			}

			$result = Importer::run( $config );
			Store::record_sync( $id, $result );
		}
	}

	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}
}
