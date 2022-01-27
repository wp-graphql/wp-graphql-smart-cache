<?php

namespace WPGraphQL\Cache;

use WPGraphQL\Cache\Query;
use WPGraphQL\PersistedQueries\Document;
use WPGraphQL\PersistedQueries\Utils;

/**
 * Test the content class
 */
class CachedQueryTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		delete_option( 'graphql_cache_section' );
	}

	public function _after() {
		delete_option( 'graphql_cache_section' );
	}

	/**
	 * Put content in the cache.
	 * Make graphql request.
	 * Very we see the results from cache.
	 */
	public function testGetResultsFromCache() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";

		$cache_object = new Query();
		$key = $cache_object->get_cache_key( null, $query );

		// Put something in the cache for the query key that proves it came from cache.
		$expected = [
			'data' => [
				'__typename' => 'Foo Bar'
			]
		];
		$cache_object->save( $key, $expected );

		// Verify the response contains what we put in cache
		$response = graphql([ 'query' => $query ]);
		$this->assertEquals($expected['data'], $response['data']);
	}

	public function testOperationNameAndVariablesGetResultsFromCache() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$query = "query GetPosts(\$count:Int){
			posts(first:\$count){
			 nodes{
			  id
			  title
			 }
			}
		  }
		  query GetPostsWithSlug(\$count:Int){
			posts(first:\$count){
			 nodes{
			  id
			  title
			  slug
			 }
			}
		  }
		";

		$cache_object = new Query();

		// Cache for one operation and variables
		$key = $cache_object->get_cache_key( null, $query, [ "count" => 1 ], "GetPosts" );
		$value = [
			'data' => [
				'foo' => 'Response for GetPosts. Count 1'
			]
		];
		$cache_object->save( $key, $value );

		// Cache for one operation and variables
		$key = $cache_object->get_cache_key( null, $query, [ "count" => 2 ], "GetPosts" );
		$value = [
			'data' => [
				'foo' => 'Response for GetPosts. Count 2'
			]
		];
		$cache_object->save( $key, $value );

		// Cache for one operation and variables
		$key = $cache_object->get_cache_key( null, $query, [ "count" => 2 ], "GetPostsWithSlug" );
		$value = [
			'data' => [
				'foo' => 'Response for GetPostsWithSlug. Count 2'
			]
		];
		$cache_object->save( $key, $value );

		// Verify the response contains what we put in cache
		$response = graphql([
			'query' => $query,
			'variables' => [ "count" => 1 ],
			'operationName' => 'GetPosts'
		]);
		$this->assertEquals( 'Response for GetPosts. Count 1', $response['data']['foo'] );

		$response = graphql([
			'query' => $query,
			'variables' => [ "count" => 2 ],
			'operationName' => 'GetPosts'
		]);
		$this->assertEquals( 'Response for GetPosts. Count 2', $response['data']['foo'] );

		$response = graphql([
			'query' => $query,
			'variables' => [ "count" => 2 ],
			'operationName' => 'GetPostsWithSlug'
		]);
		$this->assertEquals( 'Response for GetPostsWithSlug. Count 2', $response['data']['foo'] );

	}

	public function testQueryIdGetResultsFromCache() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";
		$query_id = "foo-bar-query";

		// Create/save persisted query for the query and query id
		$saved_query = new Document();
		$saved_query->save( $query_id, $query );

		$cache_object = new Query();
		$key = $cache_object->get_cache_key( $query_id, null );

		// Put something in the cache for the query key that proves it came from cache.
		$expected = [
			'data' => [
				'__typename' => 'Foo Bar'
			]
		];
		$cache_object->save( $key, $expected );

		// Verify the response contains what we put in cache
		$response = graphql([ 'queryId' => $query_id ]);
		$this->assertEquals($expected['data'], $response['data']);
	}

	public function testPurgeCacheWhenNotEnabled() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'off' ] );

		$cache_object = new Query();
		$response = $cache_object->purge();
		$this->assertFalse( $response );
	}

	public function testPurgeCacheWhenNothingCached() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$cache_object = new Query();
		$response = $cache_object->purge();
		$this->assertFalse( $response );
	}

	public function testPurgeCache() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$cache_object = new Query();

		// Put something in the cache for the query key that proves it came from cache.
		$query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";
		$key = $cache_object->get_cache_key( null, $query );
		$expected = [
			'data' => [
				'__typename' => 'Foo Bar'
			]
		];
		$cache_object->save( $key, $expected );

		// Query that we got from cache
		$response = graphql([ 'query' => $query ]);
		$this->assertEquals($expected['data'], $response['data']);

		// Clear the cache
		$this->assertEquals( $cache_object->purge(), 1 );

		$real = [
			'data' => [
				'posts' => [
					'nodes' => []
				]
			]
		];
		$response = graphql([ 'query' => $query ]);
		$this->assertEquals($real['data'], $response['data']);
	}

	/**
	 * Set the global ttl setting.
	 * Make graphql request.
	 * Verifyy we see the results from cache.
	 * Verify we see transient expiration set.
	 */
	public function testExpirationTtlIsSetForCachedResults() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on', 'global_ttl' => '30' ] );


		$query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";

		// Thought, capture a before and after time around the graphql query. Add the ttl seconds to each and make sure the 
		// transient timeout is between the two inclusively.
		$cache_object = new Query();
		$key = $cache_object->get_cache_key( null, $query );
		$time_before = time();
		$response = graphql([ 'query' => $query ]);
		$time_after = time();

		$this->assertArrayHasKey( 'data', $response );
		$transient_timeout_option = get_option( '_transient_timeout_' . $key );
		$this->assertNotEmpty( $transient_timeout_option );

		$this->assertGreaterThanOrEqual( $time_before + 30, $transient_timeout_option );
		$this->assertLessThanOrEqual( $time_after + 30, $transient_timeout_option );
	}

}
