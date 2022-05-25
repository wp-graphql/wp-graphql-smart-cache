<?php
namespace WPGraphQL\Labs;

use WPGraphQL\Labs\Cache\Collection;

class PostCacheInvalidationTest extends \Codeception\TestCase\WPTestCase {

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

	// scheduled post is published
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
