<?php
/**
 * Subscribers feature — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/Rest.php';
require_once __DIR__ . '/Install.php';

new Snel\Newsletter\Subscribers\Rest();
