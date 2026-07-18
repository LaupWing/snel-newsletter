<?php
/**
 * Campaign database queries.
 *
 * Uses the snel_newsletter CPT for storage.
 * Campaign meta: _snel_nl_recipients, _snel_nl_tags, _snel_nl_send_status,
 *                _snel_nl_sent_count, _snel_nl_total_recipients,
 *                _snel_nl_preview_text
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Campaigns;

defined( 'ABSPATH' ) || exit;

class Model {

    private static $post_type = 'snel_newsletter';

    /**
     * List campaigns with optional filters.
     */
    public static function list( $args = array() ) {
        $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
        $per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
        $search   = $args['search'] ?? '';
        $status   = $args['status'] ?? '';
        $type     = $args['type'] ?? '';

        $query_args = array(
            'post_type'      => self::$post_type,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => array( 'publish', 'draft', 'future' ),
        );

        if ( $search ) {
            $query_args['s'] = $search;
        }

        // Which campaigns are workflow emails (post ID => automation name|'').
        $workflow_map = self::workflow_map();

        // Filter by type: broadcast (standalone) vs workflow (automation email).
        if ( $type === 'workflow' ) {
            $query_args['post__in'] = ! empty( $workflow_map ) ? array_keys( $workflow_map ) : array( 0 );
        } elseif ( $type === 'broadcast' && ! empty( $workflow_map ) ) {
            $query_args['post__not_in'] = array_keys( $workflow_map );
        }

        // Map our status to WP post_status + meta.
        if ( $status === 'sent' ) {
            $query_args['post_status'] = 'publish';
            $query_args['meta_query']  = array(
                'relation' => 'OR',
                array( 'key' => '_snel_nl_send_status', 'value' => 'sent' ),
                array( 'key' => '_snel_nl_send_status', 'compare' => 'NOT EXISTS' ),
            );
        } elseif ( $status === 'draft' ) {
            $query_args['post_status'] = 'draft';
        } elseif ( $status === 'sending' ) {
            $query_args['post_status'] = 'publish';
            $query_args['meta_query']  = array(
                array( 'key' => '_snel_nl_send_status', 'value' => 'sending' ),
            );
        } elseif ( $status === 'scheduled' ) {
            $query_args['post_status'] = 'future';
        }

        $query = new \WP_Query( $query_args );
        $posts = $query->posts;

        $campaigns = array();
        foreach ( $posts as $post ) {
            $campaigns[] = self::format( $post, $workflow_map );
        }

        return array(
            'campaigns' => $campaigns,
            'total'     => (int) $query->found_posts,
            'page'      => $page,
            'per_page'  => $per_page,
            'pages'     => (int) $query->max_num_pages,
        );
    }

    /**
     * Get status counts.
     */
    public static function counts() {
        $all_statuses = wp_count_posts( self::$post_type );

        // Count sending campaigns.
        $sending_query = new \WP_Query( array(
            'post_type'      => self::$post_type,
            'post_status'    => 'publish',
            'meta_key'       => '_snel_nl_send_status',
            'meta_value'     => 'sending',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        $published = (int) ( $all_statuses->publish ?? 0 );
        $sending   = $sending_query->found_posts;
        $total     = $published + (int) ( $all_statuses->draft ?? 0 ) + (int) ( $all_statuses->future ?? 0 );

        // Workflow emails = those flagged or referenced in an automation.
        $workflow = count( self::workflow_map() );

        return array(
            'total'     => $total,
            'sent'      => $published - $sending,
            'draft'     => (int) ( $all_statuses->draft ?? 0 ),
            'sending'   => $sending,
            'scheduled' => (int) ( $all_statuses->future ?? 0 ),
            'workflow'  => $workflow,
            'broadcast' => max( 0, $total - $workflow ),
        );
    }

    /**
     * Pooled open/click rates across all sent (published) campaigns.
     * Rate = total opens|clicks / total recipients, so larger campaigns
     * weigh proportionally. Returns whole-number percentages.
     */
    public static function performance() {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COALESCE( SUM( CAST( r.meta_value AS UNSIGNED ) ), 0 ) AS recipients,
                COALESCE( SUM( CAST( o.meta_value AS UNSIGNED ) ), 0 ) AS opened,
                COALESCE( SUM( CAST( c.meta_value AS UNSIGNED ) ), 0 ) AS clicked
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = '_snel_nl_total_recipients'
             LEFT JOIN {$wpdb->postmeta} o ON o.post_id = p.ID AND o.meta_key = '_snel_nl_opened'
             LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = '_snel_nl_clicked'
             WHERE p.post_type = %s AND p.post_status = 'publish'",
            self::$post_type
        ) );

        $recipients = (int) ( $row->recipients ?? 0 );

        return array(
            'avg_open_rate'  => $recipients > 0 ? (int) round( (int) $row->opened / $recipients * 100 ) : 0,
            'avg_click_rate' => $recipients > 0 ? (int) round( (int) $row->clicked / $recipients * 100 ) : 0,
        );
    }

    /**
     * Get a single campaign by ID.
     */
    public static function find( $id ) {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::$post_type ) {
            return null;
        }
        return self::format( $post );
    }

    /**
     * Delete a campaign.
     */
    public static function delete( $id ) {
        wp_delete_post( $id, true );
        return true;
    }

    /**
     * Duplicate a campaign (as draft).
     */
    public static function duplicate( $id ) {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::$post_type ) {
            return false;
        }

        $new_id = wp_insert_post( array(
            'post_type'    => self::$post_type,
            'post_title'   => $post->post_title . ' (Copy)',
            'post_content' => $post->post_content,
            'post_status'  => 'draft',
        ) );

        if ( $new_id ) {
            // Copy meta.
            $meta_keys = array( '_snel_nl_recipients', '_snel_nl_tags', '_snel_nl_preview_text' );
            foreach ( $meta_keys as $key ) {
                $value = get_post_meta( $id, $key, true );
                if ( $value ) {
                    update_post_meta( $new_id, $key, $value );
                }
            }
        }

        return $new_id;
    }

    /**
     * Format a WP_Post into a campaign object.
     *
     * @param \WP_Post $post
     * @param array    $workflow_map Post ID => automation name (or '') for workflow emails.
     */
    private static function format( $post, $workflow_map = array() ) {
        $send_status = get_post_meta( $post->ID, '_snel_nl_send_status', true );
        $sent_count  = (int) get_post_meta( $post->ID, '_snel_nl_sent_count', true );
        $total       = (int) get_post_meta( $post->ID, '_snel_nl_total_recipients', true );
        $tags        = get_post_meta( $post->ID, '_snel_nl_tags', true ) ?: array();
        $opened      = (int) get_post_meta( $post->ID, '_snel_nl_opened', true );
        $clicked     = (int) get_post_meta( $post->ID, '_snel_nl_clicked', true );

        $is_workflow     = array_key_exists( $post->ID, $workflow_map );
        $automation_name = $is_workflow ? $workflow_map[ $post->ID ] : '';

        // Determine display status.
        if ( $post->post_status === 'draft' ) {
            $status = 'draft';
        } elseif ( $post->post_status === 'future' ) {
            $status = 'scheduled';
        } elseif ( $send_status === 'sending' ) {
            $status = 'sending';
        } elseif ( $send_status === 'failed' ) {
            $status = 'failed';
        } else {
            $status = 'sent';
        }

        // Workflow emails send from the automation flow (as drafts), so the
        // broadcast meta above is empty. Pull real numbers from the send queue
        // and tracking tables instead, keyed by campaign_id.
        if ( $is_workflow ) {
            $stats      = self::tracking_stats( $post->ID );
            $total      = $stats['recipients'];
            $sent_count = $stats['sent'];
            $opened     = $stats['opened'];
            $clicked    = $stats['clicked'];
            if ( $status === 'draft' && $sent_count > 0 ) {
                $status = 'sent';
            }
        }

        return array(
            'id'              => $post->ID,
            'subject'         => $post->post_title,
            'status'          => $status,
            'type'            => $is_workflow ? 'workflow' : 'broadcast',
            'automation_name' => $automation_name,
            'recipients'      => $total,
            'sent'            => $sent_count,
            'opened'          => $opened,
            'clicked'         => $clicked,
            'tags'            => is_array( $tags ) ? $tags : array(),
            'sent_at'         => $post->post_status === 'draft' ? null : $post->post_date,
            'created_at'      => $post->post_date,
            'edit_url'        => get_edit_post_link( $post->ID, 'raw' ),
        );
    }

    /**
     * Live send/open/click stats for one campaign, read from the send queue
     * and tracking tables. Used for workflow emails, whose sends are logged
     * there (by campaign_id) rather than in the broadcast post meta.
     */
    private static function tracking_stats( $campaign_id ) {
        global $wpdb;

        $queue    = $wpdb->prefix . 'snel_send_queue';
        $tracking = $wpdb->prefix . 'snel_tracking';

        $recipients = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $queue WHERE campaign_id = %d",
            $campaign_id
        ) );
        $sent = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $queue WHERE campaign_id = %d AND status = 'sent'",
            $campaign_id
        ) );
        $opened = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT subscriber_id) FROM $tracking WHERE campaign_id = %d AND type = 'open'",
            $campaign_id
        ) );
        $clicked = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT subscriber_id) FROM $tracking WHERE campaign_id = %d AND type = 'click'",
            $campaign_id
        ) );

        return compact( 'recipients', 'sent', 'opened', 'clicked' );
    }

    /**
     * Every campaign that counts as a workflow email: the toggle is on, OR it's
     * referenced as an email step in any automation. Returns post ID =>
     * automation name (empty string when it's flagged but not yet in a flow).
     */
    private static function workflow_map() {
        global $wpdb;

        $map = array();

        // 1. Emails referenced in automation steps.
        $automations = $wpdb->get_results(
            "SELECT name, steps FROM {$wpdb->prefix}snel_automations"
        );
        foreach ( (array) $automations as $automation ) {
            $steps = json_decode( $automation->steps ?: '[]', true );
            foreach ( self::collect_campaign_ids( is_array( $steps ) ? $steps : array() ) as $cid ) {
                if ( ! isset( $map[ $cid ] ) || $map[ $cid ] === '' ) {
                    $map[ $cid ] = $automation->name;
                }
            }
        }

        // 2. Emails explicitly flagged via the editor toggle.
        $flagged = $wpdb->get_col( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = '1'",
            '_snel_nl_is_workflow'
        ) );
        foreach ( (array) $flagged as $pid ) {
            $pid = (int) $pid;
            if ( ! isset( $map[ $pid ] ) ) {
                $map[ $pid ] = '';
            }
        }

        return $map;
    }

    /**
     * Collect email-step campaign IDs from a (possibly nested) steps array.
     */
    private static function collect_campaign_ids( $steps ) {
        $ids = array();
        foreach ( (array) $steps as $step ) {
            $type = $step['type'] ?? '';
            if ( $type === 'email' && ! empty( $step['campaign_id'] ) ) {
                $ids[] = (int) $step['campaign_id'];
            } elseif ( $type === 'condition' ) {
                $ids = array_merge(
                    $ids,
                    self::collect_campaign_ids( $step['yes'] ?? array() ),
                    self::collect_campaign_ids( $step['no'] ?? array() )
                );
            }
        }
        return array_unique( $ids );
    }
}
