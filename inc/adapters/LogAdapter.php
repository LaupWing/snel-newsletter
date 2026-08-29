<?php

namespace Snel\Newsletter\Adapters;

defined( 'ABSPATH' ) || exit;

// Test-only adapter: records every send() in memory, never emails anything.
class LogAdapter implements AdapterInterface {

    public static $log = array();

    public static $fail = false;

    public static function reset(): void {
        self::$log  = array();
        self::$fail = false;
    }

    public function get_name(): string {
        return 'Log (Test)';
    }

    public function send( string $from_email, string $from_name, string $to_email, string $subject, string $html, string $text = '', string $reply_to = '', array $headers = array() ): array {
        if ( self::$fail ) {
            return array( 'success' => false, 'message_id' => null, 'error' => 'Simulated failure' );
        }

        $entry = array(
            'to'      => $to_email,
            'subject' => $subject,
            'from'    => "$from_name <$from_email>",
            'time'    => current_time( 'mysql' ),
        );

        self::$log[] = $entry;

        return array( 'success' => true, 'message_id' => 'log-' . count( self::$log ), 'error' => null );
    }

    public function handles_open_tracking(): bool  { return false; }
    public function handles_click_tracking(): bool { return false; }
    public function get_webhook_slug(): string      { return 'log'; }
    public function parse_webhook( \WP_REST_Request $request ): array { return array(); }
    public function has_stats_api(): bool           { return false; }
    public function fetch_stats( $message_id ): array { return array( 'opens' => 0, 'clicks' => 0, 'bounces' => 0, 'complaints' => 0 ); }
    public function is_configured(): bool           { return true; }

    public function get_settings_fields(): array {
        return array();
    }
}
