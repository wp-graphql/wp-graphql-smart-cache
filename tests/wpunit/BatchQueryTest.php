<?php

namespace WPGraphQL\Cache;

use WPGraphQL\SmartCache\Cache\Results;
use WPGraphQL\SmartCache\Document;

/**
 * Test graphql batch request with local caching enabled.
 * Verify the result nodes are saved to the results map memory/transients.
 */
class BatchQueryTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function _before() {
		delete_option( 'graphql_cache_section' );

		// Create/save persisted query for the query and query id
		// The uniqid manes it's different between test runs, in case something fails and is stuck in database.
		$this->query_alias = uniqid( "query_posts_" );
		$query_string = sprintf( "query %s { posts { nodes { id title } } }", $this->query_alias );

		$saved_query = new Document();
		$this->created_post_id = $saved_query->save( $this->query_alias, $query_string );
		codecept_debug( "$this->created_post_id, $this->query_alias, $query_string" );
	}

	public function _after() {
		delete_option( 'graphql_cache_section' );
		wp_delete_post( $this->created_post_id );
	}

	public function testBatchQueryIsCached() {
		// Enable the local cache transient cache for these tests
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		// Create a published post for our queries
		self::factory()->post->create( [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'foo',
			'post_content' => 'foo bar. biz bang.',
			'post_name'    => 'foo-slug',
		] );

		// Test saved/persisted query.
		$query_string = sprintf( "query %s { posts { nodes { uri id databaseId } } }", $this->query_alias );
		$query = 
			[
				[	"queryId" => $this->query_alias ],
				[	"query" => $query_string ],
				[	"queryId" => $this->query_alias ]
			]
		;

		$response_data = graphql( $query );
		codecept_debug( $response_data );

		$this->assertEquals( 'foo', $response_data[0]['data']['posts']['nodes'][0]['title'] );
		$this->assertEquals( $response_data[0]['data']['posts']['nodes'][0]['id'], $response_data[1]['data']['posts']['nodes'][0]['id'] );
		$this->assertEquals( 'foo', $response_data[2]['data']['posts']['nodes'][0]['title'] );

		// Verify the smart cache debug return data
		$results_cache = new Results();
		$this->assertEmpty( $response_data[0]['extensions']['graphqlSmartCache']['graphqlObjectCache'] );
		$this->assertEmpty( $response_data[1]['extensions']['graphqlSmartCache']['graphqlObjectCache'] );
		$this->assertEquals( $results_cache->the_results_key( $this->query_alias, null ), $response_data[2]['extensions']['graphqlSmartCache']['graphqlObjectCache']['cacheKey'] );

		// After the batch query, each query should have an entry saved in transient results cache.
		$key = $results_cache->the_results_key( $this->query_alias, null );
		$query_alias_cache = $results_cache->get( $key );
		codecept_debug( "Cache for $key $this->query_alias");
		codecept_debug( $query_alias_cache );
		$this->assertEquals( 'foo', $query_alias_cache['data']['posts']['nodes'][0]['title'] );

		$key = $results_cache->the_results_key( null, $query_string );
		$query_string_cache = $results_cache->get( $key );
		codecept_debug( "Cache for $key $query_string");
		codecept_debug( $query_string_cache );
		$this->assertEquals( $query_alias_cache['data']['posts']['nodes'][0]['id'], $query_string_cache['data']['posts']['nodes'][0]['id'] );
	}

}
