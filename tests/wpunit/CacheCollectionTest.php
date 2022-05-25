<?php

namespace WPGraphQL\Labs;

use WPGraphQL\Labs\Cache\Collection;
use WPGraphQL\Labs\Storage\Ephemeral;
use GraphQLRelay\Relay;

class CacheCollectionTest extends \Codeception\TestCase\WPTestCase {

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

    public function testPurgeActionTriggeredOnUserChange() {
        // Create a user for this test
        $user_id = self::factory()->user->create( [
            'role' => 'editor',
            'first_name' => 'foo',
            'last_name' => 'bar',
        ] );

        $id = Relay::toGlobalId( 'user', (string) $user_id );
        $content = uniqid( 'test-data-' );

        // Fill some data in the collection memory for this user
        $collection = new Collection();
        $collection->store_content( "node:$id", $content );

        add_action('wpgraphql_cache_purge_nodes', function ( $type, $id, $nodes ) {
            set_transient( 'my-purge-action', [ $type, $id, $nodes ] );
        }, 10, 3 );

        // Change the user data
        self::factory()->user->update_object( $user_id, [
            'first_name' => 'biz'
        ]);

        // Verify the action callback happened
        $actual = get_transient( 'my-purge-action' );
        $this->assertEquals( 'user', $actual[0] );
        $this->assertEquals( "node:$id", $actual[1] );
    }

    public function testPurgeActionTriggeredOnPostMetaChange() {
        // Create a post for this test
        $post_id = self::factory()->post->create( [
            'title' => 'editor',
            'content' => 'foo bar biz',
        ] );

        $id = Relay::toGlobalId( 'post', (string) $post_id );
        $content = uniqid( 'test-data-' );

        // Fill some data in the collection memory for this post. So when post_meta changes, this should call the action cb.
        $collection = new Collection();
        $collection->store_content( "node:$id", $content );

        add_action('wpgraphql_cache_purge_nodes', function ( $type, $id, $nodes ) {
            set_transient( 'my-post-meta', [ $type, $id, $nodes ] );
        }, 10, 3 );

        // Add postmeta data for the post.
        add_metadata( 'post', $post_id, 'custom-meta', 'initial value' );

        // Change the postmeta data for the post. This should trigger the above action callback
        update_post_meta( $post_id, 'custom-meta', 'updated value' );

        // Verify the action callback happened
        $actual = get_transient( 'my-post-meta' );

        $this->assertEquals( 'post', $actual[0] );
        $this->assertEquals( "node:$id", $actual[1] );
        $this->assertEquals( [ $content ], $actual[2] );
    }

    // If have a list of posts stored in memory (means a posts collection query has been cached),
    // When a new post is created, we want to clear that (make sure the purge nodes is invoked).
    public function testPluralNameCollectionInvokedWhenPostCreated() {
        // Put at least empty data in transient. This helps trigger the purge action.
        $collection = new Collection();
        $collection->store_content( 'post', 'test-id' );
        $collection->store_content( 'test-id', 'foo' );

        // Verify how the test data is stored
        $this->assertEquals( [ 'foo' ], get_transient( 'gql_cache_test-id' ) );

        add_action('wpgraphql_cache_purge_nodes', function ( $type, $id, $nodes ) {
            set_transient( 'my-post-meta', "triggered-{$id}" );
        }, 10, 3 );

	    // the post is created as a draft. This should not
	    // trigger the purge action yet.
	    $post_id = self::factory()->post->create();

	    // verify it's not been triggered yet.
	    $this->assertEquals( false , get_transient( 'my-post-meta' ) );

		// update post meta of the non-published post
	    update_post_meta( $post_id, 'test_meta', 'meta_value' );

	    // this should not have been triggered yet, because the post was published
	    // but meta was not updated
	    $this->assertEquals( false , get_transient( 'my-post-meta' ) );

	    // set the post as published. This should still not trigger it, as no meta has changed
	    // on a published post
	    self::factory()->post->update_object( $post_id, [
		    'post_status' => 'publish'
	    ]);

	    // this should not have been triggered yet, because the post was published
	    // but meta was not updated
	    $this->assertEquals( false , get_transient( 'my-post-meta' ) );

		// update the post meta
		update_post_meta( $post_id, 'test_meta', 'meta_value' );

		// now that we updated meta of a published post, this should be triggered
	    $this->assertEquals( 'triggered-post' , get_transient( 'my-post-meta' ) );

	    // Verify transient stored in the posts type list is removed
	    $this->assertFalse( $collection->get( 'test-id' ) );
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
        $posts = $collection->get( 'post' );
        $this->assertNotFalse( $posts[0] );

        // Create post should trigger purge action and delete content for the above query
        self::factory()->post->create();

        // The posts list still has the hash in its list, but that query's hash should be empty
        $posts = $collection->get( 'post' );
        $this->assertFalse( $collection->get( $posts[0] ) );
    }
}
