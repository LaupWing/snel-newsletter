<?php

namespace Snel\Newsletter\Campaigns;

defined( 'ABSPATH' ) || exit;

// Campaigns live in the snel_newsletter CPT; meta keys are prefixed _snel_nl_*.
class Model {

    private static $post_type = 'snel_newsletter';

    public static function list( array $args = array() ): array {
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

        $workflow_map = self::workflow_map();

        if ( $type === 'workflow' ) {
            $query_args['post__in'] = ! empty( $workflow_map ) ? array_keys( $workflow_map ) : array( 0 );
        } elseif ( $type === 'broadcast' && ! empty( $workflow_map ) ) {
            $query_args['post__not_in'] = array_keys( $workflow_map );
        }

        if ( $status === 'sent' ) {
            $query_args['post_status'] = 'publish';
            $query_args['meta_query']  = array(
                'relation' => 'OR',
                array( 'key' => '_snel_nl_send_status', 'value' => 'sent' ),
                array( 'key' => '_snel_nl_send_status', 'compare' => 'NOT EXISTS' ),
            );
        } elseif ( $status === 'draft' ) {
            // Genuine drafts only — exclude ones cancelled from a schedule.
            $query_args['post_status'] = 'draft';
            $query_args['meta_query']  = array(
                'relation' => 'OR',
                array( 'key' => '_snel_nl_send_status', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_snel_nl_send_status', 'value' => 'cancelled', 'compare' => '!=' ),
            );
        } elseif ( $status === 'sending' ) {
            $query_args['post_status'] = 'publish';
            $query_args['meta_query']  = array(
                array( 'key' => '_snel_nl_send_status', 'value' => 'sending' ),
            );
        } elseif ( $status === 'cancelled' ) {
            // Cancelled campaigns are 'publish' (was sending) or 'draft' (was
            // scheduled, then unscheduled) — the meta flag is what identifies them.
            $query_args['post_status'] = array( 'publish', 'draft' );
            $query_args['meta_query']  = array(
                array( 'key' => '_snel_nl_send_status', 'value' => 'cancelled' ),
            );
        } elseif ( $status === 'scheduled' ) {
            $query_args['post_status'] = 'future';
        }

        $query = new \WP_Query( $query_args );
        $posts = $query->posts;

        $live_stats = \Snel\Newsletter\Tracking\Model::stats_for_campaigns( wp_list_pluck( $posts, 'ID' ) );

        $campaigns = array();
        foreach ( $posts as $post ) {
            $campaigns[] = self::format( $post, $workflow_map, $live_stats );
        }

        return array(
            'campaigns' => $campaigns,
            'total'     => (int) $query->found_posts,
            'page'      => $page,
            'per_page'  => $per_page,
            'pages'     => (int) $query->max_num_pages,
        );
    }

    public static function counts(): array {
        $all_statuses = wp_count_posts( self::$post_type );

        // Sending + cancelled campaigns are otherwise indistinguishable from 'sent'
        // (all 'publish'), and an unscheduled cancel sits under 'draft' — tally each.
        $count_by_send_status = function ( $value, $post_status ) {
            $q = new \WP_Query( array(
                'post_type'      => self::$post_type,
                'post_status'    => $post_status,
                'meta_key'       => '_snel_nl_send_status',
                'meta_value'     => $value,
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ) );
            return (int) $q->found_posts;
        };

        $published         = (int) ( $all_statuses->publish ?? 0 );
        $draft_total       = (int) ( $all_statuses->draft ?? 0 );
        $sending           = $count_by_send_status( 'sending', 'publish' );
        $cancelled_publish = $count_by_send_status( 'cancelled', 'publish' );
        $cancelled_draft   = $count_by_send_status( 'cancelled', 'draft' );
        $cancelled         = $cancelled_publish + $cancelled_draft;
        $total             = $published + $draft_total + (int) ( $all_statuses->future ?? 0 );

        $workflow = count( self::workflow_map() );

        return array(
            'total'     => $total,
            'sent'      => $published - $sending - $cancelled_publish,
            'draft'     => max( 0, $draft_total - $cancelled_draft ),
            'sending'   => $sending,
            'cancelled' => $cancelled,
            'scheduled' => (int) ( $all_statuses->future ?? 0 ),
            'workflow'  => $workflow,
            'broadcast' => max( 0, $total - $workflow ),
        );
    }

    // Pooled rate = total opens|clicks / total recipients, so larger campaigns
    // weigh proportionally. Whole-number percentages.
    public static function performance(): array {
        global $wpdb;

        $recipients = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE( SUM( CAST( r.meta_value AS UNSIGNED ) ), 0 )
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = '_snel_nl_total_recipients'
             WHERE p.post_type = %s AND p.post_status = 'publish'",
            self::$post_type
        ) );

