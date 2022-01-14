<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

use WPGraphQL\PersistedQueries\Admin\Settings;
use WPGraphQL\PersistedQueries\AdminErrors;
use WPGraphQL\PersistedQueries\Utils;
use GraphQL\Error\SyntaxError;
use GraphQL\Server\RequestError;

class Document {

	const TYPE_NAME     = 'graphql_document';
	const TAXONOMY_NAME = 'graphql_query_alias';
	const GRAPHQL_NAME  = 'graphqlDocument';

	public function init() {
		add_filter( 'graphql_request_data', [ $this, 'graphql_query_contains_queryid_cb' ], 10, 2 );

		add_action( 'post_updated', [ $this, 'after_updated_cb' ], 10, 3 );

		if ( ! is_admin() ) {
			add_filter( 'wp_insert_post_data', [ $this, 'validate_before_save_cb' ], 10, 2 );
			add_action( sprintf( 'save_post_%s', self::TYPE_NAME ), [ $this, 'save_document_cb' ], 10, 2 );
		}

		add_filter( 'graphql_post_object_insert_post_args', [ $this, 'mutation_filter_post_args' ], 10, 4 );
		add_filter( 'graphql_mutation_input', [ $this, 'graphql_mutation_filter' ], 10, 4 );
		add_action( 'graphql_mutation_response', [ $this, 'graphql_mutation_insert' ], 10, 6 );

		register_post_type(
			self::TYPE_NAME,
			[
				'description'         => __( 'Saved GraphQL queries', 'wp-graphql-persisted-queries' ),
				'labels'              => [
					'name'          => __( 'GraphQLQueries', 'wp-graphql-persisted-queries' ),
					'singular_name' => __( 'GraphQLQuery', 'wp-graphql-persisted-queries' ),
				],
				'public'              => true,
				'show_ui'             => Settings::show_in_admin(),
				'taxonomies'          => [
					self::TAXONOMY_NAME,
				],
				'show_in_graphql'     => true,
				'graphql_single_name' => self::GRAPHQL_NAME,
				'graphql_plural_name' => 'graphqlDocuments',
			]
		);

		register_taxonomy(
			self::TAXONOMY_NAME,
			self::TYPE_NAME,
			[
				'description'        => __( 'Alias names for saved GraphQL queries', 'wp-graphql-persisted-queries' ),
				'hierarchical'       => false,
				'labels'             => [
					'name'          => __( 'Alias Names', 'wp-graphql-persisted-queries' ),
					'singular_name' => __( 'Alias Name', 'wp-graphql-persisted-queries' ),
				],
				'show_admin_column'  => true,
				'show_in_menu'       => Settings::show_in_admin(),
				'show_in_quick_edit' => false,
				'show_in_graphql'    => false, // false because we register a field with different name
			]
		);

		add_action(
			'graphql_register_types',
			function () {
				$register_type_name = ucfirst( self::GRAPHQL_NAME );
				$config             = [
					'type'        => [ 'list_of' => [ 'non_null' => 'String' ] ],
					'description' => __( 'Alias names for saved GraphQL query documents', 'wp-graphql-persisted-queries' ),
				];

				register_graphql_field( 'Create' . $register_type_name . 'Input', 'alias', $config );
				register_graphql_field( 'Update' . $register_type_name . 'Input', 'alias', $config );

				$config['resolve'] = function ( \WPGraphQL\Model\Post $post, $args, $context, $info ) {
					$terms = get_the_terms( $post->ID, self::TAXONOMY_NAME );
					if ( ! is_array( $terms ) ) {
						return [];
					}
					return array_map(
						function ( $term ) {
							return $term->name;
						},
						$terms
					);
				};
				register_graphql_field( $register_type_name, 'alias', $config );
			}
		);
	}

	/**
	 * Run on mutation create/update.
	 */
	public function mutation_filter_post_args( $insert_post_args, $input, $post_type_object, $mutation_name ) {
		if ( in_array( $mutation_name, [ 'createGraphqlDocument', 'updateGraphqlDocument' ], true ) ) {
			$insert_post_args = array_merge( $insert_post_args, $input );
		}
		return $insert_post_args;
	}

