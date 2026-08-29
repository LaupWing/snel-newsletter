<?php

namespace Snel\Newsletter\Logger;

defined( 'ABSPATH' ) || exit;

class Logger {

    public static function debug( string $context, string $message, array $data = array() ): void {
        self::write( 'debug', $context, $message, $data );
    }

    public static function info( string $context, string $message, array $data = array() ): void {
        self::write( 'info', $context, $message, $data );
    }

    public static function warning( string $context, string $message, array $data = array() ): void {
        self::write( 'warning', $context, $message, $data );
    }

    public static function error( string $context, string $message, array $data = array() ): void {
        self::write( 'error', $context, $message, $data );
    }

    public static function exception( string $context, string $message, \Throwable $e, array $data = array() ): void {
        self::write( 'error', $context, $message, array_merge( $data, array(
            'exception' => get_class( $e ),
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ) ) );
    }

    private static function write( string $level, string $context, string $message, array $data = array() ): void {
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
