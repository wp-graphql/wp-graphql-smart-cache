<?php
namespace WPGraphQL\Labs;

use WPGraphQL\Labs\Cache\Collection;

class SettingsCacheInvalidationTest extends \Codeception\TestCase\WPTestCase {

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

	public function testItWorks() {
		$this->assertTrue( true );
	}

	// update untracked options (no purge)
	// update tracked option (purge group?)
	// set transient doesn't purge (don't want weird infinite loops)



	// new post types detected?
	// post type removed?
	// new taxonomy added?
	// taxonomy removed?
	// schema breaking change detected?
	// update permalinks (purge all?)

}
