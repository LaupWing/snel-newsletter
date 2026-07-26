<?php
/**
 * Sending lanes.
 *
 * A campaign belongs to one of two lanes:
 *  - 'automation' — workflow emails that fire from an automation flow
 *  - 'broadcast'  — everything else (one-time sends to a chosen audience)
 *
 * Each lane sends from its own identity (from-domain) and warms up on its own
 * ramp, so a bad automation blast only burns the automation domain and never
 * touches the broadcast reputation.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Lanes;

use Snel\Newsletter\Warmup\Settings as Warmup;

defined( 'ABSPATH' ) || exit;

class Lane {

    /**
     * Which lane a campaign sends on.
     *
     * @param int        $campaign_id
     * @param array|null $workflow_ids Pre-fetched workflow campaign ids (avoids
     *                                 re-querying per row inside a batch).
     */
    public static function for_campaign( int $campaign_id, ?array $workflow_ids = null ): string {
        if ( $workflow_ids === null ) {
            $workflow_ids = \Snel\Newsletter\Campaigns\Model::workflow_ids();
        }
        return in_array( $campaign_id, $workflow_ids, true )
            ? Warmup::LANE_AUTOMATION
            : Warmup::LANE_BROADCAST;
    }

    /**
     * The from-identity for a lane. The automation lane uses its own sender if
     * configured; otherwise it falls back to the broadcast (default) sender.
     *
     * @return array { from_email, from_name, reply_to }
     */
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
