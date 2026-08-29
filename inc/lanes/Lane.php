<?php
// A campaign sends on one of two lanes: 'automation' (workflow emails, own
// domain) or 'broadcast' (everything else). Each lane has its own warmup budget.

namespace Snel\Newsletter\Lanes;

use Snel\Newsletter\Warmup\Settings as Warmup;

defined( 'ABSPATH' ) || exit;

class Lane {

    public static function for_campaign( int $campaign_id, ?array $workflow_ids = null ): string {
        if ( $workflow_ids === null ) {
            $workflow_ids = \Snel\Newsletter\Campaigns\Model::workflow_ids();
        }
        return in_array( $campaign_id, $workflow_ids, true )
            ? Warmup::LANE_AUTOMATION
            : Warmup::LANE_BROADCAST;
    }

    // Automation uses its own sender when configured, else the broadcast sender.
    public static function identity( string $lane ): array {
        $s = get_option( 'snel_newsletter_settings', array() );

        if ( $lane === Warmup::LANE_AUTOMATION && ! empty( $s['auto_from_email'] ) ) {
            return array(
                'from_email' => $s['auto_from_email'],
                'from_name'  => $s['auto_from_name'] ?? ( $s['from_name'] ?? '' ),
                'reply_to'   => $s['auto_reply_to'] ?? ( $s['reply_to'] ?? '' ),
            );
        }

        return array(
            'from_email' => $s['from_email'] ?? '',
            'from_name'  => $s['from_name'] ?? '',
            'reply_to'   => $s['reply_to'] ?? '',
        );
    }
}