	// This runs on post create/update
	// Insert/Update the alias name. Make sure it is unique
	public function graphql_mutation_filter( $input, $context, $info, $mutation_name ) {
		if ( ! in_array( $mutation_name, [ 'createGraphqlDocument', 'updateGraphqlDocument' ], true ) ) {
			return $input;
		}

		if ( ! isset( $input['alias'] ) ) {
			return $input;
		}

		// If the create/update a document, see if any of these aliases already exist
		$existing_post = Utils::getPostByTermName( $input['alias'], self::TYPE_NAME, self::TAXONOMY_NAME );
		if ( $existing_post ) {
			// Translators: The placeholders are the input aliases and the existing post containing a matching alias
			throw new RequestError( sprintf( __( 'Alias "%1$s" already in use by another query "%2$s"', 'wp-graphql-persisted-queries' ), join( ', ', $input['alias'] ), $existing_post->post_title ) );
		}

		// Make sure the normalized hash for the query string isset.
		$input['alias'][] = Utils::generateHash( $input['content'] );

		return $input;
	}

	public function graphql_mutation_insert( $post_object, $filtered_input, $input, $context, $info, $mutation_name ) {
		if ( ! in_array( $mutation_name, [ 'createGraphqlDocument', 'updateGraphqlDocument' ], true ) ) {
			return;
		}

		if ( ! isset( $filtered_input['alias'] ) || ! isset( $post_object['postObjectId'] ) ) {
			return;
		}

		// Remove the existing/old alias terms
		$terms = wp_get_post_terms( $post_object['postObjectId'], self::TAXONOMY_NAME, [ 'fields' => 'names' ] );
		if ( $terms ) {
			wp_remove_object_terms( $post_object['postObjectId'], $terms, self::TAXONOMY_NAME );
		}

		wp_set_post_terms( $post_object['postObjectId'], $filtered_input['alias'], self::TAXONOMY_NAME );
	}

	/**
	 * Process request looking for when queryid and query are present.
	 * Save the query and remove it from the request
	 *
	 * @param  array $parsed_body_params Request parameters.
	 * @param  array $request_context An array containing the both body and query params
	 * @return string Updated $parsed_body_params Request parameters.
	 */
	public function graphql_query_contains_queryid_cb( $parsed_body_params, $request_context ) {
		if ( isset( $parsed_body_params['query'] ) && isset( $parsed_body_params['queryId'] ) ) {
			// save the query
			$this->save( $parsed_body_params['queryId'], $parsed_body_params['query'] );

			// remove it from process body params so graphql-php operation proceeds without conflict.
			unset( $parsed_body_params['query'] );
		}
		return $parsed_body_params;
	}

	/**
	 * If existing post is edited, verify query string in content is valid graphql
	 */
	public function validate_before_save_cb( $data, $post ) {
		if ( self::TYPE_NAME !== $post['post_type'] ) {
			return $data;
		}

		/**
		 * Before post is saved, check content for valid graphql.
		 */
		if ( array_key_exists( 'post_content', $data ) &&
			! empty( $data['post_content'] ) ) {
			try {
				// Use graphql parser to check query string validity.
				$ast = \GraphQL\Language\Parser::parse( $post['post_content'] );

				// Get post using the normalized hash of the query string. If not valid graphql, throws syntax error
				$normalized_hash = Utils::generateHash( $ast );

				// If queryId alias name is already in the system and doesn't match the query hash
				$existing_post = Utils::getPostByTermName( $normalized_hash, self::TYPE_NAME, self::TAXONOMY_NAME );
				if ( $existing_post && $existing_post->ID !== $post['ID'] ) {
					// Translators: The placeholder is the existing saved query with matching hash/query-id
					throw new RequestError( sprintf( __( 'This query has already been associated with another query "%s"', 'wp-graphql-persisted-queries' ), $existing_post->post_title ) );
				}

				// Format the query string and save that
				$data['post_content'] = \GraphQL\Language\Printer::doPrint( $ast );
			} catch ( SyntaxError $e ) {
				// Translators: The placeholder is the query string content
				throw new RequestError( sprintf( __( 'Did not save invalid graphql query string "%s"', 'wp-graphql-persisted-queries' ), $post['post_content'] ) );
			}
		}
		return $data;
	}

	/**
	 * When wp_insert_post saves the query, update the slug to match the content.
	 *
	 * @param int $post_ID
	 * @param WP_Post $post
	 */
	public function save_document_cb( $post_ID, $post ) {
		if ( empty( $post->post_content ) ) {
			return;
		}

		// Use graphql parser to check query string validity.
		// @throws on syntax error
		\GraphQL\Language\Parser::parse( $post->post_content );

		// Get the query id for the new query and save as a term
		// Verify the post content is valid graphql query document
		$query_id = Utils::generateHash( $post->post_content );

		// Set terms using wp_add_object_terms instead of wp_insert_post because the user my not have permissions to set terms
		wp_add_object_terms( $post_ID, $query_id, self::TAXONOMY_NAME );
	}

