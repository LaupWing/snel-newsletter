<?php
/**
 * Settings feature — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

new Snel\Newsletter\Settings\Rest();
