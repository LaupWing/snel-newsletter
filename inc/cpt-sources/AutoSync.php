<?php

namespace Snel\Newsletter\CptSources;

defined( 'ABSPATH' ) || exit;

// Post-type sources sync on publish. Custom providers have no save hook, so
// they sync on demand (snel_newsletter_sync_source) and hourly via cron.
class AutoSync {

	const CRON_HOOK = 'snel_newsletter_sync_custom_sources';

	public function __construct() {
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
		add_action( self::CRON_HOOK, array( $this, 'sync_custom_sources' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );

		// Let a theme or plugin push a source immediately after it writes a row.
		add_action( 'snel_newsletter_sync_source', array( $this, 'sync_source' ) );
	}

	public function on_save_post( int $post_id, \WP_Post $post, bool $update ): void {
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

	// auto_sync only gates the cron path; an explicit call always runs.
	public function sync_source( string $id ): ?array {
		$config = Store::get( $id );

		if ( ! $config ) {
			return null;
		}

		$result = Importer::run( $config );
		Store::record_sync( $id, $result );

		return $result;
	}

	// Sweep is needed even for post types: a form plugin can write the email meta
	// after insert, so save_post reads an empty field and skips the row for good.
	public function sync_custom_sources(): void {
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

	public function maybe_schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}
}
