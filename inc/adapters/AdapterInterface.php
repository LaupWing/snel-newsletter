<?php
/**
 * Email Adapter Interface.
 *
 * Every email provider (SES, SendGrid, Postmark, etc.) implements this.
 * The adapter decides what WE build vs what the provider gives us.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Adapters;

defined( 'ABSPATH' ) || exit;

interface AdapterInterface {

    // ─── Sending ─────────────────────────────────────────────────────────────────

    /**
     * Send a single email.
     *
     * @param string $from_email  Verified sender email.
     * @param string $from_name   Sender display name.
     * @param string $to_email    Recipient email.
     * @param string $subject     Email subject.
     * @param string $html        HTML body.
     * @param string $text        Plain text body.
     * @param string $reply_to    Reply-to address.
     * @param array  $headers     Extra headers (e.g. List-Unsubscribe).
     *
     * @return array { success: bool, message_id: string|null, error: string|null }
     */
    public function send( $from_email, $from_name, $to_email, $subject, $html, $text = '', $reply_to = '', $headers = array() );

    // ─── Tracking ────────────────────────────────────────────────────────────────

    /**
     * Does this adapter handle open tracking itself?
     * If true, we don't inject tracking pixels — the provider does it.
     *
     * @return bool
     */
    public function handles_open_tracking();

    /**
     * Does this adapter handle click tracking itself?
     * If true, we don't rewrite links — the provider does it.
     *
     * @return bool
     */
    public function handles_click_tracking();

    // ─── Webhooks ────────────────────────────────────────────────────────────────

    /**
     * Get the webhook endpoint slug for this adapter.
     * Used to register: /wp-json/snel-newsletter/v1/webhook/{slug}
     *
     * @return string
     */
    public function get_webhook_slug();

    /**
     * Parse an incoming webhook payload into a standard format.
     *
     * @param \WP_REST_Request $request The incoming webhook request.
     *
     * @return array[] Array of events, each: { type: 'bounce'|'complaint'|'open'|'click', email: string, ... }
     */
    public function parse_webhook( \WP_REST_Request $request );

    // ─── Stats ───────────────────────────────────────────────────────────────────

    /**
     * Does this adapter provide stats via API?
     * If true, we fetch from provider. If false, we calculate from our tracking table.
     *
     * @return bool
     */
    public function has_stats_api();

    /**
     * Fetch stats from the provider API (if has_stats_api() is true).
     *
     * @param string $message_id The provider's message ID.
     *
     * @return array { opens: int, clicks: int, bounces: int, complaints: int }
     */
    public function fetch_stats( $message_id );

    // ─── Configuration ───────────────────────────────────────────────────────────

    /**
     * Get the settings fields this adapter needs.
     * Used to dynamically render the settings UI.
     *
     * @return array[] Each: { key: string, label: string, type: 'text'|'password'|'select', options?: array }
     */
    public function get_settings_fields();

    /**
     * Validate that the adapter is properly configured.
     *
     * @return bool
     */
    public function is_configured();

    /**
     * Get the human-readable name of this adapter.
     *
     * @return string
     */
    public function get_name();
}
