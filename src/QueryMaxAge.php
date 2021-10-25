<?php
/**
 * The max age admin and filter for individual querys
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

class QueryMaxAge {

	const TAXONOMY_NAME = 'graphql_query_http_maxage';

	// The in progress query
	public $query_id;

	public function init() {
		register_taxonomy(
			self::TAXONOMY_NAME,
			SavedQuery::TYPE_NAME,
			[
				'description'        => __( 'HTTP Access-Control-Max-Age Header for a saved GraphQL query', 'wp-graphql-persisted-queries' ),
				'labels'             => [
					'name' => __( 'Max-Age Header', 'wp-graphql-persisted-queries' ),
				],
				'hierarchical'       => false,
				'show_admin_column'  => true,
				'show_in_menu'       => false,
				'show_in_quick_edit' => false,
				'meta_box_cb'        => [ $this, 'admin_input_box' ],
			]
		);

		add_action( sprintf( 'save_post_%s', SavedQuery::TYPE_NAME ), [ $this, 'save_cb' ] );

		// Add to the wp-graphql admin settings page
		add_action(
			'graphql_register_settings',
			function () {
				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'              => 'global_max_age',
						'label'             => __( 'Access-Control-Max-Age Header', 'wp-graphql-persisted-queries' ),
						'desc'              => __( 'Global Max-Age HTTP header. Integer value, greater or equal to zero.', 'wp-graphql-persisted-queries' ),
						'type'              => 'number',
						'sanitize_callback' => function ( $value ) {
							if ( $value < 0 || ! is_numeric( $value ) ) {
								return 0;
							}
							return intval( $value );
						},
					]
				);
			}
		);

		// From WPGraphql Router
		add_filter( 'graphql_response_headers_to_send', [ $this, 'filter_response_headers' ], 10, 1 );
		add_filter( 'pre_graphql_execute_request', [ $this, 'filter_what_is_the_query_id' ], 10, 2 );
	}

	/**
	 * Get the max age if it exists for a saved persisted query
	 */
	public function get( $post_id ) {
		$item  = wp_get_object_terms( $post_id, self::TAXONOMY_NAME );
		$value = $item[0]->name ?: null;
		return $value;
	}

	/**
	 * Save the data
	 */
	public function save( $post_id, $value ) {
		if ( ! is_numeric( $value ) || 0 > $value ) {
			// some sort of error?
			return [];
		}
		return wp_set_post_terms( $post_id, $value, self::TAXONOMY_NAME );
	}

	public function filter_what_is_the_query_id( $result, $request ) {
		if ( $request->params->queryId ) {
			$this->query_id = $request->params->queryId;
		} elseif ( $request->params->query ) {
			$this->query_id = Utils::generateHash( $request->params->query );
		}
		return $result;
	}

	public function filter_response_headers( $headers ) {
		$age = null;

		// Look up this specific request query. If found and has an individual max-age setting, use it.
		if ( $this->query_id ) {
			$post = Utils::getPostByTermId( $this->query_id, SavedQuery::TYPE_NAME, SavedQuery::TAXONOMY_NAME );
			if ( $post ) {
				$age = $this->get( $post->ID );
			}
		}

		if ( null === $age ) {
			// If not, use a global max-age setting if set.
			$age = get_graphql_setting( 'global_max_age', null, 'graphql_persisted_queries_section' );
		}

		// Access-Control-Max-Age header should be zero or positive integer, no decimals.
		if ( is_numeric( $age ) && $age >= 0 ) {
			$headers['Access-Control-Max-Age'] = intval( $age );
		}
		return $headers;
	}

	/**
	 * Draw the input field for the post edit
	 */
	public function admin_input_box( $post ) {
		wp_nonce_field( 'graphql_query_maxage', 'savedquery_maxage_noncename' );

		$value = $this->get( $post->ID );
		$html  = sprintf( '<input type="text" id="graphql_query_maxage" name="graphql_query_maxage" value="%s" />', $value );
		$html .= '<br><label for="graphql_query_maxage">Max-Age HTTP header. Integer value.</label>';
		echo wp_kses(
			$html,
			[
				'input' => [
					'type'  => true,
					'id'    => true,
					'name'  => true,
					'value' => true,
				],
				'br'    => [],
			]
		);
	}

	/**
	 * When a post is saved, sanitize and store the data.
	 */
	public function save_cb( $post_id ) {
		if ( empty( $_POST ) ) {
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

		if ( ! isset( $_REQUEST['savedquery_maxage_noncename'] ) ) {
			return;
		}

		// phpcs:ignore
		if ( ! wp_verify_nonce( $_REQUEST['savedquery_maxage_noncename'], 'graphql_query_maxage' ) ) {
			return;
		}

		if ( ! isset( $_POST['graphql_query_maxage'] ) ) {
			return;
		}

		$data = sanitize_text_field( wp_unslash( $_POST['graphql_query_maxage'] ) );

		$this->save( $post_id, $data );
	}

}
