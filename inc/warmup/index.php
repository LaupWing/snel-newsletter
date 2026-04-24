<?php
/**
 * Warmup module bootstrap.
 *
 * @package SnelNewsletter
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Ramp.php';
require_once __DIR__ . '/Cooldown.php';
require_once __DIR__ . '/Guard.php';
require_once __DIR__ . '/Install.php';
require_once __DIR__ . '/Rest.php';

new Snel\Newsletter\Warmup\Rest();
