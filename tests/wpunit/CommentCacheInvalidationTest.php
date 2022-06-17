<?php
namespace WPGraphQL\Labs;

use TestCase\WPGraphQLLabs\TestCase\WPGraphQLLabsTestCaseWithSeedDataAndPopulatedCaches;

class CommentCacheInvalidationTest extends WPGraphQLLabsTestCaseWithSeedDataAndPopulatedCaches {

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
