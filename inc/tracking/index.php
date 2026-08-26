<?php
/**
 * Tracking feature — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

new Snel\Newsletter\Tracking\Rest();
