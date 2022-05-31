<?php

class PostCacheInvalidationTest extends \TestCase\WPGraphQLLabs\TestCase\WPGraphQLLabsTestCase {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testItWorks() {
		$this->assertTrue( true );
	}

//	public function postListQuery() {
//		return '
//		{
//		  posts {
//		    nodes {
//		      __typename
//		      databaseId
//		    }
//		  }
//		}
//		';
//	}
//
//	public function contentNodesQuery() {
//		return '
//		{
//		  contentNodes {
//		    nodes {
//		      __typename
//		      databaseId
//		    }
//		  }
//		}
//		';
//	}
//
//	public function singlePostByDatabaseIdQuery() {
//
//		return '
//			query GetPost($id:ID!) {
//			  post(id:$id idType:DATABASE_ID) {
//				__typename
//				databaseId
//			  }
//			}
//		';
//	}
//
//	// execute several queries to populate the cache and assert that the cache is populated
//	public function populateCache() {
//
//		$queries = [
//			'single_post' => [
//				'query' => $this->singlePostByDatabaseIdQuery(),
//				'variables' => [ $this->post_id ]
//			],
//			'post_list' => [
//				'query' => $this->postListQuery(),
//			],
//			'content_nodes' => [
//				'query' => $this->contentNodesQuery(),
//			],
//			'page' => [
//				'query' => null
//			]
//		];
//
//	}
//
//	/**
//	 * @throws Exception
//	 */
//	public function executeQueryAndGetResults( $query, $variables ) {
//
//		$results = graphql([
//			'query' => $query,
//			'variables' => $variables,
//		]);
//
//		return [
//			'results' => $results,
//			'cacheKey' => $this->collection->build_key( null, $query, $variables ),
//			'query' => $query,
//			'variables' => $variables,
//		];
//	}
//
//	/**
//	 * Test behavior when a scheduled post is published
//	 *
//	 * - given:
//	 *   - a query for a single pre-existing post is in the cache
//	 *   - a query for a list of posts is in the cache
//	 *   - a query for contentNodes is in the cache
//	 *   - a query for a page is in the cache
//	 *   - a query for a list of pages is in the cache
//	 *   - a query for a tag is in the cache
//	 *   - a query for a list of tags is in the cache
//	 *   - a query for a list of users is in the cache
//	 *   - a query for the author of the post is in the cache
//	 *
//	 * - when:
//	 *   - a scheduled post is published
//	 *
//	 * - assert:
//	 *   - query for list of posts is invalidated
//	 *   - query for contentNodes is invalidated
//	 *   - query for single pre-exising post remains cached
//	 *   - query for a page remains cached
//	 *   - query for list of pages remains cached
//	 *   - query for tag remains cached
//	 *   - query for list of tags remains cached
//	 *   - query for list of users remains cached
//	 *   - query for the author of the post remains cached
//	 *
//	 *
//	 * @throws Exception
//	 */
//	public function testScheduledTestIsPublished() {
//
//		// post caches should start empty
//		$post_cache = $this->collection->get( 'post' );
//		$post_list_cache = $this->collection->get( 'list:post' );
//		$this->assertEmpty( $post_cache );
//		$this->assertEmpty( $post_list_cache );
//
//		$scheduled_post_id = self::factory()->post->create([
//			'post_type' => 'post',
//			'post_status' => 'future',
//			'post_title' => 'Test Scheduled Post',
//			'post_author' => $this->admin,
//			'post_date' => date( "Y-m-d H:i:s", strtotime( '+1 day' ) )
//		]);
//
//		// after a future post is created, these caches should still be empty
//		$post_cache = $this->collection->get( 'post' );
//		$post_list_cache = $this->collection->get( 'list:post' );
//		$this->assertEmpty( $post_cache );
//		$this->assertEmpty( $post_list_cache );
//
//		$results = $this->executeQueryAndGetResults(
//			$this->singlePostByDatabaseIdQuery(),
//			[
//				'id' => $scheduled_post_id,
//			]
//		);
//
//		// after executing, the post cache should have data
//		$post_cache = $this->collection->get( 'post' );
//		$post_list_cache = $this->collection->get( 'list:post' );
//		$this->assertNotEmpty( $post_cache );
//		$this->assertEmpty( $post_list_cache );
//
//		$this->assertEquals(
//			$this->collection->get( $results['cacheKey'] ),
//			$results['results']
//		);
//
//		// publish the post
//		wp_publish_post( $scheduled_post_id );
//
//
//
//	}

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
}