	/**
	 * If existing post is edited in the wp admin editor, use previous content to remove query term ids
	 */
	public function after_updated_cb( $post_ID, $post_after, $post_before ) {
		if ( self::TYPE_NAME !== $post_before->post_type ) {
			return;
		}

		// If the same hash, the query content hasn't changed, do not remove it.
		if ( $post_before->post_content === $post_after->post_content ) {
			return;
		}

		// Use graphql parser to check query string validity.
		try {
			// Get the existing normalized hash for this post and remove it before build a new on, only if the query has changed.
			$old_query_id = Utils::generateHash( $post_before->post_content );
		} catch ( SyntaxError $e ) {
			// syntax error in the old query, nothing to do here.
			return;
		}

		// If the old query string hash is assigned to this post, don't delete it
		$terms = wp_get_post_terms( $post_ID, self::TAXONOMY_NAME, [ 'fields' => 'names' ] );
		if ( in_array( $old_query_id, $terms, true ) ) {
			wp_remove_object_terms( $post_ID, $old_query_id, self::TAXONOMY_NAME );
		}
	}

	/**
	 * Load a persisted query corresponding to a query ID (hash) or alias/alternate name
	 *
	 * @param  string $query_id Query ID
	 * @return string Query
	 */
	public function get( $query_id ) {
		$post = Utils::getPostByTermName( $query_id, self::TYPE_NAME, self::TAXONOMY_NAME );
		if ( false === $post || empty( $post->post_content ) ) {
			return;
		}

		return $post->post_content;
	}

	/**
	 * Save a query by query ID (hash) or alias/alternate name
	 *
	 * @param  string $query_id Query string str256 hash
	 */
	public function save( $query_id, $query ) {
		// Get post using the normalized hash of the query string
		$ast             = \GraphQL\Language\Parser::parse( $query );
		$query           = \GraphQL\Language\Printer::doPrint( $ast );
		$normalized_hash = Utils::getHashFromFormattedString( $query );

		// If queryId alias name is already in the system and doesn't match the query hash
		$post = Utils::getPostByTermName( $query_id, self::TYPE_NAME, self::TAXONOMY_NAME );
		if ( $post && $post->post_name !== $normalized_hash ) {
			// translators: existing query title
			throw new RequestError( sprintf( __( 'This queryId has already been associated with another query "%s"', 'wp-graphql-persisted-queries' ), $post->post_title ) );
		}

		// If the normalized query is associated with a saved document
		$post = Utils::getPostByTermName( $normalized_hash, self::TYPE_NAME, self::TAXONOMY_NAME );
		if ( empty( $post ) ) {
			$query_operation = \GraphQL\Utils\AST::getOperationAST( $ast );

			$operation_names = [];

			$definition_count = $ast->definitions->count();
			for ( $i = 0; $i < $definition_count; $i++ ) {
				$node              = $ast->definitions->offsetGet( $i );
				$operation_names[] = isset( $node->name->value ) ? $node->name->value : __( 'A Persisted Query', 'wp-graphql-persisted-queries' );
			}
			$data = [
				'post_content' => \GraphQL\Language\Printer::doPrint( $ast ),
				'post_name'    => $normalized_hash,
				'post_title'   => join( ', ', $operation_names ),
				'post_status'  => 'publish',
				'post_type'    => self::TYPE_NAME,
			];

			// The post ID on success. The value 0 or WP_Error on failure.
			$post_id = wp_insert_post( $data );
			if ( is_wp_error( $post ) ) {
				// throw some error?
				return;
			}
		} elseif ( $query !== $post->post_content ) {
			// If the hash for the query string loads a post with a different query string,
			// This means this hash was previously used as an alias for a query
			// translators: existing query title
			throw new RequestError( sprintf( __( 'This query has already been associated with another query "%s"', 'wp-graphql-persisted-queries' ), $post->post_title ) );
		} else {
			$post_id = $post->ID;
		}

		// Save the term entries for normalized hash and if provided query id is different
		$term_names = [ $normalized_hash ];

		// If provided query_id hash is different than normalized hash, save the term associated with the hierarchy
		if ( $query_id !== $normalized_hash ) {
			$term_names[] = $query_id;
		}

		// Set terms using wp_add_object_terms instead of wp_insert_post because the user my not have permissions to set terms
		wp_add_object_terms( $post_id, $term_names, self::TAXONOMY_NAME );

		return $post_id;
	}

}
