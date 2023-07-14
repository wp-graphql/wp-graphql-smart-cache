<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Cache\Collection;
use WPGraphQL\SmartCache\Storage\Ephemeral;
use GraphQLRelay\Relay;

class CacheCollectionTest extends \Codeception\TestCase\WPTestCase {

	public function _setUp() {

		// enable graphql cache maps
		add_filter( 'wpgraphql_cache_enable_cache_maps', '__return_true' );

		parent::_setUp(); // TODO: Change the autogenerated stub
	}

	public function testAddData() {
        $key = uniqid( 'test-' );
        $content = 'foo-bar';

        $collection = new Collection();
        $collection->store_content( $key, $content );

        $actual = $collection->get( $key );
        $this->assertCount( 1, $actual );
        $this->assertEquals( $content, array_pop( $actual ) );
    }

    public function testStoreMultipleItems() {
        $key = uniqid( 'test-' );

        $collection = new Collection();
        $collection->store_content( $key, 'foo' );
        $collection->store_content( $key, 'bar' );

        $actual = $collection->get( $key );
        $this->assertCount( 2, $actual );
        $this->assertEquals( 'bar', array_pop( $actual ) );
        $this->assertEquals( 'foo', array_pop( $actual ) );
    }

    public function testPostsQueryPurgesWhenPostCreated() {

		// Create some data
        self::factory()->post->create();

        // Run a query which the hash will be saved to the posts list
        $query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";

		graphql([ 'query' => $query ]);

        $collection = new Collection();
        $posts = $collection->get( 'list:post' );
        $this->assertNotFalse( $posts[0] );

        // Create post should trigger purge action and delete content for the above query
        self::factory()->post->create([ 'post_type' => 'publish' ]);

        // The posts list still has the hash in its list, but that query's hash should be empty
        $posts = $collection->get( 'list:post' );
        $this->assertFalse( $collection->get( $posts[0] ) );
    }
}
