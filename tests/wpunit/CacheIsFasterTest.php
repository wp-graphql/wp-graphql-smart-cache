<?php

namespace WPGraphQL\PersistedQueries;

use SebastianBergmann\Timer\Timer;
use SebastianBergmann\Timer\Duration;

/**
 * Test the wp-graphql request to cached query is faster
 */

class CacheIsFasterTest extends \Codeception\TestCase\WPTestCase {
	public $timer;

	public function _before() {
		$this->timer = new Timer;
	}

	public function _after() {
	}

	public function testCachedQueryIsFaster() {
			$this->timer->start();
			$query_string = '{ __typename }';
			$result = graphql( [ 'query' => $query_string ] );
			$duration1 = $this->timer->stop();
			codecept_debug( sprintf("\nDuration time %f seconds\n", $duration1->asSeconds() ) );

			$this->timer->start();
			$query_string = '{ __typename }';
			$result = graphql( [ 'query' => $query_string ] );
			$duration2 = $this->timer->stop();
			codecept_debug( sprintf("\nDuration time %f seconds\n", $duration2->asSeconds() ) );

			// Intentionally make it bigger for this example.
			$this->assertLessThan( $duration1->asSeconds()+10, $duration2->asSeconds() );
		}
}
