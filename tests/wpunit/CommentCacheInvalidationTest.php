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

	// @todo: need to think through all the comment actions
	// create comment (unapproved)
	// create comment (approved)
	// approve comment
	// unapprove comment
	// delete comment

}
