<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

use WPGraphQL\PersistedQueries\AdminErrors;
use WPGraphQL\PersistedQueries\Utils;
use GraphQL\Error\SyntaxError;
use GraphQL\Server\RequestError;

class Document {

	const TYPE_NAME     = 'graphql_document';
	const TAXONOMY_NAME = 'graphql_query_alias';

	public function init() {
		add_filter( 'graphql_request_data', [ $this, 'graphql_request_save_document_cb' ], 10, 2 );

		add_filter( 'wp_insert_post_data', [ $this, 'editor_validate_save_data_cb' ], 10, 2 );
		add_action( 'post_updated', [ $this, 'editor_update_before_save_cb' ], 10, 3 );
		add_action( sprintf( 'save_post_%s', Document::TYPE_NAME ), [ $this, 'editor_save_document_cb' ], 10, 3 );

		register_post_type(
			self::TYPE_NAME,
			[
				'description' => __( 'Saved GraphQL queries', 'wp-graphql-persisted-queries' ),
				'labels'      => [
					'name'          => __( 'GraphQLQueries', 'wp-graphql-persisted-queries' ),
					'singular_name' => __( 'GraphQLQuery', 'wp-graphql-persisted-queries' ),
				],
				'public'      => true,
				'show_ui'     => true,
				'taxonomies'  => [
					self::TAXONOMY_NAME,
				],
				'show_in_graphql' => true,
				'graphql_single_name' => 'document',
				'graphql_plural_name' => 'documents',
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
				'show_in_menu'       => false,
				'show_in_quick_edit' => false,
				'meta_box_cb'        => [ $this, 'admin_input_box_cb' ],
				'show_in_graphql' => true,
				'graphql_single_name' => 'alias',
				'graphql_plural_name' => 'alias',
			]
		);
	}

	/**
	 * Process request looking for when queryid and query are present.
	 * Save the query and remove it from the request
	 *
	 * @param  array $parsed_body_params Request parameters.
	 * @param  array $request_context An array containing the both body and query params
	 * @return string Updated $parsed_body_params Request parameters.
	 */
	public function graphql_request_save_document_cb( $parsed_body_params, $request_context ) {
		if ( isset( $parsed_body_params['query'] ) && isset( $parsed_body_params['queryId'] ) ) {
			// save the query
			$this->save( $parsed_body_params['queryId'], $parsed_body_params['query'] );

			// remove it from process body params so graphql-php operation proceeds without conflict.
			unset( $parsed_body_params['query'] );
		}
		return $parsed_body_params;
	}

	/**
	 * If existing post is edited in the wp admin editor, verify query string in content is valid graphql
	 */
	public function editor_validate_save_data_cb( $data, $post ) {
		/**
		 * Before post is saved, check content for valid graphql.
		 */
		if ( ! empty( $data ) &&
			! empty( $data['post_content'] ) &&
			self::TYPE_NAME === $post['post_type'] ) {
			try {
				// Use graphql parser to check query string validity.
				$ast = \GraphQL\Language\Parser::parse( $post['post_content'] );
				// Format the query string and save that
				$data['post_content'] = \GraphQL\Language\Printer::doPrint( $ast );
			} catch ( SyntaxError $e ) {
				$existing_post = get_post( $post['ID'] );

				// Overwrite new/invalid query with previous working query, or empty
				$data['post_content'] = $existing_post->post_content;

				AdminErrors::add_message( 'Did not save invalid graphql query string. ' . $post['post_content'] );
			}
		}
		return $data;
	}

	/**
	 * When query is saved in the wp admin editor, save the query, update the slug to match the content.
	 *
	 * @param int $post_ID
	 * @param WP_Post $post
	 * @param bool $update
	 */
	public function editor_save_document_cb( $post_ID, $post, $update ) {
		if ( empty( $post->post_content ) ) {
			return;
		}

		// Use graphql parser to check query string validity.
		try {
			\GraphQL\Language\Parser::parse( $post->post_content );
		} catch ( SyntaxError $e ) {
			AdminErrors::add_message( 'Did not save invalid graphql query string. ' . $post['post_content'] );
			return;
		}

		// Get the query id for the new query and save as a term
		// Verify the post content is valid graphql query document
		$query_id = Utils::generateHash( $post->post_content );

		// Set terms using wp_add_object_terms instead of wp_insert_post because the user my not have permissions to set terms
		wp_add_object_terms( $post_ID, $query_id, self::TAXONOMY_NAME );
	}

	/**
	 * If existing post is edited in the wp admin editor, use previous content to remove query term ids
	 */
	public function editor_update_before_save_cb( $post_ID, $post_after, $post_before ) {
		if ( Document::TYPE_NAME !== $post_before->post_type ) {
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

	/**
	 * Draw the input field for the post edit
	 */
	public function admin_input_box_cb( $post ) {
		wp_nonce_field( 'graphql_query_grant', '_document_noncename' );

		$html  = '<ul>';
		$terms = get_the_terms( $post, self::TAXONOMY_NAME );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$string = mb_strimwidth( $term->name, 0, 30, '...' );
				$html  .= "<li>$string</li>";
			}
		}
		$html .= '</ul>';
		$html .= __( 'The aliases associated with this graphql document. Use in a graphql request as the queryId={} parameter', 'wp-graphql-persisted-queries' );
		echo wp_kses(
			$html,
			[
				'ul' => true,
				'li' => true,
			]
		);
	}

}
