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
	 * If 'skip garbage collection' is desired/selected, a value of true/1 is saved as the term.
	 * Otherwise, the term is deleted, does not exist, which means 'do not skip'.
	 *
	 * @param int  The post id
	 */
	public function save( $post_id ) {
		return wp_set_post_terms( $post_id, true, self::TAXONOMY_NAME );
	}

	public function delete( $post_id ) {
		$terms = wp_get_post_terms( $post_id, self::TAXONOMY_NAME );
		if ( $terms ) {
			foreach ( $terms as $term ) {
				wp_remove_object_terms( $post_id, $term->term_id, self::TAXONOMY_NAME );
				wp_delete_term( $term->term_id, self::TAXONOMY_NAME );
			}
		}
	}
}
