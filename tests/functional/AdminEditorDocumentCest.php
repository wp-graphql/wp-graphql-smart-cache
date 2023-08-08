<?php

/**
 * Test the graphql saved document admin page
 */

class AdminEditorDocumentCest {

	public function _before( FunctionalTester $I ) {
		// Enable the show-in-ui for these tests.  This allows testing of the admin editor page for our post type.
		$I->haveOptionInDatabase( 'graphql_persisted_queries_section', [ 'editor_display' => 'on' ] );
	}

	public function _after( FunctionalTester $I ) {
		$I->dontHaveOptionInDatabase( 'graphql_persisted_queries_section'  );

		// Clean up any saved query documents
		$I->dontHavePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontHaveTermInDatabase( [ 'taxonomy' => 'graphql_query_alias'] );
	}

	/**
	 * Test http request to /{$taxonomy_name}/{$value}
	 * When taxonomy registered, the public/public_queryable value:
	 *   true - the WP 404 page
	 *   false - the hello world page
	 */
	public function postTypeShouldNotBePublicQueryableTest( FunctionalTester $I ) {

		// Create a query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Save and see the selection after form submit
		$I->fillField( "//input[@name='post_title']", 'test-query-foo');
		$I->fillField( 'content', '{ __typename }');
		$I->selectOption("form input[name='graphql_query_grant']", 'allow');
		$I->fillField( 'graphql_query_maxage', '200');
		$I->click('//input[@id="publish"]');
		$I->seeInField(['name' => 'graphql_query_maxage'], '200');

		$I->amOnPage( '/wp-admin/edit-tags.php?taxonomy=graphql_document_grant&post_type=graphql_document' );
		$I->see( 'allow' );

		// saved document should not be visible
		$I->amOnPage( "/graphql_document/test-query-foo/" );
		codecept_debug( $I->grabPageSource() );
		$I->dontSee('__typename');

		// WordPress shows the homepage template for taxonomies that are public=>false
		// this is similar to a 404, but the WP way of handling it for this situation
		// so if we see the home template, we can be sure the private taxonomy isn't being publicly
		// exposed
		$I->seeElement( "//body[contains(@class,'home')]" );

		$I->amOnPage( "/wp-sitemap-posts-graphql_document-1.xml");
		$I->seeElement( "//body[contains(@class,'error404')]" );
		$I->dontSee('XML Sitemap');

		// query alias should not be visible
		$I->amOnPage( "/graphql_query_alias/test-document-foo-bar/" );
		codecept_debug( $I->grabPageSource() );
		$I->dontSee('Alias Name: test-query-foo');
		$I->seeElement( "//body[contains(@class,'home')]" );

		$I->amOnPage( "/wp-sitemap-taxonomies-graphql_query_alias-1.xml");

		$I->seeElement( "//body[contains(@class,'error404')]" );
		$I->dontSee('XML Sitemap');

		// allow/deny grant should not be visible
		$I->amOnPage( "/graphql_document_grant/allow/" );
		codecept_debug( $I->grabPageSource() );
		$I->dontSee('Allow/Deny: allow');
		//  tax-graphql_document_grant
		$I->dontSeeElement( "//body[contains(@class,'tax-graphql_document_grant')]" );
		$I->seeElement( "//body[contains(@class,'home')]" );

		$I->amOnPage( "wp-sitemap-taxonomies-graphql_document_grant-1.xml");

		$I->seeElement( "//body[contains(@class,'error404')]" );
		$I->dontSee('XML Sitemap');

		// max age should not be visible
		$I->amOnPage( "/graphql_document_http_maxage/200/" );

		$I->dontSee('Max-Age Header: 200');
		$I->dontSeeElement( "//body[contains(@class,'tax-graphql_document_http_maxage')]" );
		$I->seeElement( "//body[contains(@class,'home')]" );

		$I->amOnPage( "wp-sitemap-taxonomies-graphql_document_http_maxage-1.xml");

		$I->seeElement( "//body[contains(@class,'error404')]" );
		$I->dontSee('XML Sitemap');
	}

	public function newQueryWithoutErrorWhenSaveDraftSavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename }');

		// This might cause auto-draft but not save
		$I->dontSeePostInDatabase( ['post_title' => $post_title] );

		// Save draft button.
		$I->click('//input[@id="save-post"]');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
		]);
	}

	public function newQueryWithEmptyContentWhenSaveDraftSavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );

		$I->dontSeePostInDatabase( ['post_title' => $post_title] );

		// Save draft button. No content in form
		$I->click('//input[@id="save-post"]');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
		]);
	}

	public function newQueryWithEmptyContentWhenPublishSavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '');

		$I->dontSeePostInDatabase( ['post_title' => $post_title] );

		// Publish post
		$I->click('//input[@id="publish"]');

		// Because of error form (empty content), saves as draft
		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
		]);
	}

	public function newQueryWithInvalidContentWhenPublishSavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename broken');

		// This should cause auto-draft but not save
		$I->dontSeePostInDatabase( ['post_title' => $post_title] );

		// Publish post
		$I->click('//input[@id="publish"]');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
		]);
	}

	public function newQueryWithoutErrorWhenPublishSavesAsPublishedTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename }');

		// This might cause auto-draft but not save
		$I->dontSeePostInDatabase( ['post_title' => $post_title] );

		// Publish post
		$I->click('//input[@id="save-post"]');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'publish',
		]);
	}

	public function haveDraftQueryWithInvalidQueryWhenPublishSavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename }');

		// Save draft button.
		$I->click('//input[@id="save-post"]');

		// invalid query
		$I->fillField( 'content', '{ __typename broken');

		// // Publish post button
		$I->click('//input[@id="publish"]');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
		]);
	}

	public function haveDraftQueryWithValidQueryWhenPublishSavesAsPublishedTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename }');

		// Save draft button.
		$I->click('//input[@id="save-post"]');

		// invalid query
		$I->fillField( 'content', '{ __typename broken');

		// // Publish post button
		$I->click('//input[@id="publish"]');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'publish',
		]);
	}

	public function havePublishedQueryWithInvalidQueryWhenSaveAsDraftSavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename }');

		// Save draft button.
		$I->click('//input[@id="save-post"]');

		// invalid query
		$I->fillField( 'content', '{ __typename broken');

		// // Publish post button
		$I->click('//input[@id="publish"]');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
		]);
	}
	
	public function havePublishedQueryWithInvalidQueryWhenPublishItShowsPreviousQueryContentTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title);
		$I->fillField( 'content', '{ __typename }');

		// Save draft button.
		$I->click('//input[@id="save-post"]');

		// invalid query
		$I->fillField( 'content', '{ __typename broken');

		// // Publish post button
		$I->click('//input[@id="publish"]');

		// Does not save/overwrite the working query string with broken one.
		// Leaves as published
		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'publish',
			'post_content' => '{ __typename }',
		]);
	}
}
