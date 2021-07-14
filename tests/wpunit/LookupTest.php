<?php

use WPGraphQL\PersistedQueries\Lookup;

class LookupTest extends \Codeception\TestCase\WPTestCase {

    public function setUp(): void
    {
      parent::setUp();
    }

    public function tearDown(): void
    {
      parent::tearDown();
    }

	// insert hash, verify it's there
	// save hash, verify it's there
	// not found error on hash not there
	// error when hash and query do not match on save
	// curl or graphql get by queryid

	/**
	 * Tests the access function that is available to format strings to GraphQL friendly format
	 */
	public function test_query_lookup_fails() {

		$query = 'http://localhost:8091/graphql?queryId=1234';
		$expected = 'thisIsSomeFieldName';

		$this->amOnUrl($query);
		//$this->assertEquals( $expected, $actual );

		//$this->assertEquals( false, true );

	}

//	assertWPError
//	assertNotWPError


}
