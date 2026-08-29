<?php

namespace Snel\Newsletter\Adapters;

defined( 'ABSPATH' ) || exit;

// Every email provider (SES, SendGrid, ...) implements this. The adapter
// decides what WE build (tracking, stats) vs what the provider gives us.
// SOT:ADAPTER — a new provider implements this and registers in Manager; nothing else changes.
interface AdapterInterface {

    // Returns { success: bool, message_id: string|null, error: string|null }.
    public function send( string $from_email, string $from_name, string $to_email, string $subject, string $html, string $text = '', string $reply_to = '', array $headers = array() ): array;

    // True means the provider tracks opens itself, so we skip our pixel.
    public function handles_open_tracking(): bool;

    // True means the provider rewrites links itself, so we don't.
    public function handles_click_tracking(): bool;

    // Slug for /wp-json/snel-newsletter/v1/webhook/{slug}.
    public function get_webhook_slug(): string;

    // Normalizes a webhook payload into events: { type: 'bounce'|'complaint'|..., email, ... }.
    public function parse_webhook( \WP_REST_Request $request ): array;

    // False means stats are calculated from our own tracking table.
    public function has_stats_api(): bool;

    // Returns { opens: int, clicks: int, bounces: int, complaints: int }.
    public function fetch_stats( $message_id ): array;

    // Field defs for the settings UI: { key, label, type, options? }.
    public function get_settings_fields(): array;

    public function is_configured(): bool;

    public function get_name(): string;
}
