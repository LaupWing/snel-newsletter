<?php
/**
 * Tracking controller — handles pixel, click redirect, unsubscribe, webhooks.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Tracking;

defined( 'ABSPATH' ) || exit;

class Controller {

    /**
     * Open tracking pixel.
     * URL: /wp-json/snel-newsletter/v1/t/open?c={campaign_id}&s={subscriber_id}
     *
     * Returns a 1x1 transparent GIF.
     */
    public function open( \WP_REST_Request $request ) {
        $campaign_id   = (int) $request->get_param( 'c' );
        $subscriber_id = (int) $request->get_param( 's' );

        if ( $campaign_id && $subscriber_id ) {
            Model::log( $campaign_id, $subscriber_id, 'open' );
        }

        // Return 1x1 transparent GIF.
        $gif = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );

        header( 'Content-Type: image/gif' );
        header( 'Content-Length: ' . strlen( $gif ) );
        header( 'Cache-Control: no-store, no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );
        echo $gif;
        exit;
    }

    /**
     * Click tracking redirect.
     * URL: /wp-json/snel-newsletter/v1/t/click?c={campaign_id}&s={subscriber_id}&url={encoded_url}
     *
     * Logs the click and redirects to the actual URL.
     */
    public function click( \WP_REST_Request $request ) {
        $campaign_id   = (int) $request->get_param( 'c' );
        $subscriber_id = (int) $request->get_param( 's' );
        $url           = $request->get_param( 'url' );

        if ( $campaign_id && $subscriber_id && $url ) {
            Model::log( $campaign_id, $subscriber_id, 'click', $url );
        }

        // Redirect to the actual URL.
        $redirect = $url ?: home_url();
        header( 'Location: ' . esc_url_raw( $redirect ), true, 302 );
        exit;
    }

    /**
     * Unsubscribe endpoint.
     * URL: /wp-json/snel-newsletter/v1/t/unsubscribe?token={unsubscribe_token}
     *
     * Marks the subscriber as unsubscribed and shows a confirmation page.
     */
    public function unsubscribe( \WP_REST_Request $request ) {
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

        // Mark as unsubscribed.
        $wpdb->update( $table, array( 'status' => 'unsubscribed' ), array( 'id' => $subscriber->id ), array( '%s' ), array( '%d' ) );

        // Show a simple confirmation page.
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

    /**
     * Webhook endpoint — receives bounce/complaint notifications from the adapter.
     * URL: /wp-json/snel-newsletter/v1/webhook/{adapter_slug}
     */
    public function webhook( \WP_REST_Request $request ) {
        global $wpdb;

        $adapter = \Snel\Newsletter\Adapters\Manager::get_active();
        $events  = $adapter->parse_webhook( $request );

        $table = $wpdb->prefix . 'snel_subscribers';

        foreach ( $events as $event ) {
            $email = sanitize_email( $event['email'] ?? '' );
            if ( ! $email ) continue;

            if ( $event['type'] === 'bounce' || $event['type'] === 'complaint' ) {
                $wpdb->update(
                    $table,
                    array( 'status' => 'bounced' ),
                    array( 'email' => $email ),
                    array( '%s' ),
                    array( '%s' )
                );
            }
        }

        return rest_ensure_response( array( 'success' => true, 'processed' => count( $events ) ) );
    }
}
