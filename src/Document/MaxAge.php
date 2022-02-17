<?php
/**
 * The max age admin and filter for individual query documents.
 *
 * @package Wp_Graphql_Labs
 */

namespace WPGraphQL\Labs\Document;

use WPGraphQL\Labs\Admin\Settings;
use WPGraphQL\Labs\Document;
use WPGraphQL\Labs\Utils;
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
				'description'        => __( 'HTTP Access-Control-Max-Age Header for a saved GraphQL document', 'wp-graphql-labs' ),
				'labels'             => [
					'name' => __( 'Max-Age Header', 'wp-graphql-labs' ),
				],
				'hierarchical'       => false,
				'show_admin_column'  => true,
				'show_in_menu'       => Settings::show_in_admin(),
				'show_in_quick_edit' => false,
				'meta_box_cb'        => [
					'WPGraphQL\Labs\Admin\Editor',
					'maxage_input_box_cb',
				],
				'show_in_graphql'    => false,
				// false because we register a field with different name
			]
		);

		add_action(
			'graphql_register_types',
			function () {
				$register_type_name = ucfirst( Document::GRAPHQL_NAME );
				$config             = [
					'type'        => 'Int',
					'description' => __( 'HTTP Access-Control-Max-Age Header for a saved GraphQL document', 'wp-graphql-labs' ),
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

		// From WPGraphql Router
		add_filter( 'graphql_response_headers_to_send', [ $this, 'http_headers_cb' ], 10, 1 );
		add_filter( 'pre_graphql_execute_request', [ $this, 'peak_at_executing_query_cb' ], 10, 2 );

		add_filter( 'graphql_mutation_input', [ $this, 'graphql_mutation_filter' ], 10, 4 );
		add_action( 'graphql_mutation_response', [ $this, 'graphql_mutation_insert' ], 10, 6 );
	}

	// This runs on post create/update
	// Check the max age value is within limits
	public function graphql_mutation_filter( $input, $context, $info, $mutation_name ) {
		if ( ! in_array(
			$mutation_name,
			[
				'createGraphqlDocument',
				'updateGraphqlDocument',
			],
			true
		) ) {
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
		if ( ! in_array(
			$mutation_name,
			[
				'createGraphqlDocument',
				'updateGraphqlDocument',
			],
			true
		) ) {
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
		$item = get_the_terms( $post_id, self::TAXONOMY_NAME );
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		return isset( $item[0]->name ) ? $item[0]->name : null;
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
			// Translators: The placeholder is the max-age-header input value
			throw new RequestError( sprintf( __( 'Invalid max age header value "%s". Must be greater than or equal to zero', 'wp-graphql-labs' ), $value ) );
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
			$post = Utils::getPostByTermName( $this->query_id, Document::TYPE_NAME, Document::ALIAS_TAXONOMY_NAME );
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
}
