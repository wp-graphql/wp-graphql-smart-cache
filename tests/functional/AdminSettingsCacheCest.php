<?php

/**
 * Test the wp-graphql settings page for cache
 */

class AdminSettingsCacheCest
{
	public function _after( FunctionalTester $I ) {
		$I->dontHaveOptionInDatabase( 'graphql_cache_section'  );
	}

	public function selectCacheSettingsTest( FunctionalTester $I ) {
			$I->loginAsAdmin();
			$I->amOnPage('/wp-admin/admin.php?page=graphql#graphql_cache_section');

			// Save and see the selection after form submit
			$I->checkOption("//input[@type='checkbox' and @name='graphql_cache_section[cache_toggle]']");
			$I->click('Save Changes');
			$I->seeCheckboxIsChecked("//input[@type='checkbox' and @name='graphql_cache_section[cache_toggle]']");

			$I->uncheckOption("//input[@type='checkbox' and @name='graphql_cache_section[cache_toggle]']");
			$I->click('Save Changes');
			$I->dontSeeCheckboxIsChecked("//input[@type='checkbox' and @name='graphql_cache_section[cache_toggle]']");
	}

}
