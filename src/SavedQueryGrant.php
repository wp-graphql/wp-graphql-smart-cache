<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

class SavedQueryGrant {

	const TAXONOMY_NAME = 'graphql_query_grant';

	const ALLOW = 'allow';
	const DENY  = 'deny';

	public function init() {
		register_taxonomy(
			self::TAXONOMY_NAME,
			SavedQuery::TYPE_NAME,
			[
				'description'       => __( 'Allow/Deny access grant for a saved GraphQL query', 'wp-graphql-persisted-queries' ),
				'labels'      => [
					'name'          => __( 'Allow/Deny', 'wp-graphql-persisted-queries' ),
				],
				'hierarchical'      => false,
				'show_admin_column' => true,
				'show_in_menu'      => false,
				'show_in_quick_edit'=> false,
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
		wp_nonce_field( 'graphql_query_grant', 'savedquery_grant_noncename' );

		$item  = wp_get_object_terms( $post->ID, self::TAXONOMY_NAME );
		$value = $item[0]->name;
		$html  = sprintf(
			'<input type="checkbox" id="graphql_query_grant" name="graphql_query_grant" value="%s" %s>',
			self::ALLOW,
			checked( $value, self::ALLOW, false )
		);
		$html  .= '<label for="graphql_query_grant">Allowed?</label>';
		echo $html;
	}

	/**
	 * Use during processing of submitted form if value of selected input field is selected.
	 * And return value of the taxonomy.
	 * 
	 * @param string The input form value
	 * @return string The string value used to save as the taxonomy value
	 */
	public function the_selection( $value ) {
		return ( self::ALLOW === $_POST['graphql_query_grant'] ) ? self::ALLOW : null;
	}

	/**
	 * When a post is saved, sanitize and store the data.
	 */
	public function save_cb( $post_id ) {
		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! check_admin_referer( 'graphql_query_grant', 'savedquery_grant_noncename') ) {
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

		$data = $this->the_selection( $_POST['graphql_query_grant'] );

		// Save the data
		wp_set_post_terms( $post_id, $data, self::TAXONOMY_NAME );
	}

}
