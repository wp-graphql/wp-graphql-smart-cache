<?php

// "query FooQuery { post( id: "1", idType: DATABASE_ID) { title } }"   <-- this is the one we want to error on !!!!!
// "query ($num: ID!) { post( id: "1", idType: DATABASE_ID) { title } }"
// "query ($num: ID!) { post( id: $num, idType: DATABASE_ID) { title } }"
//        looking for  [4] => argument: (id) ()\n            [5] => variable: (num)\n
// "query ($num: ID!) { posts { edges { node { title } } } }"

class SaveQueryValidateCest {
	public function _before( FunctionalTester $I ) {
		// Make sure that is gone.
		$I->dontHavePostInDatabase(['post_title' => 'Hello world!']);

		// clean up and persisted queries terms in the taxonomy
		$I->dontHavePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontHaveTermInDatabase( [ 'taxonomy' => 'graphql_query_alias'] );

		$I->dontHaveOptionInDatabase( 'graphql_persisted_queries_section'  );
	}

	public function _after( FunctionalTester $I ) {
		$I->dontHaveOptionInDatabase( 'graphql_persisted_queries_section'  );

		// Clean up any saved query documents
		$I->dontHavePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontHaveTermInDatabase( [ 'taxonomy' => 'graphql_query_alias'] );
	}

	public function saveQueryWithArgumentMissingVariableTest( FunctionalTester $I ) {
		$query = 'query { post( id: $num, idType: $id_type) { title } }';
		$query_alias = 'test-save-query-alias';
		$I->dontSeeTermInDatabase( [ 'name' => 'graphql_query_alias' ] );
		$I->sendPost('graphql', [
			'query' => $query,
			'queryId' => $query_alias
		] );
		$I->dontSeePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
		] );
		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'Validation Error: Variable "$num" is not defined.'
			]
		]);
	}

	public function saveQueryMissingArgumentWithVariableTest( FunctionalTester $I ) {
		$query = 'query ($num: ID!) { post( id: "1", idType: DATABASE_ID ) { title } }';
		$query_alias = 'test-save-query-alias';
		$I->dontSeeTermInDatabase( [ 'name' => 'graphql_query_alias' ] );
		$I->sendPost('graphql', [
			'query' => $query,
			'queryId' => $query_alias
		] );
		$I->dontSeePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
		] );
		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'Validation Error: Argument "id" should be a variable.'
			]
		]);
	}

	public function saveQueryWithIdTypeShouldBeVariableTest( FunctionalTester $I ) {
		$query = 'query { post( id: $num, idType: DATABASE_ID) { title } }';
		$query_alias = 'test-save-query-alias';
		$I->dontSeeTermInDatabase( [ 'name' => 'graphql_query_alias' ] );
		$I->sendPost('graphql', [
			'query' => $query,
			'queryId' => $query_alias
		] );
		$I->dontSeePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
		] );
		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'Validation Error: Argument "idType" should be a variable.'
			]
		]);
	}

	public function saveQueryMissingArgumentIdTypeWithVariableTest( FunctionalTester $I ) {
		$query = 'query ($num: ID!) { post( id: $num, idType: DATABASE_ID ) { title } }';
		$query_alias = 'test-save-query-alias';
		$I->dontSeeTermInDatabase( [ 'name' => 'graphql_query_alias' ] );
		$I->sendPost('graphql', [
			'query' => $query,
			'queryId' => $query_alias
		] );
		$I->dontSeePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
		] );
		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'Validation Error: Argument "idType" should be a variable.'
			]
		]);
	}

	public function saveQueryWithArgumentWithVariableTest( FunctionalTester $I ) {
		$query = 'query ($num: ID! $id_type: PostIdType!) { post(id: $num, idType: $id_type) { title } }';
		$query_alias = 'test-save-query-alias';

		$post_id = $I->havePostInDatabase(['post_title' => 'Hello world!']);

		$I->dontSeeTermInDatabase( [ 'name' => 'graphql_query_alias' ] );
		$I->sendPost('graphql', [
			'query' => $query,
			'queryId' => $query_alias,
			'variables' => [ "num" => $post_id, "id_type" => "DATABASE_ID" ],
		] );
		$I->seeResponseContainsJson([
			'data' => [
				'post' => [
					'title' => 'Hello world!',
				]
			]
		]);
		$I->seeTermInDatabase( [ 'name' => $query_alias ] );
		$I->seePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_content' => "query (\$num: ID!, \$id_type: PostIdType!) {\n  post(id: \$num, idType: \$id_type) {\n    title\n  }\n}\n",
		] );
	}

	public function editorCreateNewQueryWithErrorWhenPublishSavesAsDraftTest( FunctionalTester $I ) {
		$I->haveOptionInDatabase( 'graphql_persisted_queries_section', [ 'editor_display' => 'on' ] );

		$post_title = 'test-post';
		$query = 'query ($num: ID!) { post( id: "1", idType: DATABASE_ID ) { title } }';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', $query );

		$I->dontSeePostInDatabase( ['post_title' => $post_title] );

		// Publish post
		$I->click('#publish');

		// Because of error form (empty content), saves as draft
		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
		]);

		$I->see('Validation Error: Argument "id" should be a variable.', '//*[@id="plugin-message"]');
		$I->dontSeeElement('//*[@id="message"]');
		$I->dontSee('Post draft updated.');
		$I->dontSee('Post published.');
		$I->see('Publish immediately'); // does not have a publish date
	}

	public function saveQueryErrorsWhenIntValueShouldBeAVariableTest( FunctionalTester $I ) {
		$query = '{ posts( first: 1 ) { nodes { title } } }';
		$query_alias = 'test-save-query-alias';
		$I->dontSeeTermInDatabase( [ 'name' => 'graphql_query_alias' ] );
		$I->sendPost('graphql', [
			'query' => $query,
			'queryId' => $query_alias
		] );
		$I->dontSeePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
		] );
		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'Validation Error: Argument "first" should be a variable.'
			]
		]);
	}
}