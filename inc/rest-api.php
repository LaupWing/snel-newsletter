<?php
/**
 * REST API endpoints.
 *
 * @package SnelNewsletter
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
    $namespace = 'snel-newsletter/v1';

    // GET /settings — load settings.
    register_rest_route( $namespace, '/settings', array(
        'methods'             => 'GET',
        'callback'            => 'snel_newsletter_get_settings',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );

    // POST /settings — save settings.
    register_rest_route( $namespace, '/settings', array(
        'methods'             => 'POST',
        'callback'            => 'snel_newsletter_save_settings',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ) );
} );

/**
 * Get newsletter settings.
 */
function snel_newsletter_get_settings() {
    $settings = get_option( 'snel_newsletter_settings', array() );

    // Mask the secret key for frontend display.
    $masked = $settings;
    if ( ! empty( $masked['ses_secret_key'] ) ) {
        $key = $masked['ses_secret_key'];
        $masked['ses_secret_key'] = str_repeat( '*', max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 );
    }

    return rest_ensure_response( $masked );
}

/**
 * Save newsletter settings.
 */
function snel_newsletter_save_settings( WP_REST_Request $request ) {
    $params   = $request->get_json_params();
    $settings = get_option( 'snel_newsletter_settings', array() );

    $fields = array(
        'ses_access_key' => 'sanitize_text_field',
        'ses_region'     => 'sanitize_text_field',
        'from_name'      => 'sanitize_text_field',
        'from_email'     => 'sanitize_email',
        'reply_to'       => 'sanitize_email',
    );

    foreach ( $fields as $key => $sanitizer ) {
        if ( isset( $params[ $key ] ) ) {
            $settings[ $key ] = call_user_func( $sanitizer, $params[ $key ] );
        }
    }

    // Secret key: only update if not masked (contains actual key, not asterisks).
    if ( isset( $params['ses_secret_key'] ) && strpos( $params['ses_secret_key'], '*' ) === false ) {
        $settings['ses_secret_key'] = sanitize_text_field( $params['ses_secret_key'] );
    }

    update_option( 'snel_newsletter_settings', $settings );

    return rest_ensure_response( array( 'success' => true ) );
}
