<?php
/**
 * Log Adapter — for testing only.
 *
 * Implements AdapterInterface but never calls any real email service.
 * Every send() call is recorded in-memory and can be inspected after the fact.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Adapters;

defined( 'ABSPATH' ) || exit;

class LogAdapter implements AdapterInterface {

    /** @var array[] Recorded send calls. */
    public static $log = array();

    /** @var bool Simulate a send failure. */
    public static $fail = false;

    public static function reset() {
        self::$log  = array();
        self::$fail = false;
    }

    public function get_name() {
        return 'Log (Test)';
    }

    public function send( $from_email, $from_name, $to_email, $subject, $html, $text = '', $reply_to = '', $headers = array() ) {
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

    public function handles_open_tracking()  { return false; }
    public function handles_click_tracking() { return false; }
    public function get_webhook_slug()        { return 'log'; }
    public function parse_webhook( \WP_REST_Request $request ) { return array(); }
    public function has_stats_api()           { return false; }
    public function fetch_stats( $message_id ) { return array( 'opens' => 0, 'clicks' => 0, 'bounces' => 0, 'complaints' => 0 ); }
    public function is_configured()           { return true; }

    public function get_settings_fields() {
        return array();
    }
}
