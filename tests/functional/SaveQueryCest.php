<?php

class SaveQueryCest {
	public function _before( FunctionalTester $I ) {
		// Make sure that is gone.
		$I->dontHavePostInDatabase(['post_title' => 'Hello world!']);
	}

	public function saveQueryWithSpecificNameTest( FunctionalTester $I ) {
		$I->wantTo( 'Save a named graphql query' );

		$query = "query my_yoyo_query {\n  __typename\n}\n";

		// Make sure query hash we use doesn't match
		$query_hash = hash( 'sha256', $query );

		$I->sendPost('graphql', [
			'query' => $query,
			'queryId' => $query_hash
		] );
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery'
			]
		]);
		$I->seePostInDatabase( [
			'post_type'    => 'graphql_query',
			'post_status'  => 'publish',
			'post_name'    => $query_hash,
			'post_content' => $query,
			'post_title'    => 'my_yoyo_query',
		] );

		// clean up
		$I->dontHavePostInDatabase( [ 'post_name' => $query_hash ] );
	}
}