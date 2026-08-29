<?php

namespace Snel\Newsletter\Tracking;

defined( 'ABSPATH' ) || exit;

// Handlers behind the public tracking routes: open pixel, click redirect,
// unsubscribe, and adapter webhooks. Routes are registered in Rest.php.
class Controller {

    public function open( \WP_REST_Request $request ): void {
        $campaign_id   = (int) $request->get_param( 'c' );
        $subscriber_id = (int) $request->get_param( 's' );

        if ( $campaign_id && $subscriber_id ) {
            Model::log( $campaign_id, $subscriber_id, 'open' );
        }

        // 1x1 transparent GIF.
        $gif = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );

        header( 'Content-Type: image/gif' );
        header( 'Content-Length: ' . strlen( $gif ) );
        header( 'Cache-Control: no-store, no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );
        echo $gif;
        exit;
    }

    public function click( \WP_REST_Request $request ): void {
        $campaign_id   = (int) $request->get_param( 'c' );
        $subscriber_id = (int) $request->get_param( 's' );
        $url           = $request->get_param( 'url' );
        $hash          = (string) $request->get_param( 'h' );

        // Only links we signed at send time may redirect; anything else goes home.
        if ( ! $url || ! hash_equals( Model::sign( $campaign_id, $subscriber_id, $url ), $hash ) ) {
            header( 'Location: ' . esc_url_raw( home_url() ), true, 302 );
            exit;
        }

        if ( $campaign_id && $subscriber_id ) {
            Model::log( $campaign_id, $subscriber_id, 'click', $url );
        }

        header( 'Location: ' . esc_url_raw( $url ), true, 302 );
        exit;
    }

    public function unsubscribe( \WP_REST_Request $request ): void {
        global $wpdb;

        $token = sanitize_text_field( $request->get_param( 'token' ) );

        if ( ! $token ) {
            wp_die( 'Invalid unsubscribe link.', 'Unsubscribe', array( 'response' => 400 ) );
        }

        $table      = $wpdb->prefix . 'snel_subscribers';
        $subscriber = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE unsubscribe_token = %s", $token ) );

        if ( ! $subscriber ) {
            wp_die( 'Invalid unsubscribe link.', 'Unsubscribe', array( 'response' => 400 ) );
        }

        $wpdb->update( $table, array( 'status' => 'unsubscribed' ), array( 'id' => $subscriber->id ), array( '%s' ), array( '%d' ) );

        $settings  = get_option( 'snel_newsletter_settings', array() );
        $site_name = $settings['from_name'] ?? get_bloginfo( 'name' );

        wp_die(
            '<div style="text-align: center; font-family: Arial, sans-serif; padding: 40px;">'
            . '<h1 style="font-size: 24px; color: #111827;">You\'ve been unsubscribed</h1>'
            . '<p style="color: #6b7280; font-size: 16px; margin-top: 12px;">You will no longer receive emails from <strong>' . esc_html( $site_name ) . '</strong>.</p>'
            . '<p style="color: #9ca3af; font-size: 14px; margin-top: 24px;">If this was a mistake, contact us to re-subscribe.</p>'
            . '</div>',
            'Unsubscribed — ' . esc_html( $site_name ),
            array( 'response' => 200 )
        );
    }

    // Bounce/complaint events from the active mail adapter. Hard bounces and
    // complaints flip subscriber status; soft bounces are logged only.
    public function webhook( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $adapter = \Snel\Newsletter\Adapters\Manager::get_active();
        $events  = $adapter->parse_webhook( $request );

        \Snel\Newsletter\Logger\Logger::info( 'webhook', 'SNS notification received', array(
            'events' => count( $events ),
            'slug'   => $request->get_param( 'slug' ),
        ) );

        $table = $wpdb->prefix . 'snel_subscribers';

        foreach ( $events as $event ) {
            $email = sanitize_email( $event['email'] ?? '' );
            if ( ! $email ) continue;

            if ( $event['type'] === 'bounce' ) {
                $wpdb->update( $table, array( 'status' => 'bounced' ), array( 'email' => $email ), array( '%s' ), array( '%s' ) );
                \Snel\Newsletter\Logger\Logger::warning( 'webhook', 'Hard bounce — subscriber marked bounced', array( 'email' => $email, 'reason' => $event['reason'] ?? '' ) );
            } elseif ( $event['type'] === 'complaint' ) {
                $wpdb->update( $table, array( 'status' => 'complained' ), array( 'email' => $email ), array( '%s' ), array( '%s' ) );
                \Snel\Newsletter\Logger\Logger::warning( 'webhook', 'Spam complaint — subscriber marked complained', array( 'email' => $email ) );
            } elseif ( $event['type'] === 'soft_bounce' ) {
                \Snel\Newsletter\Logger\Logger::info( 'webhook', 'Soft bounce — no action taken', array( 'email' => $email, 'reason' => $event['reason'] ?? '' ) );
            }
        }

        return rest_ensure_response( array( 'success' => true, 'processed' => count( $events ) ) );
    }
}
