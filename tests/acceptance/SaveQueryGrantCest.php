<?php

/**
 * Test the allow/deny selection for query grant access.
 */

class SaveQueryGrantCest
{
    public function adminSetQueryToAllowAndDenyTest(AcceptanceTester $I)
    {
        $I->loginAsAdmin();

        // Create a new graphql query with title and description
        $I->amOnPage('/wp-admin/post-new.php?post_type=graphql_query');
        $I->fillField('post_title', 'My Test Grant 1');
        $I->checkOption('graphql_query_grant');

        // Save and see the allow/deny grant after form submit
        $I->click('Publish');
        $I->seeCheckboxIsChecked('graphql_query_grant'); // I suppose user didn't check the first checkbox in form.

        // Now deselect the option and verify the status
        $I->uncheckOption('graphql_query_grant');
        $I->click('Publish');
        $I->dontSeeCheckboxIsChecked('graphql_query_grant'); // I suppose user didn't agree to terms

        // Select to allow then check the listing on the admin page table list of queries
        $I->checkOption('graphql_query_grant');
        $I->click('Publish');
        $I->amOnPage('/wp-admin/edit.php?post_type=graphql_query');
        $I->see('allow', "//table/tbody/tr[1]/td[@data-colname='Allow/Deny']");

    }
 
}
