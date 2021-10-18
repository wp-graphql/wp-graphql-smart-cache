<?php
/**
 * Save the persisted query description text in the post type excpert field.
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

class SavedQueryDescription {

	public function init() {
		add_post_type_support( 'graphql_query', 'excerpt' );

		add_filter( 'manage_graphql_query_posts_columns', [ $this, 'filter_add_description_to_admin' ], 10, 1);
		add_action( 'manage_graphql_query_posts_custom_column', [ $this, 'action_fill_excerpt_content' ], 10, 2);
		add_filter( 'manage_edit-graphql_query_sortable_columns', [ $this, 'filter_make_excerpt_column_sortable_in_admin' ], 10, 1 );
		add_action( 'add_meta_boxes_graphql_query', [ $this, 'action_the_excerpt_admin_meta_box' ], 10, 1);
	}

	/**
	 * Enable excerpt as the description.
	 */
	public function filter_add_description_to_admin( $columns ) {
		// Use 'description' as the text the user sees
		$columns['excerpt'] = __( 'Description', 'wp-graphql-persisted-queries' );
		return $columns;
	}

	public function action_fill_excerpt_content( $column, $post_id ) {
		if ( 'excerpt' === $column ) {
			echo get_the_excerpt( $post_id );
		}
	  }

	public function filter_make_excerpt_column_sortable_in_admin( $columns ) {
		$columns['excerpt'] = true;
		return $columns;
	}

	public function action_the_post_excerpt_admin_meta_box( $post ) {
		global $wp_meta_boxes;
	
		$page = 'graphql_query';
		$context = 'normal';
		$priority = 'core';
		$id = 'postexcerpt';

		if ( isset( $wp_meta_boxes[ $page ][ $context ][ $priority ][ $id ] ) ) {
			$wp_meta_boxes[ $page ][ $context ][ $priority ][ $id ]['title'] = __('Description', 'wp-graphql-persisted-queries');
		}
	}
}
