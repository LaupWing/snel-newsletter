<?php
// Maps Snel\Newsletter\Foo\Bar to inc/foo/Bar.php; CamelCase namespace parts become kebab-case folders.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

spl_autoload_register( function ( string $class ): void {
    $prefix = 'Snel\\Newsletter\\';
    if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
        return;
    }

    $parts = explode( '\\', substr( $class, strlen( $prefix ) ) );
    $file  = array_pop( $parts );
    $dirs  = array_map( fn( $p ) => strtolower( preg_replace( '/(?<!^)[A-Z](?=[a-z])/', '-$0', $p ) ), $parts );

    $path = SNEL_NEWSLETTER_PLUGIN_DIR . 'inc/' . implode( '/', $dirs ) . '/' . $file . '.php';
    if ( is_file( $path ) ) {
        require_once $path;
    }
} );
