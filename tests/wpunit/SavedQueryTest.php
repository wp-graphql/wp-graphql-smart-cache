<?php
/**
 * Class SampleTest
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

use WPGraphQL\PersistedQueries\SavedQuery;

/**
 * Test the content class
 */
class SavedQueryUnitTest extends \Codeception\TestCase\WPTestCase {

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

		$content = new SavedQuery();
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

		$content = new SavedQuery();
		// @throws SyntaxError
		$content->generateHash( $invalid_query );
	}

	/**
	 * Test graphql query with invalid string throws error
	 */
	public function test_query_verify_with_invalid_string() {
		$this->expectException( \GraphQL\Error\SyntaxError::class );
		$invalid_query = "{\n  contentNodes {\n    nodes {\n      uri";

		$content = new SavedQuery();
		// @throws SyntaxError
		$content->verifyHash( '1234', $invalid_query );
	}

	public function test_boolean_term_exists_false() {
		$content = new SavedQuery();
		$this->assertFalse( $content->termExists( 'foo123' ) );
	}

	public function test_boolean_term_exists_true() {
		wp_insert_term( 'foo123', 'graphql_query_label' );
		$content = new SavedQuery();
		$this->assertTrue( $content->termExists( 'foo123' ) );
		wp_delete_term( 'foo123', 'graphql_query_label' );
	}
}
