<?php
/**
 * Automations feature — entry point.
 *
 * @package SnelNewsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

new Snel\Newsletter\Automations\Rest();

// Tag-added trigger.
add_action( 'snel_newsletter_tags_added', array( 'Snel\Newsletter\Automations\Engine', 'on_tags_added' ), 10, 2 );
