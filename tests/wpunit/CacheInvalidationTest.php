<?php

namespace WPGraphQL\Labs;

use WPGraphQL\Labs\Cache\Collection;

class CacheInvalidationTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		\WPGraphQL::clear_schema();

		// enable caching for the whole test suite
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		parent::setUp();
	}

	public function tearDown(): void {
		\WPGraphQL::clear_schema();

		// disable caching
		delete_option( 'graphql_cache_section' );
		parent::tearDown();
	}

	// given posts of different publicly queryable post types
	// I should be able to query them using a contentNodes query
	// executing the query a 2nd time should give me cached results
	// when I publish a new post of any of these post types
	// the query should be invalidated
	public function testContentNodesQueryInvalidatesWhenPostOfPublicPostTypeIsPublished() {

		$post = $this->factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish'
		]);

		$page = $this->factory()->post->create([
			'post_type' => 'page',
			'post_status' => 'publish'
		]);

		$query = '
		{
		  contentNodes {
		    nodes {
		      id
		      __typename
		    }
		  }
		}
		';

		$collection = new Collection();

		$request_key = $collection->build_key( null, $query );

		codecept_debug( [ 'request_key' => $request_key ]);

		$actual = $collection->get( 'post' );
		$this->assertEmpty( $actual );

		$actual = $collection->get( 'page' );
		$this->assertEmpty( $actual );

		$cached_query = $collection->get( $request_key );
		codecept_debug( [ 'before_execute' => $cached_query ]);

		$this->assertEmpty( $cached_query );

		// execute here
		$actual = graphql([
			'query' => $query
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['contentNodes']['nodes'] );

		$cached_query = $collection->get( $request_key );
		codecept_debug( [ 'after_execute' => $cached_query ] );
		// there should be cached data for the query
		$this->assertNotEmpty( $cached_query );

		$actual = $collection->get( 'post' );
		codecept_debug( [ 'post' => $actual ]);
		$this->assertEquals( [ $request_key ], $actual );

		$actual = $collection->get( 'page' );
		codecept_debug( [ 'page' => $actual ]);
		$this->assertEquals( [ $request_key ], $actual );


		$this->factory->post->update_object( $post, [
			'post_title' => 'updated title'
		]);

		// the cached query should be gone now
		$cached_query = $collection->get( $request_key );
		$this->assertEmpty( $cached_query );

		wp_delete_post( $page, true );
		wp_delete_post( $post, true );

	}

	// given a published post
	// query for the published post
	// the cache should be populated
	// publishing a new post should not invalidate the cache
	public function testPublishNewPostDoesNotInvalidateQueryForSinglePost() {

		$post_id = $this->factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish'
		]);


		$query = '
		query getPostByDatabaseId( $id: ID! ) {
		  post( id: $id idType: DATABASE_ID ) {
		    __typename
		    id
		    databaseId
		  }
		}
		';

		$variables = [
			'id' => $post_id
		];

		$collection = new Collection();

		// assert the cache is empty
		$request_key = $collection->build_key( null, $query, $variables );

		codecept_debug( [ 'request_key' => $request_key ]);

		$post_keys = $collection->get( 'post' );
		$this->assertEmpty( $post_keys );

		$cached_query = $collection->get( $request_key );
		codecept_debug( [ 'before_execute' => $cached_query ]);

		$query_results = graphql([
			'query' => $query,
			'variables' => $variables
		]);

		$this->assertArrayNotHasKey('errors', $query_results );

		// assert the cache is populated
		$cached_query = $collection->get( $request_key );
		codecept_debug( [ 'after_execute' => $cached_query ] );

		// there should be cached data for the query
		$this->assertNotEmpty( $cached_query );
		$this->assertSame( $query_results, $cached_query );

		$post_keys = $collection->get( 'post' );
		codecept_debug( [ 'post' => $post_keys ]);
		$this->assertEquals( [ $request_key ], $post_keys );

		// create a new post as draft
		$new_post_id = $this->factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'draft'
		]);

		// publish the post
		$this->factory()->post->update_object( $new_post_id, [
			'post_status' => 'publish',
		]);

		// there should still be cached data for the query for a single post
		// @todo: currently a transition of a post to the publish status purges all queries associated with the "post" key
		// assert the cache is populated
		$after_publish_cached_query = $collection->get( $request_key );
		codecept_debug( [ 'after_publish' => $after_publish_cached_query ] );

		$this->assertNotEmpty( $after_publish_cached_query );
		$this->assertSame( $query_results, $after_publish_cached_query );

		// cleanup
		wp_delete_post( $post_id, true );
		wp_delete_post( $new_post_id, true );

	}

}
