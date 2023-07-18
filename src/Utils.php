<?php
/**
 * Content
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache;

class Utils {

	/**
	 * @param string $query_id Query ID
	 *
	 * @return WP_Post
	 */
	public static function getPostByTermName( $query_id, $type, $taxonomy ) {
		$wp_query = new \WP_Query(
			[
				'post_type'      => $type,
				'post_status'    => 'any',
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
	 * @param string | \GraphQL\Language\AST\DocumentNode query string or document node
	 *
	 * @return string $query_id Query string str256 hash
	 *
	 * @throws \GraphQL\Error\SyntaxError
	 */
	public static function generateHash( $query ) {
		if ( is_string( $query ) ) {
			$query = \GraphQL\Language\Parser::parse( $query );
		}
		$printed = \GraphQL\Language\Printer::doPrint( $query );

		return self::getHashFromFormattedString( $printed );
	}

	/**
	 * Generate query hash for graphql query string
	 *
	 * @param string Formatted, normalized query string
	 *
	 * @return string $query_id Query string str256 hash
	 *
	 * @throws \GraphQL\Error\SyntaxError
	 */
	public static function getHashFromFormattedString( $query ) {
		return hash( 'sha256', $query );
	}
}
