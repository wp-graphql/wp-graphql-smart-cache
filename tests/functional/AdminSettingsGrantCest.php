<?php

/**
 * Test the wp-graphql settings page for global allow/deny.
 */

class AdminSettingsGrantCest
{
	public function _after( FunctionalTester $I ) {
		$I->dontHaveOptionInDatabase( 'graphql_persisted_queries_section'  );
	}

	public function saveAllowOnlySettingsTest( FunctionalTester $I ) {
			$I->loginAsAdmin();

			$I->amOnPage('/wp-admin/admin.php?page=graphql#graphql_persisted_queries_section');
			$I->selectOption("form input[type=radio]", 'only_allowed');

			// Save and see the selection after form submit
			$I->click('Save Changes');
			$I->seeOptionIsSelected('form input[type=radio]', 'only_allowed');
	}

}
