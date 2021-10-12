<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

class SavedQueryDescription {

	const TAXONOMY_NAME = 'graphql_query_description';

	public function init() {
		register_taxonomy(
			self::TAXONOMY_NAME,
			SavedQuery::TYPE_NAME,
			[
				'description'       => __( 'Description for a saved GraphQL query', 'wp-graphql-persisted-queries' ),
				'labels'            => [
					'name' => __( 'Description', 'wp-graphql-persisted-queries' ),
				],
				'hierarchical'      => false,
				'show_admin_column' => true,
				'show_in_menu'      => false,
				'meta_box_cb'       => [ $this, 'admin_input_box' ],
			]
		);

		if ( is_admin() ) {
			add_action( 'save_post', [ $this, 'save_cb' ] );
		}
	}

	/**
	 * Draw the input field for the post edit
	 */
	public function admin_input_box( $post ) {
		wp_nonce_field( 'graphql_query_description', 'savedquery_description_noncename' );

		$descriptions = wp_get_object_terms( $post->ID, self::TAXONOMY_NAME );
		$html         = '<textarea name="graphql_query_description" id="graphql_query_description" style="width:100%;">';
		if ( count( $descriptions ) ) {
			$html .= esc_attr( $descriptions[0]->name );
		}
		$html .= '</textarea>';
		echo wp_kses_post( $html );
	}

	/**
	 * When a post is saved, sanitize and store the data.
	 */
	public function save_cb( $post_id ) {
		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! check_admin_referer( 'graphql_query_description', 'savedquery_description_noncename') ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['post_type'] ) || SavedQuery::TYPE_NAME !== $_POST['post_type'] ) {
			return;
		}

		if ( ! isset( $_POST['graphql_query_description'] ) ) {
			return;
		}

		$data = sanitize_text_field( sanitize_text_field( wp_unslash( $_POST['graphql_query_description'] ) ) );

		// Save the data
		wp_set_post_terms( $post_id, $data, self::TAXONOMY_NAME );
	}

}
