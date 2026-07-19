<?php
/**
 * Lightweight AWS SES API client.
 *
 * Uses AWS Signature V4 to sign requests directly — no SDK needed.
 * Only implements SendEmail action.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\SES;

defined( 'ABSPATH' ) || exit;

class Client {

    private $access_key;
    private $secret_key;
    private $region;
    private $service = 'ses';

    public function __construct( $access_key, $secret_key, $region ) {
        $this->access_key = $access_key;
        $this->secret_key = $secret_key;
        $this->region     = $region;
    }

    /**
     * Create a client from saved plugin settings.
     *
     * @return self|null Null if credentials are missing.
     */
    public static function from_settings() {
        $settings = get_option( 'snel_newsletter_settings', array() );

        $access_key = $settings['ses_access_key'] ?? '';
        $secret_key = $settings['ses_secret_key'] ?? '';
        $region     = $settings['ses_region'] ?? '';

        if ( ! $access_key || ! $secret_key || ! $region ) {
            return null;
        }

        return new self( $access_key, $secret_key, $region );
    }

    /**
     * Send an email via SES.
     *
     * Uses the SendRawEmail action so we can attach custom headers such as
     * List-Unsubscribe — the plain SendEmail action silently ignores headers,
     * and Gmail/Yahoo bulk rules require List-Unsubscribe or the mail is
     * spam-foldered. See build_raw_message() for the MIME assembly.
     *
     * @param string $from_email  Sender email (must be verified in SES).
     * @param string $from_name   Sender display name.
     * @param string $to_email    Recipient email.
     * @param string $subject     Email subject.
     * @param string $html_body   HTML content.
     * @param string $text_body   Plain text fallback (optional).
     * @param string $reply_to    Reply-to address (optional).
     * @param array  $headers     Extra headers, e.g. List-Unsubscribe (optional).
     *
     * @return array { success: bool, message_id: string|null, error: string|null }
     */
    public function send( $from_email, $from_name, $to_email, $subject, $html_body, $text_body = '', $reply_to = '', $headers = array() ) {
        $raw = $this->build_raw_message( $from_email, $from_name, $to_email, $subject, $html_body, $text_body, $reply_to, $headers );

        $params = array(
            'Action'                             => 'SendRawEmail',
            'RawMessage.Data'                    => base64_encode( $raw ),
            // Set the envelope sender/recipient explicitly so SES doesn't have
            // to parse them out of the headers.
            'Source'                             => $from_email,
            'Destinations.member.1'              => $to_email,
        );

        $result = $this->request( $params );

        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
            \Snel\Newsletter\Logger\Logger::error( 'ses', 'Send failed', array(
                'to'    => $to_email,
                'error' => $error,
            ) );
            return array(
                'success'    => false,
                'message_id' => null,
                'error'      => $error,
            );
        }

        // Parse message ID from XML response.
        $message_id = null;
        if ( preg_match( '/<MessageId>(.+?)<\/MessageId>/', $result, $matches ) ) {
            $message_id = $matches[1];
        }

        \Snel\Newsletter\Logger\Logger::info( 'ses', 'Email sent', array(
            'to'         => $to_email,
            'message_id' => $message_id,
        ) );

        return array(
            'success'    => true,
            'message_id' => $message_id,
            'error'      => null,
        );
    }

    /**
     * Build a raw RFC 5322 MIME message.
     *
     * If a text body is present we send multipart/alternative (text + html),
     * which also helps deliverability; otherwise a single html part.
     * Custom headers (List-Unsubscribe etc.) are written verbatim.
     */
    private function build_raw_message( $from_email, $from_name, $to_email, $subject, $html_body, $text_body, $reply_to, $headers ) {
        $eol      = "\r\n";
        $from     = $from_name
            ? '=?UTF-8?B?' . base64_encode( $from_name ) . '?= <' . $from_email . '>'
            : $from_email;
        $boundary = 'snel_' . hash( 'sha256', $from_email . $to_email . $subject );

        $lines = array();
        $lines[] = 'From: ' . $from;
        $lines[] = 'To: ' . $to_email;
        $lines[] = 'Subject: =?UTF-8?B?' . base64_encode( $subject ) . '?=';
        if ( $reply_to ) {
            $lines[] = 'Reply-To: ' . $reply_to;
        }

        // Custom headers — this is where List-Unsubscribe finally lands.
        foreach ( $headers as $name => $value ) {
            // Strip CR/LF to prevent header injection.
            $name  = preg_replace( '/[\r\n]+/', '', $name );
            $value = preg_replace( '/[\r\n]+/', '', $value );
            $lines[] = $name . ': ' . $value;
        }

        $lines[] = 'MIME-Version: 1.0';

        if ( $text_body ) {
            $lines[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $lines[] = '';
            $lines[] = '--' . $boundary;
            $lines[] = 'Content-Type: text/plain; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: base64';
            $lines[] = '';
            $lines[] = chunk_split( base64_encode( $text_body ) );
            $lines[] = '--' . $boundary;
            $lines[] = 'Content-Type: text/html; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: base64';
            $lines[] = '';
            $lines[] = chunk_split( base64_encode( $html_body ) );
            $lines[] = '--' . $boundary . '--';
        } else {
            $lines[] = 'Content-Type: text/html; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: base64';
            $lines[] = '';
            $lines[] = chunk_split( base64_encode( $html_body ) );
        }

        return implode( $eol, $lines );
    }

    /**
     * Make a signed request to the SES API.
     */
    private function request( $params ) {
        $host     = "email.{$this->region}.amazonaws.com";
        $endpoint = "https://{$host}/";
        $body     = http_build_query( $params );
        $date     = gmdate( 'Ymd\THis\Z' );
        $datestamp = gmdate( 'Ymd' );

        // Create canonical request.
        $canonical_headers = "content-type:application/x-www-form-urlencoded\nhost:{$host}\nx-amz-date:{$date}\n";
        $signed_headers    = 'content-type;host;x-amz-date';
        $payload_hash      = hash( 'sha256', $body );

        $canonical_request = implode( "\n", array(
            'POST',
            '/',
            '',
            $canonical_headers,
            $signed_headers,
            $payload_hash,
        ) );

        // Create string to sign.
        $credential_scope = "{$datestamp}/{$this->region}/{$this->service}/aws4_request";
        $string_to_sign   = implode( "\n", array(
            'AWS4-HMAC-SHA256',
            $date,
            $credential_scope,
            hash( 'sha256', $canonical_request ),
        ) );

        // Calculate signature.
        $signing_key = $this->get_signature_key( $datestamp );
        $signature   = hash_hmac( 'sha256', $string_to_sign, $signing_key );

        // Build authorization header.
        $authorization = "AWS4-HMAC-SHA256 Credential={$this->access_key}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

        // Make request.
        $response = wp_remote_post( $endpoint, array(
            'headers' => array(
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'X-Amz-Date'    => $date,
                'Authorization' => $authorization,
            ),
            'body'    => $body,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            // Extract error message from XML.
            $error = 'SES Error (HTTP ' . $code . ')';
            if ( preg_match( '/<Message>(.+?)<\/Message>/', $body, $matches ) ) {
                $error = $matches[1];
            }
            return new \WP_Error( 'ses_error', $error );
        }

        return $body;
    }

    /**
     * Derive the signing key for AWS Signature V4.
     */
    private function get_signature_key( $datestamp ) {
        $k_date    = hash_hmac( 'sha256', $datestamp, 'AWS4' . $this->secret_key, true );
        $k_region  = hash_hmac( 'sha256', $this->region, $k_date, true );
        $k_service = hash_hmac( 'sha256', $this->service, $k_region, true );
        $k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );

        return $k_signing;
    }
}
