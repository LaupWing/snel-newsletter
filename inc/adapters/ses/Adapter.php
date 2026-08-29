<?php

namespace Snel\Newsletter\Adapters\SES;

use Snel\Newsletter\Adapters\AdapterInterface;

defined( 'ABSPATH' ) || exit;

// SES is the "build it yourself" adapter: we do open/click tracking, take
// bounces/complaints as raw SNS webhooks, and compute stats from our table.
class Adapter implements AdapterInterface {

    public function get_name(): string {
        return 'Amazon SES';
    }

    public function send( string $from_email, string $from_name, string $to_email, string $subject, string $html, string $text = '', string $reply_to = '', array $headers = array() ): array {
        $client = Client::from_settings();

        if ( ! $client ) {
            return array( 'success' => false, 'message_id' => null, 'error' => 'SES not configured.' );
        }

        return $client->send( $from_email, $from_name, $to_email, $subject, $html, $text, $reply_to, $headers );
    }

    // SES tracks nothing itself: we inject the pixel and rewrite links.
    public function handles_open_tracking(): bool {
        return false;
    }

    public function handles_click_tracking(): bool {
        return false;
    }

    public function get_webhook_slug(): string {
        return 'ses';
    }

    public function parse_webhook( \WP_REST_Request $request ): array {
        $body = $request->get_body();
        $data = json_decode( $body, true );

        if ( ! $data ) {
            return array();
        }

        // Signature first, always: this endpoint is public, and following any URL
        // from an unverified body would let anyone make this server fetch it (SSRF).
        if ( ! $this->verify_sns_signature( $data ) ) {
            \Snel\Newsletter\Logger\Logger::warning( 'webhook', 'SNS signature verification failed — request rejected', array(
                'type'     => $data['Type'] ?? 'unknown',
                'cert_url' => $data['SignatureCertURL'] ?? 'missing',
            ) );
            return array();
        }

        if ( isset( $data['Type'] ) && $data['Type'] === 'SubscriptionConfirmation' ) {
            $url = $data['SubscribeURL'] ?? '';
            if ( preg_match( '#^https://sns\.[a-z0-9-]+\.amazonaws\.com/#', $url ) ) {
                wp_remote_get( $url );
            }
            return array();
        }

        // The Message field is itself a JSON string.
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

    // Verifies the AWS SNS message signature so external attackers cannot
    // inject fake bounce/complaint events.
    private function verify_sns_signature( array $data ): bool {
        if ( empty( $data['SignatureCertURL'] ) || empty( $data['Signature'] ) ) {
            return false;
        }

        // The signing certificate must come from AWS SNS itself.
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

        // The fields included in the signed string differ by message type.
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

    public function has_stats_api(): bool {
        return false;
    }

    public function fetch_stats( $message_id ): array {
        return array( 'opens' => 0, 'clicks' => 0, 'bounces' => 0, 'complaints' => 0 );
    }

    public function get_settings_fields(): array {
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

    public function is_configured(): bool {
        $settings = get_option( 'snel_newsletter_settings', array() );

        return ! empty( $settings['ses_access_key'] )
            && ! empty( $settings['ses_secret_key'] )
            && ! empty( $settings['ses_region'] );
    }
}
