<?php

namespace WPGraphQL\Labs;

use WPGraphQL\Labs\Cache\Collection;
use WPGraphQL\Labs\Storage\Ephemeral;

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

}
