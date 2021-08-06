<?php
/**
 * Class SampleTest
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

use WPGraphQL\PersistedQueries\Content;

/**
 * Test the content class
 */
class ContentUnitTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Test graphql queries match hash, even with white space differences
	 */
	public function test_queries_with_whitespace_differences_are_same_hash() {
		$query = "{\n  contentNodes {\n    nodes {\n      uri\n    }\n  }\n}\n";

		$query_compact = '{ contentNodes { nodes { uri } } }';

		$query_pretty = '{
			contentNodes {
				nodes {
					uri
				}
			}
		}';

		$content = new Content();
		$query_hash = $content->generateHash( $query );

		$this->assertTrue( $content->verifyHash( $query_hash, $query_compact ) );
		$this->assertTrue( $content->verifyHash( $query_hash, $query_pretty ) );
	}

	/**
	 * Test graphql query with invalid string throws error
	 */
	public function test_query_hash_with_invalid_string() {
		$this->expectException( \GraphQL\Error\SyntaxError::class );
		$invalid_query = "{\n  contentNodes {\n    nodes {\n      uri";

		$content = new Content();
		// @throws SyntaxError
		$content->generateHash( $invalid_query );
	}

	/**
	 * Test graphql query with invalid string throws error
	 */
	public function test_query_verify_with_invalid_string() {
		$this->expectException( \GraphQL\Error\SyntaxError::class );
		$invalid_query = "{\n  contentNodes {\n    nodes {\n      uri";

		$content = new Content();
		// @throws SyntaxError
		$content->verifyHash( '1234', $invalid_query );
	}
}