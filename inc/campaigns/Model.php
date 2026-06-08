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
            $campaigns[] = self::format( $post );
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

        return array(
            'total'     => $published + (int) ( $all_statuses->draft ?? 0 ) + (int) ( $all_statuses->future ?? 0 ),
            'sent'      => $published - $sending,
            'draft'     => (int) ( $all_statuses->draft ?? 0 ),
            'sending'   => $sending,
            'scheduled' => (int) ( $all_statuses->future ?? 0 ),
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
     */
    private static function format( $post ) {
        $send_status = get_post_meta( $post->ID, '_snel_nl_send_status', true );
        $sent_count  = (int) get_post_meta( $post->ID, '_snel_nl_sent_count', true );
        $total       = (int) get_post_meta( $post->ID, '_snel_nl_total_recipients', true );
        $tags        = get_post_meta( $post->ID, '_snel_nl_tags', true ) ?: array();
        $opened      = (int) get_post_meta( $post->ID, '_snel_nl_opened', true );
        $clicked     = (int) get_post_meta( $post->ID, '_snel_nl_clicked', true );

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

        return array(
            'id'         => $post->ID,
            'subject'    => $post->post_title,
            'status'     => $status,
            'recipients' => $total,
            'sent'       => $sent_count,
            'opened'     => $opened,
            'clicked'    => $clicked,
            'tags'       => is_array( $tags ) ? $tags : array(),
            'sent_at'    => $post->post_status === 'draft' ? null : $post->post_date,
            'created_at' => $post->post_date,
            'edit_url'   => get_edit_post_link( $post->ID, 'raw' ),
        );
    }
}
