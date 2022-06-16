<?php
namespace WPGraphQL\Labs;

use TestCase\WPGraphQLLabs\TestCase\WPGraphQLLabsTestCaseWithSeedDataAndPopulatedCaches;

class MediaItemCacheInvalidationTest extends WPGraphQLLabsTestCaseWithSeedDataAndPopulatedCaches {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testItWorks() {
		$this->assertTrue( true );
	}

	// upload media item
	public function testUploadMediaItemEvictsCache() {

		// uploading a media item should evict cache for list of media items
		$filename = WPGRAPHQL_LABS_PLUGIN_DIR . '/tests/_data/images/test.png';
		codecept_debug( $filename );

		$this->assertEmpty( $this->getEvictedCaches() );

		$image_id = self::factory()->attachment->create_upload_object( $filename );

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// purge list of media items when a new image is uploaded
			'listMediaItem',

			// should media items be content nodes? 🤔
			'listContentNode'
		], $evicted_caches );

	}

	// update media item
	public function testUpdateMediaItemEvictsCache() {

		// updating a media item should evict cache for single media item and list media items
	}

	// delete media item
	public function testDeleteMediaItem() {

		// evict cache for single media item, list media item
	}

	// update media item meta
	public function updateMediaItemMetaShouldEvictCache() {

	}

	// delete media item meta
	public function deleteMediaItemMetaShouldEvictCache() {

	}


}
