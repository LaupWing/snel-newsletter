<?php
/**
 * Settings business logic.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Settings;

defined( 'ABSPATH' ) || exit;

class Controller {

    private static $option_key = 'snel_newsletter_settings';

    /**
     * Get settings (masks secret key).
     */
    public function get() {
        $settings = get_option( self::$option_key, array() );

        // Mask the secret key.
        if ( ! empty( $settings['ses_secret_key'] ) ) {
            $key = $settings['ses_secret_key'];
            $settings['ses_secret_key'] = str_repeat( '*', max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 );
        }

        return rest_ensure_response( $settings );
    }

    /**
     * Save settings.
     */
    public function save( \WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $settings = get_option( self::$option_key, array() );

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

        // Secret key: only update if not masked.
        if ( isset( $params['ses_secret_key'] ) && strpos( $params['ses_secret_key'], '*' ) === false ) {
            $settings['ses_secret_key'] = sanitize_text_field( $params['ses_secret_key'] );
        }

        update_option( self::$option_key, $settings );

        return rest_ensure_response( array( 'success' => true ) );
    }
}
