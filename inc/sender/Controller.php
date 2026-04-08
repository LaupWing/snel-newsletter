<?php
/**
 * Sender controller — preview and send campaigns.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Sender;

defined( 'ABSPATH' ) || exit;

class Controller {

    /**
     * Preview the email HTML for a campaign.
     */
    public function preview( \WP_REST_Request $request ) {
        $id   = (int) $request->get_param( 'id' );
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== 'snel_newsletter' ) {
            return new \WP_Error( 'not_found', 'Campaign not found.', array( 'status' => 404 ) );
        }

        $content      = apply_filters( 'the_content', $post->post_content );
        $preview_text = get_post_meta( $id, '_snel_nl_preview_text', true ) ?: '';
        $settings     = get_option( 'snel_newsletter_settings', array() );
        $brand_name   = $settings['from_name'] ?? get_bloginfo( 'name' );

        $html = EmailTemplate::render( $content, $brand_name, '#unsubscribe', $preview_text );

        return rest_ensure_response( array(
            'subject' => $post->post_title,
            'html'    => $html,
        ) );
    }

    /**
     * Send a test email for a campaign to a single address.
     */
    public function send_test( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $params = $request->get_json_params();
        $to = sanitize_email( $params['email'] ?? '' );

        if ( ! is_email( $to ) ) {
            return new \WP_Error( 'invalid_email', 'Invalid email address.', array( 'status' => 400 ) );
        }

        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'snel_newsletter' ) {
            return new \WP_Error( 'not_found', 'Campaign not found.', array( 'status' => 404 ) );
        }

        $client = \Snel\Newsletter\SES\Client::from_settings();
        if ( ! $client ) {
            return new \WP_Error( 'no_credentials', 'SES not configured.', array( 'status' => 400 ) );
        }

        $settings     = get_option( 'snel_newsletter_settings', array() );
        $from_email   = $settings['from_email'] ?? '';
        $from_name    = $settings['from_name'] ?? '';
        $reply_to     = $settings['reply_to'] ?? '';
        $preview_text = get_post_meta( $id, '_snel_nl_preview_text', true ) ?: '';

        if ( ! $from_email ) {
            return new \WP_Error( 'no_from', 'From email not configured.', array( 'status' => 400 ) );
        }

        $content  = apply_filters( 'the_content', $post->post_content );
        $html     = EmailTemplate::render( $content, $from_name, '#unsubscribe', $preview_text );
        $text     = EmailTemplate::to_plain_text( $html );
        $subject  = '[TEST] ' . $post->post_title;

        $result = $client->send( $from_email, $from_name, $to, $subject, $html, $text, $reply_to );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'send_failed', $result['error'], array( 'status' => 500 ) );
        }

        return rest_ensure_response( array( 'success' => true, 'message_id' => $result['message_id'] ) );
    }
}
