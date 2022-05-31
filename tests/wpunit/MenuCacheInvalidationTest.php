<?php
namespace WPGraphQL\Labs;

use WPGraphQL\Labs\Cache\Collection;

class MenuCacheInvalidationTest extends \Codeception\TestCase\WPTestCase {

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

	// create nav menu (doesn't purge)
	// assign nav menu to location (purge)
	// update nav menu (which is assigned to location, should purge)
	// update nav menu (not assigned to a location, no purge)
	// delete menu (assigned to location, purge)
	// delete menu (not assigned to location, no purge)

}
