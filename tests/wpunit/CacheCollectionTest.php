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
}
