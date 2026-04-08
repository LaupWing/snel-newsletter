<?php
/**
 * Adapter Manager.
 *
 * Resolves the active email adapter based on settings.
 * To add a new provider, just create a new class that implements AdapterInterface
 * and register it here.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Adapters;

defined( 'ABSPATH' ) || exit;

class Manager {

    /** @var array<string, string> Slug → class name */
    private static $adapters = array(
        'ses' => SESAdapter::class,
        // Future: 'sendgrid' => SendGridAdapter::class,
        // Future: 'postmark' => PostmarkAdapter::class,
        // Future: 'mailgun'  => MailgunAdapter::class,
    );

    /**
     * Get the currently active adapter.
     *
     * @return AdapterInterface|null
     */
    public static function get_active() {
        $settings = get_option( 'snel_newsletter_settings', array() );
        $slug     = $settings['adapter'] ?? 'ses';

        if ( ! isset( self::$adapters[ $slug ] ) ) {
            $slug = 'ses';
        }

        $class = self::$adapters[ $slug ];
        return new $class();
    }

    /**
     * Get all registered adapters.
     *
     * @return array [ { slug, name, configured } ]
     */
    public static function get_all() {
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

    /**
     * Register a new adapter.
     *
     * @param string $slug  Unique slug (e.g. 'sendgrid').
     * @param string $class Fully qualified class name.
     */
    public static function register( $slug, $class ) {
        self::$adapters[ $slug ] = $class;
    }
}
