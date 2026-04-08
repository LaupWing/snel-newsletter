<?php
/**
 * Sender feature — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/EmailTemplate.php';
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/Rest.php';

new Snel\Newsletter\Sender\Rest();
