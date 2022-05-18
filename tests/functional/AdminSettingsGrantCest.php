<?php

/**
 * Test the wp-graphql settings page for global allow/deny.
 */

class AdminSettingsGrantCest
{
	public function _after( FunctionalTester $I ) {
		$I->dontHaveOptionInDatabase( 'graphql_persisted_queries_section'  );
		$I->dontHaveOptionInDatabase( 'graphql_cache_section'  );
	}

	public function saveAllowOnlySettingsTest( FunctionalTester $I ) {
		$I->loginAsAdmin();

		$I->amOnPage('/wp-admin/admin.php?page=graphql-settings#graphql_persisted_queries_section');
		$I->selectOption("form input[type=radio]", 'only_allowed');

		// Save and see the selection after form submit
		$I->click('Save Changes');
		$I->seeOptionIsSelected('form input[type=radio]', 'only_allowed');
	}

	public function testChangeAllowTriggersPurge( FunctionalTester $I ) {
		$I->wantTo( 'Change the allow/deny grant glopbal setting and verify cache is purged' );

		// Enable caching for this test
		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		// put something in transient cache
		$transient_name = '_transient_gql_cache_foo:bar';
		$I->haveOptionInDatabase( $transient_name, [ 'bizz' => 'bang' ] );

		$transients = unserialize( $I->grabFromDatabase( 'wp_options', 'option_value', [ 'option_name' => $transient_name ] ) );
		codecept_debug( $transients );

		// change the allow/deny setting in admin
		$I->loginAsAdmin();
		$I->amOnPage('/wp-admin/admin.php?page=graphql-settings#graphql_persisted_queries_section');
		$I->selectOption("form input[type=radio]", 'only_allowed');
		$I->click('Save Changes');

		// verify the transient is gone
		$transients = unserialize( $I->grabFromDatabase( 'wp_options', 'option_value', [ 'option_name like' => '_transient_gql_cache_%' ] ) );
		codecept_debug( $transients );
		$I->assertEmpty( $transients );
	}
}
