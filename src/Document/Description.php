<?php
/**
 * Save the persisted query description text in the post type excpert field.
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries\Document;

use WPGraphQL\PersistedQueries\Document;

class Description {

	public function init() {
		// Enable excerpts for the persisted query post type
		add_post_type_support( Document::TYPE_NAME, 'excerpt' );

		// Change the text from Excerpt to Description where it is visible.
		add_filter( 'gettext', [ $this, 'translate_excerpt_text_cb' ], 10, 1 );

		add_filter( sprintf( 'manage_%s_posts_columns', Document::TYPE_NAME ), [ $this, 'add_description_column_to_admin_cb' ], 10, 1 );
		add_action( sprintf( 'manage_%s_posts_custom_column', Document::TYPE_NAME ), [ $this, 'fill_excerpt_content_cb' ], 10, 2 );
		add_filter( sprintf( 'manage_edit-%s_sortable_columns', Document::TYPE_NAME ), [ $this, 'make_excerpt_column_sortable_in_admin_cb' ], 10, 1 );
	}

	/**
	 * Change the text from Excerpt to Description where it is visible.
	 *
	 * @param String  The string for the __() or _e() translation
	 * @return String  The translated or original string
	 */
	public function translate_excerpt_text_cb( $string ) {
		$post = get_post();
		if ( $post && Document::TYPE_NAME === $post->post_type ) {
			if ( 'Excerpt' === $string ) {
				return __( 'Description', 'wp-graphql-persisted-queries' );
			}
			if ( 'Excerpts are optional hand-crafted summaries of your content that can be used in your theme. <a href="%s">Learn more about manual excerpts</a>.' === $string ) {
				return __( 'Add the query description.', 'wp-graphql-persisted-queries' );
			}
		}
		return $string;
	}

	/**
	 * Enable excerpt as the description.
	 */
	public function add_description_column_to_admin_cb( $columns ) {
		// Use 'description' as the text the user sees
		$columns['excerpt'] = __( 'Description', 'wp-graphql-persisted-queries' );
		return $columns;
	}

	public function fill_excerpt_content_cb( $column, $post_id ) {
		if ( 'excerpt' === $column ) {
			echo esc_html( get_the_excerpt( $post_id ) );
		}
	}

	public function make_excerpt_column_sortable_in_admin_cb( $columns ) {
		$columns['excerpt'] = true;
		return $columns;
	}

}
