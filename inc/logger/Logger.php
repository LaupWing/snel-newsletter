<?php
/**
 * Plugin-wide logger.
 *
 * Usage:
 *   Logger::info( 'queue', 'Batch started', [ 'count' => 50 ] );
 *   Logger::warning( 'webhook', 'Signature failed' );
 *   Logger::error( 'ses', 'Send failed', [ 'to' => $email, 'error' => $msg ] );
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Logger;

defined( 'ABSPATH' ) || exit;

class Logger {

    public static function info( $context, $message, $data = array() ) {
        self::write( 'info', $context, $message, $data );
    }

    public static function warning( $context, $message, $data = array() ) {
        self::write( 'warning', $context, $message, $data );
    }

    public static function error( $context, $message, $data = array() ) {
        self::write( 'error', $context, $message, $data );
    }

    private static function write( $level, $context, $message, $data = array() ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'snel_newsletter_logs',
            array(
                'level'   => $level,
                'context' => $context,
                'message' => $message,
                'data'    => ! empty( $data ) ? wp_json_encode( $data ) : null,
            ),
            array( '%s', '%s', '%s', '%s' )
        );
    }
}
