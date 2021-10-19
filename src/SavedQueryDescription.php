<?php
/**
 * Save the persisted query description text in the post type excpert field.
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

class SavedQueryDescription {

	public function init() {
		// Enable excerpts for the persisted query post type
		add_post_type_support( SavedQuery::TYPE_NAME, 'excerpt' );

		// Change the text from Excerpt to Description where it is visible.
		add_filter( 'gettext', [ $this, 'filter_translate_excerpt_text' ], 10, 1 );

		add_filter( sprintf( 'manage_%s_posts_columns', SavedQuery::TYPE_NAME ), [ $this, 'filter_add_description_to_admin' ], 10, 1 );
		add_action( sprintf( 'manage_%s_posts_custom_column', SavedQuery::TYPE_NAME ), [ $this, 'action_fill_excerpt_content' ], 10, 2 );
		add_filter( sprintf( 'manage_edit-%s_sortable_columns', SavedQuery::TYPE_NAME ), [ $this, 'filter_make_excerpt_column_sortable_in_admin' ], 10, 1 );
	}

	/**
	 * Change the text from Excerpt to Description where it is visible.
	 *
	 * @param String  The string for the __() or _e() translation
	 * @return String  The translated or original string
	 */
	public function filter_translate_excerpt_text( $string ) {
		if ( 'Excerpt' === $string ) {
			return __( 'Description', 'wp-graphql-persisted-queries' );
		}
		if ( 'Excerpts are optional hand-crafted summaries of your content that can be used in your theme. <a href="%s">Learn more about manual excerpts</a>.' === $string ) {
			return __( 'Add the query description.', 'wp-graphql-persisted-queries' );
		}
		return $string;
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
			echo esc_html( get_the_excerpt( $post_id ) );
		}
	}

	public function filter_make_excerpt_column_sortable_in_admin( $columns ) {
		$columns['excerpt'] = true;
		return $columns;
	}

}
