<?php
/**
 * Class SampleTest
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

/**
 * Sample test case.
 */
class SampleTest extends \PHPUnit\Framework\TestCase {

	/**
	 * A single example test.
	 */
	public function test_sample() {
		return '{
			contentNodes {
				nodes {
					uri
				}
			}
		}';
		// Replace this with some actual testing code.
		$this->assertTrue( true );
	}
}
