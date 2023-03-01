<?php

/**
 * Test the graphql saved document admin page
 */

class AdminEditorDocumentCest {

	public function _before( FunctionalTester $I ) {
	}

	/**
	 * Test http request to /{$taxonomy_name}/{$value}
	 * When taxonomy registered, the public/public_queryable value:
	 *   true - the WP 404 page
	 *   false - the hello world page
	 */
	public function postTypeShouldNotBePublicQueryableTest( FunctionalTester $I ) {

		// Enable the show-in-ui for these tests.  This allows testing of the admin editor page for our post type.
		$I->haveOptionInDatabase( 'graphql_persisted_queries_section', [ 'editor_display' => 'on' ] );

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
		// codecept_debug( $I->grabPageSource() );
		 $I->see('404');
		 $I->dontSee('XML Sitemap');

		// query alias should not be visible
		$I->amOnPage( "/graphql_query_alias/test-document-foo-bar/" );
		codecept_debug( $I->grabPageSource() );
		$I->dontSee('Alias Name: test-query-foo');
		$I->seeElement( "//body[contains(@class,'home')]" );

		 $I->amOnPage( "/wp-sitemap-taxonomies-graphql_query_alias-1.xml");

		 $I->see('This page could not be found.');
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
		codecept_debug( $I->grabPageSource() );
		$I->dontSee('Max-Age Header: 200');
		$I->dontSeeElement( "//body[contains(@class,'tax-graphql_document_http_maxage')]" );
		$I->seeElement( "//body[contains(@class,'home')]" );

		 $I->amOnPage( "wp-sitemap-taxonomies-graphql_document_http_maxage-1.xml");

		 $I->seeElement( "//body[contains(@class,'error404')]" );
		 $I->dontSee('XML Sitemap');
	}
}
