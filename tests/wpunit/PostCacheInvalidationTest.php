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

		// ensure all queries have a cache
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish the scheduled post
		wp_publish_post( $this->scheduled_post );

		// get the evicted caches
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

		// publish the post
		wp_publish_post( $this->scheduled_post_with_category->ID );

		codecept_debug( [ 'empty_after_publish' => wp_get_object_terms( $this->scheduled_post_with_category->ID, 'category' ) ]);

		// get the evicted caches _after_ publish
		$evicted_caches = $this->getEvictedCaches();

		// when publishing a scheduled post with an associated category,
		// the listPost and listContentNode queries should have been cleared
		// but also the listCategory and singleCategory as the termCount
		// needs to be updated on the terms
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'listContentNode', $evicted_caches );
		$this->assertContains( 'listCategory', $evicted_caches );
		$this->assertContains( 'singleCategory', $evicted_caches );


		// we're also asserting that we cleared the "listCategory" cache because
		// a category in the list was updated
		// by being assigned to this post
		$this->assertEmpty( $this->collection->get( $this->query_results['listCategory']['cacheKey'] ) );

		// the single category query should no longer be in the cache because a post was published that
		// was associated with the category
		$this->assertEmpty( $this->collection->get( $this->query_results['singleCategory']['cacheKey'] ) );


		// ensure the other caches remain cached
		$this->assertNotContains( 'singleTag', $evicted_caches );
		$this->assertNotContains( 'listTag', $evicted_caches );

		// Ensure that other caches have not been emptied
		$this->assertNotContains( 'listTag', $evicted_caches );

	}

	// published post is changed to draft
	public function testPublishedPostIsChangedToDraft() {

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'draft'
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );
		$this->assertContains( 'singlePost', $evicted_caches );
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'singleContentNode', $evicted_caches );
		$this->assertContains( 'singleNodeById', $evicted_caches );
		$this->assertContains( 'singleNodeByUri', $evicted_caches );

	}

	public function testPublishedPostWithCategoryIsChangedToDraft() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'draft',
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		// the post was unpublished, so the singlePost query should be evicted
		$this->assertContains( 'singlePost', $evicted_caches );

		// the post that was unpublished was part of the list, and should be evicted
		$this->assertContains( 'listPost', $evicted_caches );

		// the post was the single content node and should be evicted
		$this->assertContains( 'singleContentNode', $evicted_caches );

		// the post was part of the content node list and should be evicted
		$this->assertContains( 'listContentNode', $evicted_caches );

		// the post was the single node by id and should be evicted
		$this->assertContains( 'singleNodeById', $evicted_caches );

		// the post was the single node by uri and should be evicted
		$this->assertContains( 'singleNodeByUri', $evicted_caches );

		// the post had a category assigned, so the category list should be evicted
		$this->assertContains( 'listCategory', $evicted_caches );

		// the single category should be evicted as its post count has changed
		$this->assertContains( 'singleCategory', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'listPage', $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'singlePage', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}


	// published post is changed to private
	public function testPublishPostChangedToPrivate() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'private'
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );
		$this->assertContains( 'singlePost', $evicted_caches );
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'singleContentNode', $evicted_caches );
		$this->assertContains( 'singleNodeById', $evicted_caches );
		$this->assertContains( 'singleNodeByUri', $evicted_caches );
	}

	public function testPublishedPostWithCategoryIsChangedToPrivate() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// purge all caches (since we just added a term to a published post and we want to start in a clean state again)
		$this->collection->purge_all();

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'private',
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		// the post was unpublished, so the singlePost query should be evicted
		$this->assertContains( 'singlePost', $evicted_caches );

		// the post that was unpublished was part of the list, and should be evicted
		$this->assertContains( 'listPost', $evicted_caches );

		// the post was the single content node and should be evicted
		$this->assertContains( 'singleContentNode', $evicted_caches );

		// the post was part of the content node list and should be evicted
		$this->assertContains( 'listContentNode', $evicted_caches );

		// the post was the single node by id and should be evicted
		$this->assertContains( 'singleNodeById', $evicted_caches );

		// the post was the single node by uri and should be evicted
		$this->assertContains( 'singleNodeByUri', $evicted_caches );

		// the post had a category assigned, so the category list should be evicted
		$this->assertContains( 'listCategory', $evicted_caches );

		// the single category should be evicted as its post count has changed
		$this->assertContains( 'singleCategory', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'listPage', $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'singlePage', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}
	// published post is trashed
	public function testPublishPostIsTrashed() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'trash'
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );
		$this->assertContains( 'singlePost', $evicted_caches );
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'singleContentNode', $evicted_caches );
		$this->assertContains( 'singleNodeById', $evicted_caches );
		$this->assertContains( 'singleNodeByUri', $evicted_caches );
	}

	public function testPublishedPostWithCategoryIsTrashed() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// purge all caches (since we just added a term to a published post and we want to start in a clean state again)
		$this->collection->purge_all();

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'trash',
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		// the post was unpublished, so the singlePost query should be evicted
		$this->assertContains( 'singlePost', $evicted_caches );

		// the post that was unpublished was part of the list, and should be evicted
		$this->assertContains( 'listPost', $evicted_caches );

		// the post was the single content node and should be evicted
		$this->assertContains( 'singleContentNode', $evicted_caches );

		// the post was part of the content node list and should be evicted
		$this->assertContains( 'listContentNode', $evicted_caches );

		// the post was the single node by id and should be evicted
		$this->assertContains( 'singleNodeById', $evicted_caches );

		// the post was the single node by uri and should be evicted
		$this->assertContains( 'singleNodeByUri', $evicted_caches );

		// the post had a category assigned, so the category list should be evicted
		$this->assertContains( 'listCategory', $evicted_caches );

		// the single category should be evicted as its post count has changed
		$this->assertContains( 'singleCategory', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'listPage', $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'singlePage', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}

	// published post is force deleted
	public function testPublishPostIsForceDeleted() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_post( $this->published_post->ID, true );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );
		$this->assertContains( 'singlePost', $evicted_caches );
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'singleContentNode', $evicted_caches );
		$this->assertContains( 'singleNodeById', $evicted_caches );
		$this->assertContains( 'singleNodeByUri', $evicted_caches );
	}

	public function testPublishedPostWithCategoryIsForceDeleted() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// purge all caches (since we just added a term to a published post and we want to start in a clean state again)
		$this->collection->purge_all();

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// force delete the post
		wp_delete_post( $this->published_post->ID, true );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		// the post was unpublished, so the singlePost query should be evicted
		$this->assertContains( 'singlePost', $evicted_caches );

		// the post that was unpublished was part of the list, and should be evicted
		$this->assertContains( 'listPost', $evicted_caches );

		// the post was the single content node and should be evicted
		$this->assertContains( 'singleContentNode', $evicted_caches );

		// the post was part of the content node list and should be evicted
		$this->assertContains( 'listContentNode', $evicted_caches );

		// the post was the single node by id and should be evicted
		$this->assertContains( 'singleNodeById', $evicted_caches );

		// the post was the single node by uri and should be evicted
		$this->assertContains( 'singleNodeByUri', $evicted_caches );

		// the post had a category assigned, so the category list should be evicted
		$this->assertContains( 'listCategory', $evicted_caches );

		// the single category should be evicted as its post count has changed
		$this->assertContains( 'singleCategory', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'listPage', $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'singlePage', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}

	// delete draft post (doesnt evoke purge action)
	public function testDraftPostIsForceDeleted() {

		// no caches should be evicted to start
		$non_evicted_caches_before_delete = $this->getNonEvictedCaches();
		$this->assertEmpty( $this->getEvictedCaches() );

		// delete the draft post
		// this shouldn't evict any caches as the draft post shouldn't
		// be in the cache in the first place
		wp_delete_post( $this->draft_post->ID, true );

		// assert that caches have been evicted
		// as a draft post shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before_delete, $this->getNonEvictedCaches() );
	}

	// trashed post is restored
	public function testTrashedPostIsRestored() {

		// ensure we have no evicted caches to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// trash a post
		wp_trash_post( $this->draft_post->ID );

		// trashing the draft post shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish the trashed post
		wp_publish_post( $this->draft_post->ID );

		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );
		$this->assertNotEmpty( $non_evicted_caches );

		codecept_debug( [
			'evicted' => $evicted_caches,
			'non' => $non_evicted_caches
		]);

		// publishing a post should evict the listContentNode cache
		$this->assertContains( 'listContentNode', $evicted_caches );

		// publishing a post should evict the listPost cache
		$this->assertContains( 'listPost', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// publishing a trashed post should not evict a query for another post
		$this->assertContains( 'singlePost', $non_evicted_caches );

		// publishing a trashed post should not evict a query for another post
		$this->assertContains( 'singleContentNode', $non_evicted_caches );

		// publishing a trashed post should not evict a query for another single node by id
		$this->assertContains( 'singleNodeById', $non_evicted_caches );

		// publishing a trashed post should not evict a query for another post by uri
		$this->assertContains( 'singleNodeByUri', $non_evicted_caches );

		// the post did not have a category assigned, so the category list should not be evicted
		$this->assertContains( 'listCategory', $non_evicted_caches );

		// the post did not have a category assigned, so the singleCategory should not be evicted
		$this->assertContains( 'singleCategory', $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'listPage', $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'singlePage', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}

	// page is created as auto draft
	public function testPageIsCreatedAsAutoDraft() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->post->create([
			'post_type' => 'page',
			'post_status' => 'draft'
		]);

		// creating a draft post should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// page is published from draft
	public function testDraftPageIsPublished() {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_publish_post( $this->draft_page );

		$evicted_caches = $this->getEvictedCaches();

		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		// publishing a page should evict the listContentNode cache
		$this->assertContains( 'listContentNode', $evicted_caches );

		// publishing a page should evict the listPage query
		$this->assertContains( 'listPage', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// publishing a post not should evict the listPost cache
		$this->assertContains( 'listPost', $non_evicted_caches );

		// publishing a trashed post should not evict a query for another post
		$this->assertContains( 'singlePost', $non_evicted_caches );

		// publishing a trashed post should not evict a query for another post
		$this->assertContains( 'singleContentNode', $non_evicted_caches );

		// publishing a trashed post should not evict a query for another single node by id
		$this->assertContains( 'singleNodeById', $non_evicted_caches );

		// publishing a trashed post should not evict a query for another post by uri
		$this->assertContains( 'singleNodeByUri', $non_evicted_caches );

		// the post did not have a category assigned, so the category list should not be evicted
		$this->assertContains( 'listCategory', $non_evicted_caches );

		// the post did not have a category assigned, so the singleCategory should not be evicted
		$this->assertContains( 'singleCategory', $non_evicted_caches );

		// the singlePage query is for a different page than the one that was published and should remain cached
		$this->assertContains( 'singlePage', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}

	// published page is changed to draft
	public function testPublishedPageIsChangedToDraft() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// set the published page to draft
		self::factory()->post->update_object( $this->published_page->ID, [
			'post_status' => 'draft'
		]);

		$evicted_caches = $this->getEvictedCaches();

		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		// setting a published page to draft should evict the listContentNode cache
		$this->assertContains( 'listContentNode', $evicted_caches );

		// setting a published page to draft should evict the listPage query
		$this->assertContains( 'listPage', $evicted_caches );

		// the singlePage query should be evicted
		$this->assertContains( 'singlePage', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// setting a published page to draft should not evict the listPost cache
		$this->assertContains( 'listPost', $non_evicted_caches );

		// setting a published page to draft should not evict a query for another post
		$this->assertContains( 'singlePost', $non_evicted_caches );

		// setting a published page to draft should not evict a query for another post
		$this->assertContains( 'singleContentNode', $non_evicted_caches );

		// setting a published page to draft should not evict a query for another single node by id
		$this->assertContains( 'singleNodeById', $non_evicted_caches );

		// setting a published page to draft should not evict a query for another post by uri
		$this->assertContains( 'singleNodeByUri', $non_evicted_caches );

		// the page did not have a category assigned, so the category list should not be evicted
		$this->assertContains( 'listCategory', $non_evicted_caches );

		// the page did not have a category assigned, so the singleCategory should not be evicted
		$this->assertContains( 'singleCategory', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}

	// published page is changed to private
	public function testPublishedPageIsChangedToPrivate() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// set the published page to draft
		self::factory()->post->update_object( $this->published_page->ID, [
			'post_status' => 'private'
		]);

		$evicted_caches = $this->getEvictedCaches();

		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		// setting a published page to private should evict the listContentNode cache
		$this->assertContains( 'listContentNode', $evicted_caches );

		// setting a published page to private should evict the listPage query
		$this->assertContains( 'listPage', $evicted_caches );

		// the singlePage query should be evicted
		$this->assertContains( 'singlePage', $evicted_caches );



		$this->assertNotEmpty( $non_evicted_caches );

		// setting a published page to private should not evict the listPost cache
		$this->assertContains( 'listPost', $non_evicted_caches );

		// setting a published page to private should not evict a query for another post
		$this->assertContains( 'singlePost', $non_evicted_caches );

		// setting a published page to private should not evict a query for another post
		$this->assertContains( 'singleContentNode', $non_evicted_caches );

		// setting a published page to private should not evict a query for another single node by id
		$this->assertContains( 'singleNodeById', $non_evicted_caches );

		// setting a published page to private should not evict a query for another post by uri
		$this->assertContains( 'singleNodeByUri', $non_evicted_caches );

		// the page did not have a category assigned, so the category list should not be evicted
		$this->assertContains( 'listCategory', $non_evicted_caches );

		// the page did not have a category assigned, so the singleCategory should not be evicted
		$this->assertContains( 'singleCategory', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}

	// published page is trashed
	public function testPublishedPageIsTrashed() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// set the published page to trash
		self::factory()->post->update_object( $this->published_page->ID, [
			'post_status' => 'trash'
		]);

		$evicted_caches = $this->getEvictedCaches();

		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		// setting a published page to trashed should evict the listContentNode cache
		$this->assertContains( 'listContentNode', $evicted_caches );

		// setting a published page to trashed should evict the listPage query
		$this->assertContains( 'listPage', $evicted_caches );

		// the singlePage query should be evicted
		$this->assertContains( 'singlePage', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// setting a published page to trashed should not evict the listPost cache
		$this->assertContains( 'listPost', $non_evicted_caches );

		// setting a published page to trashed should not evict a query for another post
		$this->assertContains( 'singlePost', $non_evicted_caches );

		// setting a published page to trashed should not evict a query for another post
		$this->assertContains( 'singleContentNode', $non_evicted_caches );

		// setting a published page to trashed should not evict a query for another single node by id
		$this->assertContains( 'singleNodeById', $non_evicted_caches );

		// setting a published page to trashed should not evict a query for another post by uri
		$this->assertContains( 'singleNodeByUri', $non_evicted_caches );

		// the page did not have a category assigned, so the category list should not be evicted
		$this->assertContains( 'listCategory', $non_evicted_caches );

		// the page did not have a category assigned, so the singleCategory should not be evicted
		$this->assertContains( 'singleCategory', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}

	// published page is force deleted
	public function testPublishedPageIsForceDeleted() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// force delete the page
		wp_delete_post( $this->published_page->ID, true );

		$evicted_caches = $this->getEvictedCaches();

		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		// setting a published page to trashed should evict the listContentNode cache
		$this->assertContains( 'listContentNode', $evicted_caches );

		// setting a published page to trashed should evict the listPage query
		$this->assertContains( 'listPage', $evicted_caches );

		// the singlePage query is should be evicted
		$this->assertContains( 'singlePage', $evicted_caches );

		$this->assertNotEmpty( $non_evicted_caches );

		// setting a published page to trashed should not evict the listPost cache
		$this->assertContains( 'listPost', $non_evicted_caches );

		// setting a published page to trashed should not evict a query for another post
		$this->assertContains( 'singlePost', $non_evicted_caches );

		// setting a published page to trashed should not evict a query for another post
		$this->assertContains( 'singleContentNode', $non_evicted_caches );

		// setting a published page to trashed should not evict a query for another single node by id
		$this->assertContains( 'singleNodeById', $non_evicted_caches );

		// setting a published page to trashed should not evict a query for another post by uri
		$this->assertContains( 'singleNodeByUri', $non_evicted_caches );

		// the page did not have a category assigned, so the category list should not be evicted
		$this->assertContains( 'listCategory', $non_evicted_caches );

		// the page did not have a category assigned, so the singleCategory should not be evicted
		$this->assertContains( 'singleCategory', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}

	// delete draft page (doesnt evoke purge action)
	public function testDeleteDraftPage() {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_post( $this->draft_page->ID, true );

		// deleting a draft page should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

	}


	// trashed page is restored
	public function testTrashedPageIsRestored() {

		// ensure we have no evicted caches to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// trash a page
		wp_trash_post( $this->draft_page->ID );

		// trashing the draft page shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish the trashed page
		wp_publish_post( $this->draft_page->ID );

		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );
		$this->assertNotEmpty( $non_evicted_caches );

		codecept_debug( [
			'evicted' => $evicted_caches,
			'non' => $non_evicted_caches
		]);

		// publishing a page should evict the listContentNode cache
		$this->assertContains( 'listContentNode', $evicted_caches );

		// publishing a page should evict the listPage cache
		$this->assertContains( 'listPage', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// publishing a trashed page should not evict a query for a single post
		$this->assertContains( 'singlePost', $non_evicted_caches );

		// publishing a trashed page should not evict a query for single post
		$this->assertContains( 'singleContentNode', $non_evicted_caches );

		// publishing a trashed page should not evict a query for another single node by id
		$this->assertContains( 'singleNodeById', $non_evicted_caches );

		// publishing a trashed page should not evict a query for another post by uri
		$this->assertContains( 'singleNodeByUri', $non_evicted_caches );

		// the page did not have a category assigned, so the category list should not be evicted
		$this->assertContains( 'listCategory', $non_evicted_caches );

		// the page did not have a category assigned, so the singleCategory should not be evicted
		$this->assertContains( 'singleCategory', $non_evicted_caches );

		// no posts were affected, should remain cached
		$this->assertContains( 'listPost', $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'singlePage', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}


	// publish first post to a user (user->post connection should purge)
	public function testPublishFirstPostToUserShouldPurgeUserToPostConnection() {

		$new_user = self::factory()->user->create_and_get([
			'role' => 'administrator'
		]);

		$query = $this->getSingleUserByDatabaseIdWithAuthoredPostsQuery();
		$variables = [ 'id' => $new_user->ID ];

		$cache_key = $this->collection->build_key( null, $query, $variables );

		$this->assertEmpty( $this->collection->get( $cache_key ) );

		$actual = graphql([
			'query' => $query,
			'variables' => $variables
		]);

		self::assertQuerySuccessful( $actual, [
			$this->expectedField( 'user', self::IS_NULL )
		]);

		// ensure the query is cached now
		$this->assertNotEmpty( $this->collection->get( $cache_key ) );
		$this->assertSame( $actual, $this->collection->get( $cache_key ) );

		$new_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_author' => $new_user->ID,
		]);

		// assert that the query for a user and the users post has been evicted
		$this->assertEmpty( $this->collection->get( $cache_key ) );

		$query_again = graphql([
			'query' => $query,
			'variables' => $variables
		]);

		// the query should be cached again
		$this->assertNotEmpty( $this->collection->get( $cache_key ) );
		$this->assertSame( $query_again, $this->collection->get( $cache_key ) );

		// the results should have the user data
		self::assertQuerySuccessful( $query_again, [
			$this->expectedField( 'user.__typename', 'User' ),
			$this->expectedNode( 'user.posts.nodes', [
				'__typename' => 'Post',
				'databaseId' => $new_post->ID,
			]),
		]);

	}

	// delete only post of a user (user->post connection should purge)
	public function testDeleteOnlyPostOfUserShouldPurgeUserToPostConnection() {

		$new_user = self::factory()->user->create_and_get([
			'role' => 'administrator'
		]);

		$new_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_author' => $new_user->ID,
		]);

		$query = $this->getSingleUserByDatabaseIdWithAuthoredPostsQuery();
		$variables = [ 'id' => $new_user->ID ];

		$cache_key = $this->collection->build_key( null, $query, $variables );

		$this->assertEmpty( $this->collection->get( $cache_key ) );

		$actual = graphql([
			'query' => $query,
			'variables' => $variables
		]);

		// the query should be cached again
		$this->assertNotEmpty( $this->collection->get( $cache_key ) );
		$this->assertSame( $actual, $this->collection->get( $cache_key ) );

		// the results should have the user data
		self::assertQuerySuccessful( $actual, [
			$this->expectedField( 'user.__typename', 'User' ),
			$this->expectedNode( 'user.posts.nodes', [
				'__typename' => 'Post',
				'databaseId' => $new_post->ID,
			]),
		]);


		self::factory()->post->update_object( $new_post->ID, [
			'post_status' => 'draft'
		]);

		// after setting the only post of the author to draft, the cache should be cleared
		$this->assertEmpty( $this->collection->get( $cache_key ) );


		$actual = graphql([
			'query' => $query,
			'variables' => $variables
		]);

		// the results should now be null for the user as it's a private entity again
		self::assertQuerySuccessful( $actual, [
			$this->expectedField( 'user', self::IS_NULL )
		]);

	}

	// @todo
	// change only post of a user from publish to draft (user->post connection should purge)

	// change post author (user->post connection should purge)
	public function testChangeAuthorOfPost() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->post->update_object( $this->published_post->ID, [
			'post_author' => $this->editor
		]);

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		// the "adminUserWithPostsConnection" (previous author) query should have been evicted
		$this->assertContains( 'adminUserWithPostsConnection', $evicted_caches );

		// the "editorUserWithPostsConnection" (new author) query should have been evicted
		$this->assertContains( 'editorUserWithPostsConnection', $evicted_caches );

		// these should have also been evicted as the published post has changed
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'singlePost', $evicted_caches );
		$this->assertContains( 'singleContentNode', $evicted_caches );
		$this->assertContains( 'listContentNode', $evicted_caches );
		$this->assertContains( 'singleNodeByUri', $evicted_caches );

	}

	// update post meta of draft post does not evict cache
	public function testUpdatePostMetaOfDraftPostDoesntEvictCache() {

		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update meta on a draft post
		update_post_meta( $this->draft_post, 'meta_key', uniqid( null, true ) );

		// there should be no evicted cache after updating meta of a draft post
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertEqualSets( $non_evicted_caches_before, $this->getNonEvictedCaches() );

	}

	// delete post meta of draft post does not evoke purge action
	public function testUpdatePostMetaOnDraftPost() {

		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update post meta on the draft post
		update_post_meta( $this->draft_post->ID, 'test_key', uniqid( null, true ) );

		// this event should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );

	}

	// update allowed (meta without underscore at the front) post meta on published post
	public function testUpdateAllowedPostMetaOnPost() {

		// there should be no evicted caches to start
		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// meta is considered public if the key doesn't start win an underscore
		$key = 'test_meta_key';

		// we ensure the value is unique so that it properly triggers the updated_post_meta hook
		// if the value were the same as the previous value the hook wouldn't fire and we wouldn't
		// need to purge cache
		$value = 'value' . uniqid( 'test_', true );

		// update post meta on the published post.
		// if the meta doesn't exist yet, it will fire the "added_post_meta" hook
		update_post_meta( $this->published_post->ID, $key, $value );

		// this event SHOULD evict caches that contain the published post
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		codecept_debug( [ 'evicted' => $evicted_caches ]);
		$this->assertContains( 'singlePost', $evicted_caches );
		$this->assertContains( 'listPost', $evicted_caches );

		$this->assertNotSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );

	}

	public function testUpdateAllowedPostMetaOnPage() {}

	public function testUpdateAllowedPostMetaOnCustomPostType() {}

	public function testUpdateDisallowedPostMetaOnPost() {
		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update post meta on the draft post
		update_post_meta( $this->published_post->ID, '_private_meta', uniqid( null, true ) );

		// this event should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );
	}

	public function testUpdateDisallowedPostMetaOnPage() {
		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update post meta on the draft post
		update_post_meta( $this->published_page->ID, '_private_meta', uniqid( null, true ) );

		// this event should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );
	}

	// e
	public function testUpdateDisallowedPostMetaOnCustomPostType() {
		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update post meta on the draft post
		update_post_meta( $this->published_test_post_type->ID, '_private_meta', uniqid( null, true ) );

		// this event should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );
	}

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
