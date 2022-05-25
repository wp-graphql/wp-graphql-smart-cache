<?php

namespace WPGraphQL\Labs;

use WPGraphQL\Labs\Cache\Collection;

class CacheInvalidationTest extends \Codeception\TestCase\WPTestCase {

	protected $collection;

	public function setUp(): void {
		\WPGraphQL::clear_schema();

		if ( ! defined( 'GRAPHQL_DEBUG' ) ) {
			define( 'GRAPHQL_DEBUG', true );
		}

		$this->collection = new Collection();

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

		$post = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish'
		]);

		$page = self::factory()->post->create([
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

		$actual = $collection->get( 'list:post' );
		$this->assertEmpty( $actual );

		$actual = $collection->get( 'list:page' );
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

		$actual = $collection->get( 'list:post' );
		codecept_debug( [ 'list:post' => $actual ]);
		$this->assertEquals( [ $request_key ], $actual );

		$actual = $collection->get( 'list:page' );
		codecept_debug( [ 'list:page' => $actual ]);
		$this->assertEquals( [ $request_key ], $actual );


		self::factory()->post->update_object( $post, [
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

	// when a post is created as an auto-draft, caches should not be purged
	public function testPostCreatedAsAutoDraftDoesNotPurgeCache() {

		// start with a post
		$mock_post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish'
		]);

		// store data in the post cache
		$this->collection->store_content( 'post', $mock_post_id );
		$this->collection->store_content( $mock_post_id, 'foo' );

		codecept_debug( [ 'get_post' => $this->collection->get( 'post' ) ] );

		// assert that the data is in the post cache
		$this->assertEquals( [ 'foo' ], get_transient( 'gql_cache_' . $mock_post_id ) );
		$this->assertContains( $mock_post_id, $this->collection->get( 'post' ) );

		// this will create an autodraft
		$new_post_id = self::factory()->post->create();

		// the cache should not have been purged
		$this->assertEquals( [ 'foo' ], get_transient( 'gql_cache_' . $mock_post_id ) );
		$this->assertContains( $mock_post_id, $this->collection->get( 'post' ) );

		// cleanup
		wp_delete_post( $mock_post_id, true );
		wp_delete_post( $new_post_id, true );

	}

	// post is published from draft
	public function testPublishingDraftPostInvalidatesListCache() {

		// get a random id to put in the cache
		$random_id = uniqid( 'gql_test:', true );

		// start with an auto-draft post
		$auto_draft_post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'auto-draft'
		]);

		$post_list_query = '
		{
		  posts {
		    nodes {
		      id
		      title
		    }
		  }
		}
		';

		$single_post_query = '
		query GetPost($id:ID!) {
		  post(id:$id idType:DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';

		$single_post_query_variables = [
			'id' => $auto_draft_post_id
		];

		$post_list_query_cache_key = $this->collection->build_key( null, $post_list_query );
		$single_post_query_cache_key = $this->collection->build_key( null, $single_post_query, $single_post_query_variables );

		$post_keys =  $this->collection->get( 'post' );
		$this->assertEmpty( $post_keys );

		$cached_query =  $this->collection->get( $post_list_query_cache_key );
		$this->assertEmpty( $cached_query );

		$cached_query =  $this->collection->get( $single_post_query_cache_key );
		$this->assertEmpty( $cached_query );

		codecept_debug( [
			'before_execute' => $cached_query,
			'post_list_caches' => $this->collection->get( 'list:post' )
		]);

		$post_list_query_results = graphql([
			'query' => $post_list_query
		]);

		// ensure the query executed without errors
		$this->assertArrayNotHasKey( 'errors', $post_list_query_results );
		$this->assertNotEmpty( $post_list_query_results['data'] );

		// assert that the results are cached
		$this->assertNotEmpty( $this->collection->get( $post_list_query_cache_key ) );
		$this->assertSame( $post_list_query_results, $this->collection->get( $post_list_query_cache_key ) );

		$single_post_query_results = graphql([
			'query' => $single_post_query,
			'variables' => $single_post_query_variables
		]);

		codecept_debug( [
			'before_publish' => [
				'single_post_query_results' => $single_post_query_results,
				'post_list_caches' => $this->collection->get( 'list:post' )
			]
		]);

		// ensure the query executed without errors
		$this->assertArrayNotHasKey( 'errors', $single_post_query_results );
		$this->assertNotEmpty( $single_post_query_results['data'] );

		// assert that the results are cached
		$this->assertNotEmpty( $this->collection->get( $single_post_query_cache_key ) );
		$this->assertSame( $single_post_query_results, $this->collection->get( $single_post_query_cache_key ) );


		// publish the auto draft post
		self::factory()->post->update_object( $auto_draft_post_id, [
			'post_status' => 'publish'
		]);

		codecept_debug( [ 'after_publish' => [
			'post_list_query_key' => $post_list_query_cache_key,
			'post_list_query' => $this->collection->get( $post_list_query_cache_key ),
			'post_list_caches' => $this->collection->get( 'list:post' ),
		]]);

		$this->assertSame( $single_post_query_results, $this->collection->get( $single_post_query_cache_key ) );
		$this->assertEmpty( $this->collection->get( $post_list_query_cache_key ) );

//		codecept_debug( [ 'after_publish' => $this->collection->get( 'post' ) ] );
//
//		// publishing a post should invalidate the list cache,
//		$this->assertEmpty( $this->collection->get( 'post' ) );
//
//		// but NOT invalidate the individual cache for the random post id
//		$this->assertEquals( [ 'foo' ], get_transient( 'gql_cache_' . $random_id ) );

		// cleanup
		wp_delete_post( $auto_draft_post_id, true );

	}

	// scheduled post is published

	// published post is changed to draft

	// published post is changed to private

	// published post is trashed

	// published post is force deleted

	// delete draft post (doesnt evoke purge action)

	// trashed post is restored





	// page is created as auto draft

	// page is published from draft

	// published page is changed to draft

	// published page is changed to private

	// published page is trashed

	// published page is force deleted

	// delete draft page (doesnt evoke purge action)

	// trashed page is restored



	// publish first post to a user (user->post connection should purge)

	// delete only post of a user (user->post connection should purge)
	// change only post of a user from publish to draft (user->post connection should purge)

	// change post author (user->post connection should purge)




	// update post meta of draft post does not evoke purge action

	// delete post meta of draft post does not evoke purge action

	// update post meta of published post

	// delete post meta of published post



	// new post types detected?
	// post type removed?
	// new taxonomy added?
	// taxonomy removed?
	// schema breaking change detected?



	// post of publicly queryable/show in graphql cpt is created as auto draft

	// post of publicly queryable/show in graphql cpt is published from draft

	// scheduled post of publicly queryable/show in graphql cpt is published

	// published post of publicly queryable/show in graphql cpt is changed to draft

	// published post of publicly queryable/show in graphql cpt is changed to private

	// published post of publicly queryable/show in graphql cpt is trashed

	// published post of publicly queryable/show in graphql cpt is force deleted

	// delete draft post of publicly queryable/show in graphql post type (doesn't evoke purge action)

	// trashed post of publicly queryable/show in graphql post type



	// post of non-gql post type cpt is created as auto draft

	// post of private cpt is published from draft

	// scheduled post of private cpt is published

	// published post of private cpt is changed to draft

	// published post of private cpt is changed to private

	// published post of private cpt is trashed

	// published post of private cpt is force deleted

	// delete draft post of private post type (doesnt evoke purge action)




	// category term is created

	// category term is updated

	// category term is deleted

	// category term is added to a published post

	// category term is added to a draft post

	// category term is removed from a published post

	// category term is removed from a draft post

	// update category meta

	// delete category meta

	// create child category

	// update child category

	// delete child category



	// tag term is created

	// tag term is updated

	// tag term is deleted

	// tag term is added to a published post

	// tag term is added to a draft post

	// tag term is removed from a published post

	// tag term is removed from a draft post

	// update tag meta

	// delete tag meta




	// custom tax (show_in_graphql) term is created

	// custom tax (show_in_graphql) term is updated

	// custom tax (show_in_graphql) term is deleted

	// custom tax (show_in_graphql) term is added to a published post

	// custom tax (show_in_graphql) term is added to a draft post

	// custom tax (show_in_graphql) term is removed from a published post

	// custom tax (show_in_graphql) term is removed from a draft post

	// update custom tax (show_in_graphql) term meta (of allowed meta key)

	// delete custom tax (show_in_graphql) term meta (of allowed meta key)





	// create user (no purge, not public yet)

	// delete user with no published posts (no purge)

	// delete user without re-assign (what should happen here?)
	// call purge for each post the author was the author of?

	// delete user and re-assign posts
	// - purge user
	// - purge for each post (of each post type) transferred
	// - purge for the new author being assigned

	// update user that has published posts

	// update user meta (with allowed meta key)

	// update user meta (with non-allowed meta key)

	// delete user meta (with allowed meta key)

	// delete user meta (with non-allowed meta key)




	// upload media item

	// update media item

	// delete media item

	// update media item meta

	// delete media item meta




	// create anv menu (doesnt purge)

	// assign nav menu to location (purge)

	// update nav menu (which is assigned to location, should purge)

	// update nav menu (not assigned to a location, no purge)

	// delete menu (assigned to location, purge)

	// delete menu (not assigned to location, no purge)




	// update permalinks (purge all?)




	// update untracked options (no purge)

	// update tracked option (purge group?)

	// set transient doesn't purge (don't want weird infinite loops)

}
