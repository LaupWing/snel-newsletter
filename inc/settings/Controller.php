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

    /**
     * Send a test email via SES.
     */
    public function test_email( \WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $to     = sanitize_email( $params['email'] ?? '' );

        if ( ! is_email( $to ) ) {
            return new \WP_Error( 'invalid_email', 'Invalid email address.', array( 'status' => 400 ) );
        }

        $client = \Snel\Newsletter\SES\Client::from_settings();

        if ( ! $client ) {
            return new \WP_Error( 'no_credentials', 'SES credentials not configured. Save your settings first.', array( 'status' => 400 ) );
        }

        $settings   = get_option( self::$option_key, array() );
        $from_name  = $settings['from_name'] ?? 'Snel Newsletter';
        $from_email = $settings['from_email'] ?? '';
        $reply_to   = $settings['reply_to'] ?? '';

        if ( ! $from_email ) {
            return new \WP_Error( 'no_from', 'From email not configured. Set it in the Sender tab.', array( 'status' => 400 ) );
        }

        $result = $client->send(
            $from_email,
            $from_name,
            $to,
            'Snel Newsletter — Test Email',
            '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">'
                . '<div style="background: #1a1a1a; padding: 24px; text-align: center;">'
                . '<h1 style="color: #fff; font-size: 20px; margin: 0;">' . esc_html( $from_name ) . '</h1>'
                . '</div>'
                . '<div style="padding: 32px 24px; background: #ffffff;">'
                . '<h2 style="color: #1f2937; font-size: 18px; margin: 0 0 12px;">Your SES connection works!</h2>'
                . '<p style="color: #6b7280; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">This is a test email sent from your Snel Newsletter plugin via Amazon SES.</p>'
                . '<p style="color: #6b7280; font-size: 14px; line-height: 1.6; margin: 0 0 16px;"><strong>Region:</strong> ' . esc_html( $settings['ses_region'] ?? '' ) . '</p>'
                . '<p style="color: #6b7280; font-size: 14px; line-height: 1.6; margin: 0;"><strong>From:</strong> ' . esc_html( $from_email ) . '</p>'
                . '</div>'
                . '<div style="padding: 16px 24px; text-align: center; border-top: 1px solid #e5e7eb;">'
                . '<p style="color: #9ca3af; font-size: 11px; margin: 0;">Sent by Snel Newsletter</p>'
                . '</div>'
            . '</div>',
            'Your SES connection works! This is a test email from Snel Newsletter.',
            $reply_to
        );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'send_failed', $result['error'], array( 'status' => 500 ) );
        }

        return rest_ensure_response( array(
            'success'    => true,
            'message'    => 'Test email sent!',
            'message_id' => $result['message_id'],
        ) );
    }
}
