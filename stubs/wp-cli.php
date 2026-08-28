<?php
// IDE stub only — never loaded at runtime. WP-CLI defines the real class.

namespace {
    if ( ! class_exists( 'WP_CLI' ) ) {
        class WP_CLI {
            public static function log( string $message ): void {}
            public static function warning( string $message ): void {}
            public static function error( string $message ): void {}
            public static function success( string $message ): void {}
            public static function add_command( string $name, $callable ): void {}
        }
    }
}

namespace WP_CLI\Utils {
    function format_items( string $format, array $items, array $fields ): void {}
}
