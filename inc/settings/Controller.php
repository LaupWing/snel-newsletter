<?php

namespace Snel\Newsletter\Settings;

defined( 'ABSPATH' ) || exit;

class Controller {

    private static string $option_key = 'snel_newsletter_settings';

    public function get() {
        $settings = get_option( self::$option_key, array() );

        // Never expose the secret key; only the last 4 chars leave the server.
        if ( ! empty( $settings['ses_secret_key'] ) ) {
            $key = $settings['ses_secret_key'];
            $settings['ses_secret_key'] = str_repeat( '*', max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 );
        }

        return rest_ensure_response( $settings );
    }

    public function save( \WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $settings = get_option( self::$option_key, array() );

        $fields = array(
            'ses_access_key' => 'sanitize_text_field',
            'ses_region'     => 'sanitize_text_field',
            'from_name'      => 'sanitize_text_field',
            'from_email'     => 'sanitize_email',
            'reply_to'       => 'sanitize_email',
            // Automation lane sender is optional; falls back to the broadcast sender.
            'auto_from_name'  => 'sanitize_text_field',
            'auto_from_email' => 'sanitize_email',
            'auto_reply_to'   => 'sanitize_email',
        );

        foreach ( $fields as $key => $sanitizer ) {
            if ( isset( $params[ $key ] ) ) {
                $settings[ $key ] = call_user_func( $sanitizer, $params[ $key ] );
            }
        }

        // A masked value is the UI echoing our own mask back; never store it.
        if ( isset( $params['ses_secret_key'] ) && strpos( $params['ses_secret_key'], '*' ) === false ) {
            $settings['ses_secret_key'] = sanitize_text_field( $params['ses_secret_key'] );
        }

        update_option( self::$option_key, $settings );

        return rest_ensure_response( array( 'success' => true ) );
    }

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

        $settings = get_option( self::$option_key, array() );

        // Which sending lane to test: broadcast (default) or automation.
        $lane     = in_array( $params['lane'] ?? 'broadcast', array( 'broadcast', 'automation' ), true )
                    ? $params['lane']
                    : 'broadcast';
        $identity   = \Snel\Newsletter\Lanes\Lane::identity( $lane );
        $from_name  = $identity['from_name'] ?: 'Snel Newsletter';
        $from_email = $identity['from_email'];
        $reply_to   = $identity['reply_to'];

        if ( ! $from_email ) {
            return new \WP_Error( 'no_from', 'From email not configured for this lane. Set it in the Sender tab.', array( 'status' => 400 ) );
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

    public function get_logs( \WP_REST_Request $request ) {
        global $wpdb;

        $table   = $wpdb->prefix . 'snel_newsletter_logs';
        $level   = sanitize_text_field( $request->get_param( 'level' ) ?? '' );
        $context = sanitize_text_field( $request->get_param( 'context' ) ?? '' );

        $where  = array( '1=1' );
        $values = array();

        if ( $level ) {
            $where[]  = 'level = %s';
            $values[] = $level;
        }

        if ( $context ) {
            $where[]  = 'context = %s';
            $values[] = $context;
        }

        $where_sql = implode( ' AND ', $where );
        $sql       = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT 500";
        $rows      = $values
            ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) )
            : $wpdb->get_results( $sql );

        return rest_ensure_response( array( 'logs' => $rows ?: array() ) );
    }

    // Streams CSV and exits; bypasses the REST response cycle on purpose.
    public function download_logs( \WP_REST_Request $request ) {
        global $wpdb;

        $table   = $wpdb->prefix . 'snel_newsletter_logs';
        $level   = sanitize_text_field( $request->get_param( 'level' ) ?? '' );
        $context = sanitize_text_field( $request->get_param( 'context' ) ?? '' );

        $where  = array( '1=1' );
        $values = array();

        if ( $level ) {
            $where[]  = 'level = %s';
            $values[] = $level;
        }
        if ( $context ) {
            $where[]  = 'context = %s';
            $values[] = $context;
        }

        $where_sql = implode( ' AND ', $where );
        $sql       = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC";
        $rows      = $values
            ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) )
            : $wpdb->get_results( $sql );

        $filename = 'snel-newsletter-logs-' . gmdate( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'ID', 'Level', 'Context', 'Message', 'Data', 'Created At' ) );

        foreach ( $rows as $row ) {
            fputcsv( $out, array(
                $row->id,
                $row->level,
                $row->context,
                $row->message,
                $row->data,
                $row->created_at,
            ) );
        }

        fclose( $out );
        exit;
    }
}
