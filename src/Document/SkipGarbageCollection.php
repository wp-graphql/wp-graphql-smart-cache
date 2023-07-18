<?php
/**
 * Content
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache\Document;

use WPGraphQL\SmartCache\Admin\Settings;
use WPGraphQL\SmartCache\Document;
use GraphQL\Server\RequestError;

class SkipGarbageCollection {

	const TAXONOMY_NAME = 'graphql_document_skip_gc';
	const DISABLED      = 'disabled';

	public function init() {
		register_taxonomy(
			self::TAXONOMY_NAME,
			Document::TYPE_NAME,
			[
				'description'        => __( 'Select this option to not clean up this saved GraphQL query document after a number of days. See admin settings.', 'wp-graphql-smart-cache' ),
				'labels'             => [
					'name' => __( 'Garbage Collection', 'wp-graphql-smart-cache' ),
				],
				'hierarchical'       => false,
				'public'             => false,
				'publicly_queryable' => false,
				'show_admin_column'  => true,
				'show_in_menu'       => Settings::show_in_admin(),
				'show_ui'            => Settings::show_in_admin(),
				'show_in_quick_edit' => false,
				'meta_box_cb'        => [
					'WPGraphQL\SmartCache\Admin\Editor',
					'skip_garbage_collection_input_box_cb',
				],
				'show_in_graphql'    => false,
				// false because we register a field with different name
			]
		);
	}

	/**
	 * Look up the setting for a post
	 *
	 * @param int  The post id
	 */
	public static function get( $post_id ) {
		$item = get_the_terms( $post_id, self::TAXONOMY_NAME );
		return ! is_wp_error( $item ) && isset( $item[0]->name ) ? $item[0]->name : '';
	}

	/**
	 * If 'no garbage collection' is desired/selected, a value is saved as the term.
	 *
	 * @param int  The post id
	 */
	public function disable( $post_id ) {
		return wp_set_post_terms( $post_id, self::DISABLED, self::TAXONOMY_NAME );
	}

	/**
	 * If 'garbage collection' is select, the term is deleted, does not exist, which means 'allow garbage collection'.
	 *
	 * @param int  The post id
	 */
	public function enable( $post_id ) {
		$terms = wp_get_post_terms( $post_id, self::TAXONOMY_NAME );
		if ( $terms ) {
			foreach ( $terms as $term ) {
				wp_remove_object_terms( $post_id, $term->term_id, self::TAXONOMY_NAME );
				wp_delete_term( $term->term_id, self::TAXONOMY_NAME );
			}
		}
	}

	/**
	 * @param integer $number_of_posts  Number of post ids matching criteria.
	 *
	 * @return [int]  Array of post ids
	 */
	public static function getDocumentsByAge( $number_of_posts = 100 ) {
		// $days_ago  Posts older than this many days ago
		$days_ago = get_graphql_setting( 'query_gc_age', null, 'graphql_persisted_queries_section' );
		if ( 1 > $days_ago || ! is_numeric( $days_ago ) ) {
			return [];
		}

		// Query for saved query documents that are older than age and not skipping garbage collection.
		// Get documents where the skip_qc taxonomy term name is not set to 'disabled'.
		$wp_query = new \WP_Query(
			[
				'post_type'      => Document::TYPE_NAME,
				'post_status'    => 'publish',
				'posts_per_page' => $number_of_posts,
				'fields'         => 'ids',
				'date_query'     => [
					[
						'column' => 'post_modified_gmt',
						'before' => $days_ago . ' days ago',
					],
				],
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'      => [
					[
						'taxonomy' => self::TAXONOMY_NAME,
						'field'    => 'name',
						'terms'    => self::DISABLED,
						'operator' => 'NOT IN',
					],
				],
			]
		);

		return $wp_query->get_posts();
	}
}
