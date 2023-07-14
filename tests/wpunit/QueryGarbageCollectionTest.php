<?php
/**
 * @package Wp_Graphql_Smart_Cache
 *
 * Test saved query garbage collection clean up of queries after certain age.
 * Test cron job wakes and deletes was is expected.
 */

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Utils;

class QueryGarbageCollectionTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		update_option(
			'graphql_persisted_queries_section',
			[ 
				'query_gc' => 'off',
			]
		);
	}

	public function _after() {
		delete_option( 'graphql_persisted_queries_section' );
	}

	public function testQueriesAreDeletedByJob() {
		// Enable garbage collection for queries after an age
		update_option(
			'graphql_persisted_queries_section',
			[ 
				'query_gc' => 'on',
				'query_gc_age' => '20',
			]
		);

		$age_list = [
			'11 days ago',
			'21 days ago',
		];

		// Create saved queries with various modified dates/ages
		$counter = 0;
		foreach ( $age_list as $one_date ) {
			$date_string = date( 'Y-m-d H:i:s', strtotime( $one_date ) );
			// Create more than one saved query for this age
			for ( $i = 0; $i < 3; $i++ ) {
				self::factory()->post->create(
					[
						'post_type' => 'graphql_document',
						'post_date' => $date_string,
						'post_content' => sprintf( "query Saved_%d { typename }", $counter ),
						'post_title' => sprintf( "query %d", $counter ),
					]
				);
				$counter++;
			}
		}

		$this->assertCount( 6, Utils::getDocumentsByAge( 10 ) );
		$this->assertCount( 3, Utils::getDocumentsByAge( 20 ) );

		// Verify delete event is not scheduled before the garbage collection event runs
		$this->assertFalse( wp_next_scheduled( 'wp_graphql_smart_cache_query_gc_deletes' ) );

		do_action( 'wp_graphql_smart_cache_query_gc' );

		// Verify delete job scheduled, this WP api returns timestamp integer
		$this->assertIsInt( wp_next_scheduled( 'wp_graphql_smart_cache_query_gc_deletes' ) );

		// filter batch size to we only delete a few of our aged queries and reschedule the next job
		add_filter( 'wpgraphql_document_garbage_collection_batch_size', function () { return 2; } );

		// Fire the delete action and verify the number of posts deleted
		do_action( 'wp_graphql_smart_cache_query_gc_deletes' );
		$this->assertCount( 1, Utils::getDocumentsByAge( 20 ) );

		// Fire the delete action again, verify expected queries are removed.
		do_action( 'wp_graphql_smart_cache_query_gc_deletes' );
		$this->assertCount( 3, Utils::getDocumentsByAge( 10 ) );
		$this->assertCount( 0, Utils::getDocumentsByAge( 20 ) );
	}

}
