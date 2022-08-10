<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Cache\Collection;

class CacheInvalidationTest extends \Codeception\TestCase\WPTestCase {

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

	public function testNonNullListOfNonNullPostMapsToListOfPosts() {

		register_graphql_field( 'RootQuery', 'listOfThing', [
			'type' => [
				'non_null' => [
					'list_of' => [
						'non_null' => 'Post'
					],
				],
			],
		]);

		$query = '
		{
		  listOfThing {
		    __typename
		  }
		}
		';

		$schema = \WPGraphQL::get_schema();
		$types = $this->collection->get_query_list_types( $schema, $query );

		$this->assertContains( 'list:post', $types );

	}

	public function testListOfNonNullPostMapsToListOfPosts() {

		register_graphql_field( 'RootQuery', 'listOfThing', [
			'type' => [
				'list_of' => [
					'non_null' => 'Post'
				],
			],
		]);

		$query = '
		{
		  listOfThing {
		    __typename
		  }
		}
		';

		$schema = \WPGraphQL::get_schema();
		$types = $this->collection->get_query_list_types( $schema, $query );

		$this->assertContains( 'list:post', $types );

	}

}
