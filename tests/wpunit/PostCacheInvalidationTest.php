<?php

class PostCacheInvalidationTest extends \TestCase\WPGraphQLLabs\TestCase\WPGraphQLLabsTestCaseWithSeedDataAndPopulatedCaches {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testItWorks() {
		$this->assertTrue( true );
	}

	/**
	 * Test behavior when an auto-draft post is created
	 *
	 * - given:
	 *   - a query for a single pre-existing post is in the cache
	 *   - a query for a list of posts is in the cache
	 *   - a query for contentNodes is in the cache
	 *   - a query for a page is in the cache
	 *   - a query for a list of pages is in the cache
	 *   - a query for a tag is in the cache
	 *   - a query for a list of tags is in the cache
	 *   - a query for a list of users is in the cache
	 *   - a query for the author of the post is in the cache
	 *
	 * - when:
	 *   - a scheduled post is published
	 *
	 * - assert:
	 *   - query for list of posts remains cached
	 *   - query for contentNodes remains cached
	 *   - query for single pre-exising post remains cached
	 *   - query for a page remains cached
	 *   - query for list of pages remains cached
	 *   - query for tag remains cached
	 *   - query for list of tags remains cached
	 *   - query for list of users remains cached
	 *   - query for the author of the post remains cached
	 *
	 *
	 * @throws Exception
	 */
	public function testCreateDraftPostDoesNotInvalidatePostCache() {

		// all queries should be in the cache, non should be empty
		$this->assertEmpty( $this->getEvictedCaches() );

		// create an auto draft post
		self::factory()->post->create([
			'post_status' => 'auto-draft'
		]);

		// after creating an auto-draft post, there should be no caches that were emptied
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	/**
	 * Test behavior when a scheduled post is published
	 *
	 * - given:
	 *   - a query for a single pre-existing post is in the cache
	 *   - a query for a list of posts is in the cache
	 *   - a query for contentNodes is in the cache
	 *   - a query for a page is in the cache
	 *   - a query for a list of pages is in the cache
	 *   - a query for a tag is in the cache
	 *   - a query for a list of tags is in the cache
	 *   - a query for a list of users is in the cache
	 *   - a query for the author of the post is in the cache
	 *
	 * - when:
	 *   - a scheduled post is published
	 *
	 * - assert:
	 *   - query for list of posts is invalidated
	 *   - query for contentNodes is invalidated
	 *   - query for single pre-exising post remains cached
	 *   - query for a page remains cached
	 *   - query for list of pages remains cached
	 *   - query for tag remains cached
	 *   - query for list of tags remains cached
	 *   - query for list of users remains cached
	 *   - query for the author of the post remains cached
	 *
	 * @throws Exception
	 */
	public function testPublishingScheduledPostWithoutAssociatedTerm() {

		// ensure WordPress doesn't set a default category when publishing the post
		update_option( 'default_category', 0 );

		// ensure all queries have a cache
		$this->assertEmpty( $this->getEvictedCaches() );

		// create a scheduled post
		$scheduled_post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'future',
			'post_title' => 'Test Scheduled Post',
			'post_author' => $this->admin,
			'post_date' => date( "Y-m-d H:i:s", strtotime( '+1 day' ) ),
		]);

		wp_publish_post( $scheduled_post_id );

		codecept_debug( [ 'empty_after_publish' => $this->getEvictedCaches() ]);

		$emptied_caches = $this->getEvictedCaches();

		// when publishing a scheduled post, the listPost and listContentNode queries should have been cleared
		$this->assertContains( 'listPost', $emptied_caches );
		$this->assertContains( 'listContentNode', $emptied_caches );

		// Ensure that other caches have not been emptied
		$this->assertNotContains( 'listTag', $emptied_caches );
		$this->assertNotContains( 'listCategory', $emptied_caches );

	}

	/**
	 * Test behavior when a scheduled post (that has a category assigned to it) is published
	 *
	 * - given:
	 *   - a query for a single pre-existing post is in the cache
	 *   - a query for a list of posts is in the cache
	 *   - a query for contentNodes is in the cache
	 *   - a query for a page is in the cache
	 *   - a query for a list of pages is in the cache
	 *   - a query for a tag is in the cache
	 *   - a query for a list of tags is in the cache
	 *   - a query for a list of users is in the cache
	 *   - a query for the author of the post is in the cache
	 *
	 * - when:
	 *   - a scheduled post is published
	 *
	 * - assert:
	 *   - query for list of posts is invalidated
	 *   - query for contentNodes is invalidated
	 *   - query for list of categories is invalidated
	 *   - query for single category is invalidated
	 *   - query for single pre-exising post remains cached
	 *   - query for a page remains cached
	 *   - query for list of pages remains cached
	 *   - query for tag remains cached
	 *   - query for list of tags remains cached
	 *   - query for list of users remains cached
	 *   - query for the author of the post remains cached
	 *
	 * @throws Exception
	 */
	public function testPublishingScheduledPostWithCategoryAssigned() {

		// ensure all queries have a cache
		$this->assertEmpty( $this->getEvictedCaches() );

		// the single category query should be in the cache
		$this->assertNotEmpty( $this->collection->get( $this->query_results['singleCategory']['cacheKey'] ) );

		// create a scheduled post
		$scheduled_post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'future',
			'post_title' => 'Test Scheduled Post',
			'post_author' => $this->admin,
			'post_date' => date( "Y-m-d H:i:s", strtotime( '+1 day' ) ),
			'tax_input' => [
				'category' => [ $this->category->term_id ],
			],
		]);

		wp_publish_post( $scheduled_post_id );

		codecept_debug( [ 'empty_after_publish' => $this->getEvictedCaches() ]);

		$evicted_caches = $this->getEvictedCaches();

		// when publishing a scheduled post, the listPost and listContentNode queries should have been cleared
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'listContentNode', $evicted_caches );

		codecept_debug([
			'singleCategory' => $this->query_results['singleCategory']
		]);

		// we're also asserting that we cleared the "listCategory" cache because
		// a category in the list was updated
		// by being assigned to this post
		$this->assertEmpty( $this->collection->get( $this->query_results['listCategory']['cacheKey'] ) );

		codecept_debug(
			[
				'cleared_nodes' => $this->collection->retrieve_nodes( $this->toRelayId( 'term', $this->category->term_id ) ),
				'singleCategory' => $this->query_results['singleCategory'],
				'cachedResults' => $this->collection->get( $this->query_results['singleCategory']['cacheKey'] )
			]
		);

		// the single category query should no longer be in the cache because a post was published that
		// was associated with the category
		$this->assertEmpty( $this->collection->get( $this->query_results['singleCategory']['cacheKey'] ) );




		// Ensure that other caches have not been emptied
		$this->assertNotContains( 'listTag', $evicted_caches );

	}

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
