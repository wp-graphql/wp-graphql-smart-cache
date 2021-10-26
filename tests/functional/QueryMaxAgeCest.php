<?php

/**
 * Test the wp-graphql settings page for global max age header.
 */

class QueryMaxAgeCest {
	public function _before( FunctionalTester $I ) {
		$I->dontHaveOptionInDatabase( 'graphql_persisted_queries_section'  );
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

		$I->dontHaveOptionInDatabase( 'graphql_persisted_queries_section'  );
		$this->_runQuery( $I, 600 );

		$I->haveOptionInDatabase( 'graphql_persisted_queries_section', [ 'global_max_age' => null ] );
		$this->_runQuery( $I, 600 );

		$I->haveOptionInDatabase( 'graphql_persisted_queries_section', [ 'global_max_age' => 30 ] );
		$this->_runQuery( $I, 30 );

		$I->haveOptionInDatabase( 'graphql_persisted_queries_section', [ 'global_max_age' => 10.5 ] );
		$this->_runQuery( $I, 10 );

		$I->haveOptionInDatabase( 'graphql_persisted_queries_section', [ 'global_max_age' => -1 ] );
		$this->_runQuery( $I, 600 );

		$I->haveOptionInDatabase( 'graphql_persisted_queries_section', [ 'global_max_age' => 0 ] );
		$this->_runQuery( $I, 0 );

	}

}