        $tracking = $wpdb->prefix . 'snel_tracking';
        $row      = $wpdb->get_row(
            "SELECT COUNT(DISTINCT CASE WHEN type = 'open'  THEN CONCAT(campaign_id, '-', subscriber_id) END) AS opened,
                    COUNT(DISTINCT CASE WHEN type = 'click' THEN CONCAT(campaign_id, '-', subscriber_id) END) AS clicked
             FROM $tracking"
        );

        return array(
            'avg_open_rate'  => $recipients > 0 ? (int) round( (int) ( $row->opened ?? 0 ) / $recipients * 100 ) : 0,
            'avg_click_rate' => $recipients > 0 ? (int) round( (int) ( $row->clicked ?? 0 ) / $recipients * 100 ) : 0,
        );
    }

    public static function find( int $id ): ?array {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::$post_type ) {
            return null;
        }
        return self::format( $post, self::workflow_map(), \Snel\Newsletter\Tracking\Model::stats_for_campaigns( array( $post->ID ) ) );
    }

    public static function delete( int $id ): bool {
        wp_delete_post( $id, true );
        return true;
    }

    // Halt every queued email that hasn't gone out yet and flag the campaign
    // cancelled. Already-sent emails stay. Returns queued rows stopped, or false.
    public static function cancel( int $id ) {
        global $wpdb;

        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::$post_type ) {
            return false;
        }

        $queue = $wpdb->prefix . 'snel_send_queue';

        $stopped = (int) $wpdb->query( $wpdb->prepare(
            "UPDATE $queue SET status = 'cancelled'
             WHERE campaign_id = %d AND status IN ('pending', 'retrying', 'delayed')",
            $id
        ) );

        // A scheduled campaign hasn't queued yet — pull it off the schedule so
        // WP won't auto-publish it. future→draft doesn't trip the publish hook.
        if ( $post->post_status === 'future' ) {
            wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
        }

        update_post_meta( $id, '_snel_nl_send_status', 'cancelled' );

        return $stopped;
    }

    public static function duplicate( int $id ) {
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

    private static function format( \WP_Post $post, array $workflow_map = array(), array $live_stats = array() ): array {
        $send_status = get_post_meta( $post->ID, '_snel_nl_send_status', true );
        $sent_count  = (int) get_post_meta( $post->ID, '_snel_nl_sent_count', true );
        $total       = (int) get_post_meta( $post->ID, '_snel_nl_total_recipients', true );
        $tags        = get_post_meta( $post->ID, '_snel_nl_tags', true ) ?: array();
        // Live from snel_tracking; the old cached postmeta froze once sending finished.
        $opened      = (int) ( $live_stats[ $post->ID ]['opened'] ?? 0 );
        $clicked     = (int) ( $live_stats[ $post->ID ]['clicked'] ?? 0 );

        $is_workflow     = array_key_exists( $post->ID, $workflow_map );
        $automation_name = $is_workflow ? $workflow_map[ $post->ID ] : '';

        // Cancelled wins over post_status: a cancelled campaign may have been
        // unscheduled back to draft.
        if ( $send_status === 'cancelled' ) {
            $status = 'cancelled';
        } elseif ( $post->post_status === 'draft' ) {
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

        // Workflow emails send from the automation flow (as drafts), so broadcast
        // meta is empty — pull real numbers from the queue/tracking tables instead.
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

    // Live per-campaign stats from snel_send_queue + snel_tracking, used for
    // workflow emails whose sends are logged there rather than in post meta.
    private static function tracking_stats( int $campaign_id ): array {
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

    public static function workflow_ids(): array {
        return array_map( 'intval', array_keys( self::workflow_map() ) );
    }

    // A campaign counts as workflow when its toggle is on OR it's an email step
    // in any automation. Returns post ID => automation name ('' if only flagged).
    private static function workflow_map(): array {
        global $wpdb;

        $map = array();

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

    // Recurses into condition branches; steps come from decoded automation JSON.
    private static function collect_campaign_ids( $steps ): array {
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
