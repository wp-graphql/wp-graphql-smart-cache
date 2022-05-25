<?php
namespace WPGraphQL\Labs;

use WPGraphQL\Labs\Cache\Collection;

class TermCacheInvalidationTest extends \Codeception\TestCase\WPTestCase {

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

	// category term is created
	// category term is updated
	// category term is deleted
	// category term is added to a published post
	// category term is added to a draft post
	// category term is removed from a published post
	// category term is removed from a draft post
	// update category meta
	// delete category meta
	// create child category
	// update child category
	// delete child category



	// tag term is created
	// tag term is updated
	// tag term is deleted
	// tag term is added to a published post
	// tag term is added to a draft post
	// tag term is removed from a published post
	// tag term is removed from a draft post
	// update tag meta
	// delete tag meta




	// custom tax (show_in_graphql) term is created
	// custom tax (show_in_graphql) term is updated
	// custom tax (show_in_graphql) term is deleted
	// custom tax (show_in_graphql) term is added to a published post
	// custom tax (show_in_graphql) term is added to a draft post
	// custom tax (show_in_graphql) term is removed from a published post
	// custom tax (show_in_graphql) term is removed from a draft post
	// update custom tax (show_in_graphql) term meta (of allowed meta key)
	// delete custom tax (show_in_graphql) term meta (of allowed meta key)

}
