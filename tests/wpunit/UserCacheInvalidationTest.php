<?php
namespace WPGraphQL\Labs;

use WPGraphQL\Labs\Cache\Collection;

class UserCacheInvalidationTest extends \Codeception\TestCase\WPTestCase {

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

	// create user (no purge, not public yet)
	// delete user with no published posts (no purge)
	// delete user without re-assign (what should happen here?)
	// - call purge for each post the author was the author of?
	// delete user and re-assign posts
	// - purge user
	// - purge for each post (of each post type) transferred
	// - purge for the new author being assigned
	// update user that has published posts
	// update user meta (with allowed meta key)
	// update user meta (with non-allowed meta key)
	// delete user meta (with allowed meta key)
	// delete user meta (with non-allowed meta key)

}
