<?php

namespace Snel\Newsletter\Adapters;

defined( 'ABSPATH' ) || exit;

// Resolves the active email adapter from settings. New providers implement
// AdapterInterface and register here (or via register()).
class Manager {

    private static $adapters = array(
        'ses' => SESAdapter::class,
    );

    // Unknown or missing slug falls back to SES.
    public static function get_active(): AdapterInterface {
        $settings = get_option( 'snel_newsletter_settings', array() );
        $slug     = $settings['adapter'] ?? 'ses';

        if ( ! isset( self::$adapters[ $slug ] ) ) {
            $slug = 'ses';
        }

        $class = self::$adapters[ $slug ];
        return new $class();
    }

    // Returns [ { slug, name, configured } ] for all registered adapters.
    public static function get_all(): array {
        $result = array();

        foreach ( self::$adapters as $slug => $class ) {
            $adapter  = new $class();
            $result[] = array(
                'slug'       => $slug,
                'name'       => $adapter->get_name(),
                'configured' => $adapter->is_configured(),
            );
        }

        return $result;
    }

    public static function register( string $slug, string $class ): void {
        self::$adapters[ $slug ] = $class;
    }
}
