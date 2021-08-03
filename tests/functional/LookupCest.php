<?php

class LookupCest {
	public function _before( FunctionalTester $I ) {
		// Make sure that is gone.
		$I->dontHavePostInDatabase(['post_title' => 'Hello world!']);
	}

	// no id/hash. expect an error that it doesn't exist
	public function queryIdThatDoesNotExistTest( FunctionalTester $I ) {
		$I->wantTo( 'Query with a hash that does not exist in the database' );
		$query_hash = '1234';

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendGet('graphql', [ 'queryId' => $query_hash ] );

		$I->seeResponseContainsJson([
			'errors' => [
				'message' => sprintf( 'Query Not Found %s', $query_hash )
			]
		]);
	}

	// insert hash with empty query, expect error
	public function queryIdWithEmptyGraphqlStringTest( FunctionalTester $I ) {
		$I->wantTo( 'Query with a hash that exists but has empty query in the database' );

		$query_hash = '1234';
		$I->havePostInDatabase( [
			'post_type'    => 'graphql_query',
			'post_status'  => 'publish',
			'post_title'   => $query_hash,
			'post_content' => '',
		] );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendGet('graphql', [ 'queryId' => $query_hash ] );

		$I->seeResponseContainsJson([
			'errors' => [
				'message' => sprintf( 'Query Not Found %s', $query_hash )
			]
		]);

		// clean up
		$I->dontHavePostInDatabase(['post_title' => $query_hash]);
	}

	// insert hash and query string that doesn't match. expect error
	public function queryIdWithWrongGraphqlStringTest( FunctionalTester $I ) {
		$I->wantTo( 'Query with a hash that does not match the graphql string in the database' );

		$query = '{
			foo: bizbang
		}';

		// Make sure query hash we use doesn't match
		$query_hash = 'X-' . hash( 'sha256', $query );
		$I->havePostInDatabase( [
			'post_type'    => 'graphql_query',
			'post_status'  => 'publish',
			'post_title'   => $query_hash,
			'post_content' => $query,
		] );
		
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendGet('graphql', [ 'queryId' => $query_hash ] );

		$I->seeResponseContainsJson([
			'errors' => [
				'message' => sprintf( 'Query Not Found %s', $query_hash )
			]
		]);

		// clean up
		$I->dontHavePostInDatabase(['post_title' => $query_hash]);
	}

	// insert hash and query string, expect empty result
	public function queryIdWithGraphqlEmptyResultsTest( FunctionalTester $I ) {
		$I->wantTo( 'Query with hash that results in no return content/posts' );

		$query = "{\n  posts {\n    nodes {\n      title\n    }\n  }\n}\n";
		$query_hash = hash( 'sha256', $query );

		$I->havePostInDatabase( [
			'post_type'    => 'graphql_query',
			'post_status'  => 'publish',
			'post_title'   => $query_hash,
			'post_content' => $query,
		] );
		
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendGet('graphql', [ 'queryId' => $query_hash ] );

		// https://codeception.com/docs/modules/REST.html#jsonpath
		$I->assertEmpty(
			$I->grabDataFromResponseByJsonPath("$.data.posts.nodes[*].title")
		);

		// clean up
		$I->dontHavePostInDatabase(['post_title' => $query_hash]);
	}

	// insert hash, query string, posts. expect results
	public function queryIdWithGraphqlWithPostsTest( FunctionalTester $I ) {
		$I->wantTo( 'Query with a hash that results in posts from the database' );

		$query = "{\n  posts {\n    nodes {\n      title\n    }\n  }\n}\n";
		$query_hash = hash( 'sha256', $query );

		$I->havePostInDatabase( [
			'post_type'    => 'graphql_query',
			'post_status'  => 'publish',
			'post_title'   => $query_hash,
			'post_content' => $query,
		] );

		$I->havePostInDatabase( [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'foo',
			'post_content' => 'foo bar. biz bang.',
			'post_name'   => 'foo-slug',
		] );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );

		$I->sendGet('graphql', [ 'queryId' => $query_hash ] );
		$I->seeResponseContainsJson( [
			'data' => [
				'posts' => [
					'nodes' => [
							'title' => 'foo'
					]
				]
			]
		]);

		// clean up
		$I->dontHavePostInDatabase( [ 'post_title' => $query_hash ] );
		$I->dontHavePostInDatabase( [ 'post_title' => 'foo' ] );
	}
}
