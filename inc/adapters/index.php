<?php
/**
 * Adapters — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/AdapterInterface.php';
require_once __DIR__ . '/SESAdapter.php';
require_once __DIR__ . '/Manager.php';
