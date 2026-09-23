<?php
defined( 'ABSPATH' ) || exit;

add_action( 'init', array( Snel\Newsletter\Engagement\Sunset::class, 'ensure_scheduled' ) );
add_action( Snel\Newsletter\Engagement\Sunset::CRON_HOOK, array( Snel\Newsletter\Engagement\Sunset::class, 'run' ) );
