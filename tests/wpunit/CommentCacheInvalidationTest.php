<?php

namespace WPGraphQL\SmartCache;

use TestCase\WPGraphQLSmartCache\TestCase\WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches;

class CommentCacheInvalidationTest extends WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testItWorks() {
		$this->assertTrue( true );
	}

	// create comment (unapproved)
	public function testCreateUnapprovedCommentDoesNotEvictCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->comment->create_object( [
			'comment_approved' => false
		] );

		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// create comment (approved)
	public function testCreateApprovedCommentEvictsCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->comment->create_object( [
			'comment_approved' => true
		] );

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listComment',
		], $evicted_caches );

	}

	// approve comment
	public function testTransitionCommentToApprovedEvictsCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->comment->update_object( $this->unapproved_comment->comment_ID, [
			'comment_approved' => true
		] );

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listComment',
		], $evicted_caches );

	}

	// unapprove comment
	public function testTransitionCommentToUnapprovedEvictsCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->comment->update_object( $this->approved_comment->comment_ID, [
			'comment_approved' => false
		] );

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listComment',
		], $evicted_caches );

	}

	// delete comment
	public function testDeleteApprovedCommentEvictsCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_comment( $this->approved_comment->comment_ID );

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listComment',
		], $evicted_caches );

	}

}
