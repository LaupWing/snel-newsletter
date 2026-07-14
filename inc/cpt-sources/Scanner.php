<?php
/**
 * CPT Sources — post type + field discovery.
 *
 * WordPress never declares which meta keys a post type "has", so we sample
 * existing posts and score each key on how email-like / tag-like its values are.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\CptSources;

defined( 'ABSPATH' ) || exit;

class Scanner {

	/** Post types we never offer as a source. */
	private static $excluded = array(
		'snel_newsletter', 'attachment', 'revision', 'nav_menu_item',
		'custom_css', 'customize_changeset', 'oembed_cache', 'user_request',
		'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles',
		'wp_navigation', 'wp_font_family', 'wp_font_face',
	);

	/** How many meta values to sample per key. */
	const SAMPLE_SIZE = 25;

	/**
	 * Scan every eligible post type for email + tag field candidates.
	 *
	 * @return array
	 */
	public static function scan() {
		$out = array();

		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $pt ) {
			if ( in_array( $pt->name, self::$excluded, true ) ) {
				continue;
			}

			$count = (int) wp_count_posts( $pt->name )->publish;
			$meta  = self::meta_keys( $pt->name );

			$email_fields = array();
			$tag_fields   = array();

			foreach ( $meta as $key => $info ) {
				if ( $info['email_score'] > 0 ) {
					$email_fields[] = array(
						'key'        => $key,
						'label'      => self::humanize( $key ),
						'confidence' => $info['email_score'],
						'sample'     => $info['sample'],
						'filled'     => $info['filled'],
					);
				}
				if ( $info['tag_score'] > 0 ) {
					$tag_fields[] = array(
						'key'        => $key,
						'label'      => self::humanize( $key ),
						'source'     => 'meta',
						'confidence' => $info['tag_score'],
						'sample'     => $info['sample'],
						'filled'     => $info['filled'],
					);
				}
			}

			// Taxonomies are the idiomatic WP way to tag things — offer them too.
			foreach ( get_object_taxonomies( $pt->name, 'objects' ) as $tax ) {
				if ( ! $tax->show_ui ) {
					continue;
				}
				$terms = get_terms( array( 'taxonomy' => $tax->name, 'hide_empty' => false, 'number' => 3, 'fields' => 'names' ) );
				$tag_fields[] = array(
					'key'        => $tax->name,
					'label'      => $tax->labels->singular_name,
					'source'     => 'taxonomy',
					'confidence' => 1.0,
					'sample'     => is_wp_error( $terms ) ? '' : implode( ', ', $terms ),
					'filled'     => null,
				);
			}

			usort( $email_fields, fn( $a, $b ) => $b['confidence'] <=> $a['confidence'] );
			usort( $tag_fields, fn( $a, $b ) => $b['confidence'] <=> $a['confidence'] );

			$out[] = array(
				'id'           => $pt->name,
				'kind'         => 'cpt',
				'post_type'    => $pt->name,
				'label'        => $pt->labels->name,
				'description'  => '',
				'count'        => $count,
				'email_fields' => $email_fields,
				'tag_fields'   => $tag_fields,
				// A source is only usable if we found somewhere to read an email from.
				'connectable'  => ! empty( $email_fields ),
			);
		}

		// Most promising first: connectable, then by post count.
		usort( $out, function ( $a, $b ) {
			if ( $a['connectable'] !== $b['connectable'] ) {
				return $b['connectable'] <=> $a['connectable'];
			}
			return $b['count'] <=> $a['count'];
		} );

		return $out;
	}

	/**
	 * Sample meta keys for a post type and score each one.
	 *
	 * @param string $post_type
	 * @return array<string,array>
	 */
	private static function meta_keys( $post_type ) {
		global $wpdb;

		$keys = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT pm.meta_key
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = %s
			   AND p.post_status NOT IN ('trash', 'auto-draft')
			   AND pm.meta_key NOT LIKE %s
			   AND pm.meta_key NOT LIKE %s
			 LIMIT 200",
			$post_type,
			$wpdb->esc_like( '_edit' ) . '%',
			$wpdb->esc_like( '_wp_' ) . '%'
		) );

		$out = array();

		foreach ( $keys as $key ) {
			$values = $wpdb->get_col( $wpdb->prepare(
				"SELECT pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.post_type = %s AND pm.meta_key = %s
				   AND p.post_status NOT IN ('trash', 'auto-draft')
				   AND pm.meta_value != ''
				 LIMIT %d",
				$post_type,
				$key,
				self::SAMPLE_SIZE
			) );

			if ( empty( $values ) ) {
				continue;
			}

			$out[ $key ] = array(
				'filled'      => count( $values ),
				'sample'      => self::truncate( $values[0] ),
				'email_score' => self::score_email( $key, $values ),
				'tag_score'   => self::score_tags( $key, $values ),
			);
		}

		return $out;
	}

	/**
	 * How confident are we that this key holds an email address? 0.0–1.0
	 */
	private static function score_email( $key, $values ) {
		$valid = 0;
		foreach ( $values as $v ) {
			if ( is_string( $v ) && is_email( trim( $v ) ) ) {
				$valid++;
			}
		}

		$ratio = $valid / count( $values );

		// Values are the real signal; the key name only breaks ties.
		if ( $ratio < 0.5 ) {
			return 0.0;
		}

		$name_bonus = preg_match( '/e?mail/i', $key ) ? 0.15 : 0.0;

		return min( 1.0, round( $ratio + $name_bonus, 2 ) );
	}

	/**
	 * How tag-like is this key? Name-driven, since tags are just short text.
	 */
	private static function score_tags( $key, $values ) {
		if ( ! preg_match( '/tag|categor|topic|interes|segment|group|onderwerp|type/i', $key ) ) {
			return 0.0;
		}

		// Anything long enough to be prose is not a tag field.
		foreach ( $values as $v ) {
			if ( ! is_string( $v ) || strlen( $v ) > 200 || is_serialized( $v ) ) {
				return 0.0;
			}
		}

		return 0.8;
	}

	private static function humanize( $key ) {
		return ucfirst( trim( str_replace( array( '_', '-' ), ' ', $key ) ) );
	}

	private static function truncate( $value, $len = 40 ) {
		if ( is_serialized( $value ) ) {
			return '(serialized)';
		}
		$value = (string) $value;
		return strlen( $value ) > $len ? substr( $value, 0, $len ) . '…' : $value;
	}

	/**
	 * Preview what would be imported for a given mapping.
	 *
	 * @param string $post_type
	 * @param string $email_field
	 * @param string $tag_field   Meta key or taxonomy name. Empty for none.
	 * @param string $tag_source  'meta' | 'taxonomy'
	 * @param int    $limit
	 * @return array
	 */
	public static function preview( $post_type, $email_field, $tag_field = '', $tag_source = 'meta', $manual_tags = array(), $limit = 10 ) {
		global $wpdb;

		// One query for every post: the preview must count the same set the
		// importer will walk, so the statuses here mirror Importer::run_cpt().
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID AS id,
			        p.post_title AS title,
			        (
			            SELECT pm.meta_value
			            FROM {$wpdb->postmeta} pm
			            WHERE pm.post_id = p.ID AND pm.meta_key = %s
			            ORDER BY pm.meta_id ASC
			            LIMIT 1
			        ) AS email
			 FROM {$wpdb->posts} p
			 WHERE p.post_type = %s
			   AND p.post_status IN ('publish', 'private')
			 ORDER BY p.ID ASC",
			$email_field,
			$post_type
		), ARRAY_A );

		// Tags cost a query per post, so only resolve them for the rows we display.
		$tag_fn = fn( $row ) => self::read_tags( $row['id'], $tag_field, $tag_source );

		return self::preview_rows( $rows, $manual_tags, $limit, $tag_fn );
	}

	/**
	 * Score a set of { id, title, email, tags } rows against the subscriber table.
	 *
	 * Shared by post-type sources and custom providers.
	 *
	 * @param array[]       $rows
	 * @param array         $manual_tags Tags applied to every row.
	 * @param int           $limit       How many rows to return for display.
	 * @param callable|null $tag_fn      Resolves a row's tags, called only for displayed rows.
	 * @return array
	 */
	public static function preview_rows( $rows, $manual_tags = array(), $limit = 10, $tag_fn = null ) {
		$existing = array_flip( array_map( 'strtolower', \Snel\Newsletter\Subscribers\Model::all_emails() ) );

		$out            = array();
		$seen           = array();
		$valid          = 0;
		$invalid        = 0;
		$existing_count = 0;
		$duplicate      = 0;
		$empty          = 0;

		foreach ( $rows as $row ) {
			$email = trim( (string) ( $row['email'] ?? '' ) );

			if ( '' === $email ) {
				$empty++;
				continue;
			}

			$key = strtolower( $email );

			if ( ! is_email( $email ) ) {
				$status = 'invalid';
				$invalid++;
			} elseif ( isset( $existing[ $key ] ) ) {
				$status = 'existing';
				$existing_count++;
			} elseif ( isset( $seen[ $key ] ) ) {
				$status = 'duplicate';
				$duplicate++;
			} else {
				$status = 'new';
				$valid++;
			}

			$seen[ $key ] = true;

			if ( count( $out ) < $limit ) {
				$row_tags = $tag_fn ? call_user_func( $tag_fn, $row ) : ( $row['tags'] ?? array() );
				$tags     = array_values( array_unique( array_merge( $manual_tags, (array) $row_tags ) ) );

				$out[] = array(
					'post_id' => $row['id'] ?? 0,
					'title'   => $row['title'] ?? '',
					'email'   => $email,
					'tags'    => $tags,
					'status'  => $status,
				);
			}
		}

		return array(
			'rows'   => $out,
			'totals' => array(
				'scanned'    => count( $rows ),
				'importable' => $valid,
				'existing'   => $existing_count,
				'duplicate'  => $duplicate,
				'invalid'    => $invalid,
				'no_email'   => $empty,
			),
		);
	}

	/**
	 * Read the tag values off a post for the chosen field.
	 *
	 * @return string[]
	 */
	public static function read_tags( $post_id, $field, $source = 'meta' ) {
		if ( ! $field ) {
			return array();
		}

		if ( 'taxonomy' === $source ) {
			$terms = get_the_terms( $post_id, $field );
			return is_wp_error( $terms ) || ! $terms ? array() : wp_list_pluck( $terms, 'name' );
		}

		$raw = get_post_meta( $post_id, $field, true );

		if ( is_array( $raw ) ) {
			return array_values( array_filter( array_map( 'strval', $raw ) ) );
		}

		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}

		$parts = preg_split( '/[,;|]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );

		return array_values( array_filter( array_map( 'trim', $parts ) ) );
	}
}
