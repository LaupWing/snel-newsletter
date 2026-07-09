<?php
/**
 * CPT Sources feature — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/Store.php';
require_once __DIR__ . '/Scanner.php';
require_once __DIR__ . '/Importer.php';
require_once __DIR__ . '/AutoSync.php';
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/Rest.php';

new Snel\Newsletter\CptSources\Rest();
new Snel\Newsletter\CptSources\AutoSync();
