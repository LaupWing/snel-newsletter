<?php
/**
 * CPT Sources feature — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

new Snel\Newsletter\CptSources\Rest();
new Snel\Newsletter\CptSources\AutoSync();

/**
 * Sync one configured source into the subscriber table, right now.
 *
 * Call this after writing a row to your own table:
 *
 *   snel_newsletter_sync_source( 'snel_leads' );
 *
 * Safe to call when the source isn't configured yet — returns null.
 *
 * @param string $id Source id (post type name, or the key you registered).
 * @return array|null Counts: imported, tagged, skipped, invalid.
 */
function snel_newsletter_sync_source( $id ) {
	$config = Snel\Newsletter\CptSources\Store::get( $id );

	if ( ! $config ) {
		return null;
	}

	$result = Snel\Newsletter\CptSources\Importer::run( $config );
	Snel\Newsletter\CptSources\Store::record_sync( $id, $result );

	return $result;
}
