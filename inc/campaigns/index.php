<?php
/**
 * Campaigns feature — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

new Snel\Newsletter\Campaigns\Rest();
