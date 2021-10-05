<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

class Utils {

	/**
	 * @param  string $query_id Query ID
	 * @return WP_Post
	 */
	public static function getPostByTermId( $query_id, $type, $taxonomy ) {
		$wp_query = new \WP_Query(
			[
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'      => [
					[
						'taxonomy' => $taxonomy,
						'field'    => 'name',
						'terms'    => $query_id,
					],
				],
			]
		);
		$posts    = $wp_query->get_posts();
		if ( empty( $posts ) ) {
			return false;
		}

		$post = array_pop( $posts );
		if ( ! $post->ID ) {
			return false;
		}

		return $post;
	}

	/**
	 * Generate query hash for graphql query string
	 *
	 * @param  string Query
	 * @return string $query_id Query string str256 hash
	 *
	 * @throws \GraphQL\Error\SyntaxError
	 */
	public static function generateHash( $query ) {
		$ast     = \GraphQL\Language\Parser::parse( $query );
		$printed = \GraphQL\Language\Printer::doPrint( $ast );
		return hash( 'sha256', $printed );
	}

	/**
	 * Verify the query_id matches the query hash
	 *
	 * @param  string $query_id Query string str256 hash
	 * @param  string Query
	 * @return boolean
	 *
	 * @throws \GraphQL\Error\SyntaxError
	 */
	public static function verifyHash( $query_id, $query ) {
		return self::generateHash( $query ) === $query_id;
	}

	/**
	 * Query taxonomy terms for existance of provided name/alias.
	 *
	 * @param  string   Query name/alias
	 * @return boolean  If term for the taxonomy already exists
	 */
	public static function termExists( $name, $taxonomy ) {
		$query = new \WP_Term_Query(
			[
				'taxonomy' => $taxonomy,
				'fields'   => 'names',
				'get'      => 'all',
			]
		);
		$terms = $query->get_terms();
		return in_array( $name, $terms, true );
	}

}
