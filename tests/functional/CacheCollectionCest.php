<?php
/**
 * Test GET request to graphql.
 * Verify the result nodes are saved to the collection map memory/transients.
 */
class CacheCollectionCest {
	public function queryContentTest( FunctionalTester $I ) {
		$I->wantTo( 'Execute a graphql query and verify nodes are in memory for my url' );

		$I->havePostInDatabase( [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'foo',
			'post_content' => 'foo bar. biz bang.',
			'post_name'    => 'foo-slug',
		] );

		$I->sendGet( 'graphql', [ 'query' => '{ posts { nodes { id title content } } }' ] );
		$I->seeResponseContainsJson( [
			'data' => [
				'posts' => [
					'nodes' => [
							'title' => 'foo'
					]
				]
			]
		]);

		$post_id = $I->grabDataFromResponseByJsonPath("$.data.posts.nodes[*].id")[0];
		codecept_debug( $post_id );

		// Get the stored information for the post node after we ran the graphql GET query.
		// Verify the stored url matches our GET request
		$transient_name = "_transient_gql_cache_node:$post_id";
		$query_key = unserialize( $I->grabFromDatabase( 'wp_options', 'option_value', [ 'option_name' => $transient_name ] ) );
		$query_key = $query_key[0];
		codecept_debug( $query_key );

		// Now take that value of the query request hash and look up the urls for that query
		// Example '_transient_gql_cache_url:10756d547c7be4686f65c2980cf4b3be4936c2b0c95eb6bdcf0a4668fc5ce5b3';
		$transient_name = "_transient_gql_cache_url:$query_key";
		$urls = unserialize( $I->grabFromDatabase( 'wp_options', 'option_value', [ 'option_name' => $transient_name ] ) );
		$url = $urls[0];
		codecept_debug( $url );

		// This is what the url looks like for the query. Should be stored in the collection map
		$expected_url = '/graphql?query=%7B+posts+%7B+nodes+%7B+id+title+content+%7D+%7D+%7D';
		$I->assertEquals($expected_url, $url);

		// clean up
		$I->dontHavePostInDatabase( ['post_name' => 'foo-slug'] );
	}

}
