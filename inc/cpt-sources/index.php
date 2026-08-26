<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

new Snel\Newsletter\CptSources\Rest();
new Snel\Newsletter\CptSources\AutoSync();

// Public helper: sync one source now, e.g. right after your own code inserts a lead.
// Safe when the source is not configured yet; returns null then.
function snel_newsletter_sync_source( $id ) {
	$config = Snel\Newsletter\CptSources\Store::get( $id );

	if ( ! $config ) {
		return null;
	}

	$result = Snel\Newsletter\CptSources\Importer::run( $config );
	Snel\Newsletter\CptSources\Store::record_sync( $id, $result );

	return $result;
}
