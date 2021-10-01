<?php

namespace WPGraphQL\PersistedQueries;

use WPGraphQL\PersistedQueries\Utils;

/**
 * Test the content class
 */
class CachedResponseTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Put content in the cache.
	 * Make graphql request.
	 * Very we see the results from cache.
	 * Check the number of filters run.
	 */
	public function testGetResultsFromCache() {
		$query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";

		$cache_object = new CachedResponse();
		$key = $cache_object->get_cache_key( null, $query );

		// Put something in the cache for the query key that proves it came from cache.
		$expected = [
			'data' => [
				'__typename' => 'Foo Bar'
			]
		];
		set_transient( $key, $expected );

		// Verify the response contains what we put in cache
		$response = graphql([ 'query' => $query ]);
		$this->assertEquals($expected['data'], $response['data']);
	}

	public function testOperationNameAndVariablesGetResultsFromCache() {
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

		$cache_object = new CachedResponse();

		// Cache for one operation and variables
		$key = $cache_object->get_cache_key( null, $query, [ "count" => 1 ], "GetPosts" );
		$value = [
			'data' => [
				'foo' => 'Response for GetPosts. Count 1'
			]
		];
		set_transient( $key, $value );

		// Cache for one operation and variables
		$key = $cache_object->get_cache_key( null, $query, [ "count" => 2 ], "GetPosts" );
		$value = [
			'data' => [
				'foo' => 'Response for GetPosts. Count 2'
			]
		];
		set_transient( $key, $value );

		// Cache for one operation and variables
		$key = $cache_object->get_cache_key( null, $query, [ "count" => 2 ], "GetPostsWithSlug" );
		$value = [
			'data' => [
				'foo' => 'Response for GetPostsWithSlug. Count 2'
			]
		];
		set_transient( $key, $value );

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
}
