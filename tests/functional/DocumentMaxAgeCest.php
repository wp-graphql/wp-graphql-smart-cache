<?php

/**
 * Test the wp-graphql settings page for global max age header.
 */

class DocumentMaxAgeCest {
	public function _before( FunctionalTester $I ) {
		$I->dontHaveOptionInDatabase( 'graphql_cache_section'  );
	}

	public function _runQuery( FunctionalTester $I, $expected ) {
		$query = "query { __typename }";
		$I->sendPost('graphql', [
			'query'         => $query,
		] );
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery'
			]
		]);
		$I->seeHttpHeader( 'Access-Control-Max-Age', $expected );
	}

	public function queryShowsMaxAgeTest( FunctionalTester $I ) {
		$I->wantTo( 'See my custom max-age header in response for a graphql query' );

		$I->dontHaveOptionInDatabase( 'graphql_cache_section'  );
		$this->_runQuery( $I, 600 );

		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => null ] );
		$this->_runQuery( $I, 600 );

		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => 30 ] );
		$this->_runQuery( $I, 30 );

		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => 10.5 ] );
		$this->_runQuery( $I, 10 );

		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => -1 ] );
		$this->_runQuery( $I, 600 );

		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => 0 ] );
		$this->_runQuery( $I, 0 );

	}

	public function batchQueryDefaultMaxAgeTest( FunctionalTester $I ) {
		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => 444 ] );

		$query =
			[
				[	"query" => "query { __typename }" ],
				[	"query" => "{ posts { nodes { title content } } }" ],
			]
		;

		$I->sendPost('graphql', $query );
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery'
			]
		]);
		$I->seeHttpHeader( 'Access-Control-Max-Age', 444 );
	}

}
