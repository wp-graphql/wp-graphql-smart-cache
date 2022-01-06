<?php
/**
 * The max age admin and filter for individual query documents.
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries\Document;

use WPGraphQL\PersistedQueries\Document;
use WPGraphQL\PersistedQueries\Utils;
use GraphQL\Server\RequestError;

class MaxAge {

	const TAXONOMY_NAME = 'graphql_document_http_maxage';

	// The in-progress query
	public $query_id;

	public function init() {
		register_taxonomy(
			self::TAXONOMY_NAME,
			Document::TYPE_NAME,
			[
				'description'        => __( 'HTTP Access-Control-Max-Age Header for a saved GraphQL document', 'wp-graphql-persisted-queries' ),
				'labels'             => [
					'name' => __( 'Max-Age Header', 'wp-graphql-persisted-queries' ),
				],
				'hierarchical'       => false,
				'show_admin_column'  => true,
				'show_in_menu'       => false,
				'show_in_quick_edit' => false,
				'meta_box_cb'        => [ $this, 'admin_input_box_cb' ],
				'show_in_graphql'    => false, // false because we register a field with different name
			]
		);

		add_action( sprintf( 'save_post_%s', Document::TYPE_NAME ), [ $this, 'save_cb' ] );

		add_action(
			'graphql_register_types',
			function () {
				$register_type_name = ucfirst( Document::GRAPHQL_NAME );
				$config             = [
					'type'        => 'Int',
					'description' => __( 'HTTP Access-Control-Max-Age Header for a saved GraphQL document', 'wp-graphql-persisted-queries' ),
				];

				register_graphql_field( 'Create' . $register_type_name . 'Input', 'max_age_header', $config );
				register_graphql_field( 'Update' . $register_type_name . 'Input', 'max_age_header', $config );

				$config['resolve'] = function ( \WPGraphQL\Model\Post $post, $args, $context, $info ) {
					$term = get_the_terms( $post->ID, self::TAXONOMY_NAME );
					return isset( $term[0]->name ) ? $term[0]->name : null;
				};
				register_graphql_field( $register_type_name, 'max_age_header', $config );
			}
		);

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
		add_filter( 'graphql_response_headers_to_send', [ $this, 'http_headers_cb' ], 10, 1 );
		add_filter( 'pre_graphql_execute_request', [ $this, 'peak_at_executing_query_cb' ], 10, 2 );

		add_filter( 'graphql_mutation_input', [ $this, 'graphql_mutation_filter' ], 10, 4 );
		add_action( 'graphql_mutation_response', [ $this, 'graphql_mutation_insert' ], 10, 6 );
	}

	// This runs on post create/update
	// Check the max age value is within limits
	public function graphql_mutation_filter( $input, $context, $info, $mutation_name ) {
		if ( ! in_array( $mutation_name, [ 'createGraphqlDocument', 'updateGraphqlDocument' ], true ) ) {
			return $input;
		}

		if ( ! isset( $input['maxAgeHeader'] ) ) {
			return $input;
		}

		return $input;
	}

	// This runs on post create/update
	// Check the grant allow/deny value is within limits
	public function graphql_mutation_insert( $post_object, $filtered_input, $input, $context, $info, $mutation_name ) {
		if ( ! in_array( $mutation_name, [ 'createGraphqlDocument', 'updateGraphqlDocument' ], true ) ) {
			return;
		}

		if ( ! isset( $filtered_input['maxAgeHeader'] ) || ! isset( $post_object['postObjectId'] ) ) {
			return;
		}

		$this->save( $post_object['postObjectId'], $filtered_input['maxAgeHeader'] );
	}

	/**
	 * Get the max age if it exists for a saved persisted query
	 */
	public function get( $post_id ) {
		$item  = get_the_terms( $post_id, self::TAXONOMY_NAME );
		$value = $item[0]->name ?: null;
		return $value;
	}

	public function valid( $value ) {
		// TODO: terms won't save 0, as considers that empty and removes the term. Consider 'zero' or 'stale' or greater than zero.
		return ( is_numeric( $value ) && $value >= 0 );
	}

	/**
	 * Save the data
	 */
	public function save( $post_id, $value ) {
		if ( ! $this->valid( $value ) ) {
			if ( ! is_admin() ) {
				// Translators: The placeholder is the max-age-header input value
				throw new RequestError( sprintf( __( 'Invalid max age header value "%s". Must be greater than or equal to zero', 'wp-graphql-persisted-queries' ), $value ) );
			}
			// some sort of error?
			return [];
		}
		return wp_set_post_terms( $post_id, $value, self::TAXONOMY_NAME );
	}

	public function peak_at_executing_query_cb( $result, $request ) {
		if ( $request->params->queryId ) {
			$this->query_id = $request->params->queryId;
		} elseif ( $request->params->query ) {
			$this->query_id = Utils::generateHash( $request->params->query );
		}
		return $result;
	}

	public function http_headers_cb( $headers ) {
		$age = null;

		// Look up this specific request query. If found and has an individual max-age setting, use it.
		if ( $this->query_id ) {
			$post = Utils::getPostByTermName( $this->query_id, Document::TYPE_NAME, Document::TAXONOMY_NAME );
			if ( $post ) {
				$age = $this->get( $post->ID );
			}
		}

		if ( null === $age ) {
			// If not, use a global max-age setting if set.
			$age = get_graphql_setting( 'global_max_age', null, 'graphql_persisted_queries_section' );
		}

		// Access-Control-Max-Age header should be zero or positive integer, no decimals.
		if ( $this->valid( $age ) ) {
			$headers['Access-Control-Max-Age'] = intval( $age );
		}
		return $headers;
	}

	/**
	 * Draw the input field for the post edit
	 */
	public function admin_input_box_cb( $post ) {
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

		if ( ! isset( $_POST['post_type'] ) || Document::TYPE_NAME !== $_POST['post_type'] ) {
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
