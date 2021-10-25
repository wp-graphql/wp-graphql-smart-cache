<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

class SavedQuery {

	const TYPE_NAME     = 'graphql_query';
	const TAXONOMY_NAME = 'graphql_query_label';

	public function init() {
		$this->register_post_type();
		add_filter( 'graphql_request_data', [ $this, 'filter_request_data' ], 10, 2 );
	}

	public function register_post_type() {
		register_post_type(
			self::TYPE_NAME,
			[
				'labels'      => [
					'name'          => __( 'GraphQLQueries', 'wp-graphql-persisted-queries' ),
					'singular_name' => __( 'GraphQLQuery', 'wp-graphql-persisted-queries' ),
				],
				'description' => __( 'Saved GraphQL queries', 'wp-graphql-persisted-queries' ),
				'public'      => true,
				'show_ui'     => true,
				'taxonomies'  => [
					self::TAXONOMY_NAME,
				],
			]
		);

		register_taxonomy(
			self::TAXONOMY_NAME,
			self::TYPE_NAME,
			[
				'description'        => __( 'Taxonomy for saved GraphQL queries', 'wp-graphql-persisted-queries' ),
				'hierarchical'       => false,
				'labels'             => [
					'name'              => __( 'GraphQL Query Names', 'wp-graphql-persisted-queries' ),
					'singular_name'     => __( 'GraphQL Query Name', 'wp-graphql-persisted-queries' ),
					'search_items'      => __( 'Search Query Names', 'wp-graphql-persisted-queries' ),
					'all_items'         => __( 'All Query Name', 'wp-graphql-persisted-queries' ),
					'parent_item'       => __( 'Parent Query Name', 'wp-graphql-persisted-queries' ),
					'parent_item_colon' => __( 'Parent Query Name:', 'wp-graphql-persisted-queries' ),
					'edit_item'         => __( 'Edit Query Name', 'wp-graphql-persisted-queries' ),
					'update_item'       => __( 'Update Query Name', 'wp-graphql-persisted-queries' ),
					'add_new_item'      => __( 'Add New Query Name', 'wp-graphql-persisted-queries' ),
					'new_item_name'     => __( 'New Query Name', 'wp-graphql-persisted-queries' ),
					'menu_name'         => __( 'GraphQL Query Names', 'wp-graphql-persisted-queries' ),
				],
				'show_ui'            => true,
				'show_in_quick_edit' => false,
			]
		);
		register_taxonomy_for_object_type( self::TAXONOMY_NAME, self::TYPE_NAME );
	}

	/**
	 * Process request looking for when queryid and query are present.
	 * Save the query and remove it from the request
	 *
	 * @param  array $parsed_body_params Request parameters.
	 * @param  array $request_context An array containing the both body and query params
	 * @return string Updated $parsed_body_params Request parameters.
	 */
	public function filter_request_data( $parsed_body_params, $request_context ) {
		if ( isset( $parsed_body_params['query'] ) && isset( $parsed_body_params['queryId'] ) ) {
			// save the query
			$this->save( $parsed_body_params['queryId'], $parsed_body_params['query'] );

			// remove it from process body params so graphql-php operation proceeds without conflict.
			unset( $parsed_body_params['query'] );
		}
		return $parsed_body_params;
	}

	/**
	 * Load a persisted query corresponding to a query ID (hash) or alias/alternate name
	 *
	 * @param  string $query_id Query ID
	 * @return string Query
	 */
	public function get( $query_id ) {
		$post = Utils::getPostByTermId( $query_id, self::TYPE_NAME, self::TAXONOMY_NAME );
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
		$normalized_hash = Utils::generateHash( $query );

		// The normalized query should have one saved object/post by hash as the slug name
		$post = get_page_by_path( $normalized_hash, 'OBJECT', self::TYPE_NAME );
		if ( empty( $post ) ) {
			$ast             = \GraphQL\Language\Parser::parse( $query );
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

			// Upon saving the new persisted query, remove any terms that already exist as aliases
			$term_object = get_term_by( 'name', $normalized_hash, self::TAXONOMY_NAME );
			if ( $term_object ) {
				$r = wp_delete_term( $term_object->term_id, self::TAXONOMY_NAME );
			}
		} else {
			$post_id = $post->ID;
		}

		// Save the term entries for normalized hash and if provided query id is different
		$term_names = [ $normalized_hash ];

		// If provided query_id hash is different than normalized hash, save the term associated with the hierarchy
		if ( $query_id !== $normalized_hash ) {
			$term_names[] = $query_id;
		}

		// Gather the term ids to save with the post
		$term_ids = [];
		foreach ( $term_names as $term_name ) {
			if ( Utils::termExists( $term_name, self::TAXONOMY_NAME ) ) {
				continue;
			}

			// Inserting the term will trigger WP 'clean_term_cache' action
			$term       = wp_insert_term(
				$term_name,
				self::TAXONOMY_NAME,
				[
					'description' => __( 'A graphql query relationship', 'wp-graphql-persisted-queries' ),
				]
			);
			$term_ids[] = $term['term_id'];
		}

		if ( ! empty( $term_ids ) ) {
			// Set terms using wp_add_object_terms instead of wp_insert_post because the user my not have permissions to set terms
			wp_add_object_terms(
				$post_id,
				$term_ids,
				self::TAXONOMY_NAME
			);
		}

		return $post_id;
	}

}
