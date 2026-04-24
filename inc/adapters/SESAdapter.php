<?php
/**
 * Amazon SES Adapter.
 *
 * SES is the "build everything yourself" adapter:
 * - We handle open tracking (pixel)
 * - We handle click tracking (link rewriting)
 * - We handle bounces/complaints via SNS webhooks (raw JSON)
 * - We calculate stats from our own tracking table
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Adapters;

defined( 'ABSPATH' ) || exit;

class SESAdapter implements AdapterInterface {

    public function get_name() {
        return 'Amazon SES';
    }

    public function send( $from_email, $from_name, $to_email, $subject, $html, $text = '', $reply_to = '', $headers = array() ) {
        $client = \Snel\Newsletter\SES\Client::from_settings();

        if ( ! $client ) {
            return array( 'success' => false, 'message_id' => null, 'error' => 'SES not configured.' );
        }

        return $client->send( $from_email, $from_name, $to_email, $subject, $html, $text, $reply_to );
    }

    // ─── Tracking: SES doesn't do this, so WE handle it ─────────────────────────

    public function handles_open_tracking() {
        return false; // We inject our own tracking pixel.
    }

    public function handles_click_tracking() {
        return false; // We rewrite links ourselves.
    }

    // ─── Webhooks: SES sends raw SNS notifications ──────────────────────────────

    public function get_webhook_slug() {
        return 'ses';
    }

    public function parse_webhook( \WP_REST_Request $request ) {
        $body = $request->get_body();
        $data = json_decode( $body, true );

        if ( ! $data ) {
            return array();
        }

        // SNS sends a SubscriptionConfirmation first — auto-confirm it.
        if ( isset( $data['Type'] ) && $data['Type'] === 'SubscriptionConfirmation' ) {
            if ( isset( $data['SubscribeURL'] ) ) {
                wp_remote_get( $data['SubscribeURL'] );
            }
            return array();
        }

        // Verify SNS signature before processing any notification.
        if ( ! $this->verify_sns_signature( $data ) ) {
            \Snel\Newsletter\Logger\Logger::warning( 'webhook', 'SNS signature verification failed — request rejected', array(
                'type'     => $data['Type'] ?? 'unknown',
                'cert_url' => $data['SignatureCertURL'] ?? 'missing',
            ) );
            return array();
        }

        // Actual notification — the Message field is a JSON string.
        if ( ! isset( $data['Message'] ) ) {
            return array();
        }

        $message = json_decode( $data['Message'], true );
        if ( ! $message ) {
            return array();
        }

        $events = array();
        $type   = $message['notificationType'] ?? $message['eventType'] ?? '';

        if ( $type === 'Bounce' && isset( $message['bounce']['bouncedRecipients'] ) ) {
            // Transient = soft bounce (temporary), Permanent = hard bounce.
            $bounce_type = $message['bounce']['bounceType'] ?? 'Permanent';
            $event_type  = ( strtolower( $bounce_type ) === 'transient' ) ? 'soft_bounce' : 'bounce';

            foreach ( $message['bounce']['bouncedRecipients'] as $recipient ) {
                $events[] = array(
                    'type'   => $event_type,
                    'email'  => $recipient['emailAddress'] ?? '',
                    'reason' => $recipient['diagnosticCode'] ?? 'bounced',
                );
            }
        }

        if ( $type === 'Complaint' && isset( $message['complaint']['complainedRecipients'] ) ) {
            foreach ( $message['complaint']['complainedRecipients'] as $recipient ) {
                $events[] = array(
                    'type'   => 'complaint',
                    'email'  => $recipient['emailAddress'] ?? '',
                    'reason' => 'spam complaint',
                );
            }
        }

        return $events;
    }

    /**
     * Verify AWS SNS message signature.
     * Prevents fake bounce/complaint events from external attackers.
     */
    private function verify_sns_signature( $data ) {
        if ( empty( $data['SignatureCertURL'] ) || empty( $data['Signature'] ) ) {
            return false;
        }

        // Certificate must come from AWS SNS.
        if ( ! preg_match( '#^https://sns\.[a-z0-9\-]+\.amazonaws\.com/#', $data['SignatureCertURL'] ) ) {
            return false;
        }

        $response = wp_remote_get( $data['SignatureCertURL'], array( 'timeout' => 10 ) );
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $cert = wp_remote_retrieve_body( $response );
        if ( ! $cert ) {
            return false;
        }

        // Fields to sign differ by message type.
        $type = $data['Type'] ?? '';
        if ( $type === 'Notification' ) {
            $fields = array( 'Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type' );
        } else {
            $fields = array( 'Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type' );
        }

        $string_to_sign = '';
        foreach ( $fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $string_to_sign .= $field . "\n" . $data[ $field ] . "\n";
            }
        }

        $pub_key   = openssl_get_publickey( $cert );
        $signature = base64_decode( $data['Signature'] );
        $algorithm = ( ( $data['SignatureVersion'] ?? '1' ) === '2' ) ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
        $valid     = openssl_verify( $string_to_sign, $signature, $pub_key, $algorithm );

        return $valid === 1;
    }

    // ─── Stats: SES has no stats API — we calculate from our tracking table ─────

    public function has_stats_api() {
        return false;
    }

    public function fetch_stats( $message_id ) {
        return array( 'opens' => 0, 'clicks' => 0, 'bounces' => 0, 'complaints' => 0 );
    }

    // ─── Configuration ──────────────────────────────────────────────────────────

    public function get_settings_fields() {
        return array(
            array( 'key' => 'ses_access_key', 'label' => 'Access Key ID', 'type' => 'text' ),
            array( 'key' => 'ses_secret_key', 'label' => 'Secret Access Key', 'type' => 'password' ),
            array(
                'key'     => 'ses_region',
                'label'   => 'Region',
                'type'    => 'select',
                'options' => array(
                    'us-east-1'      => 'US East (N. Virginia)',
                    'us-east-2'      => 'US East (Ohio)',
                    'us-west-2'      => 'US West (Oregon)',
                    'eu-west-1'      => 'EU (Ireland)',
                    'eu-central-1'   => 'EU (Frankfurt)',
                    'ap-southeast-1' => 'Asia Pacific (Singapore)',
                    'ap-southeast-2' => 'Asia Pacific (Sydney)',
                ),
            ),
        );
    }

    public function is_configured() {
        $settings = get_option( 'snel_newsletter_settings', array() );

        return ! empty( $settings['ses_access_key'] )
            && ! empty( $settings['ses_secret_key'] )
            && ! empty( $settings['ses_region'] );
    }
}
