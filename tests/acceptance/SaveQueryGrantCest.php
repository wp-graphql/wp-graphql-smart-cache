<?php

/**
 * Test the allow/deny selection for individual query grant access.
 */

class SaveQueryGrantCest
{
    public function adminSetQueryToAllowAndDenyTest(AcceptanceTester $I)
    {
        $I->loginAsAdmin();

        // Create a new graphql query with title and set the allow/deny
        $I->amOnPage('/wp-admin/post-new.php?post_type=graphql_query');
        $I->fillField('post_title', 'My Test Grant 1');

        // Now select different option and verify the status
        $I->selectOption('graphql_query_grant', 'allow');
        $I->click('publish');
        $I->seeOptionIsSelected('form input[id=graphql_query_grant_allow]', 'allow');

        $I->selectOption('graphql_query_grant', 'deny');
        $I->click('publish');
        $I->seeOptionIsSelected('form input[id=graphql_query_grant_deny]', 'deny');

        // Check the listing on the admin page table list of queries
        $I->amOnPage('/wp-admin/edit.php?post_type=graphql_query');
        $I->see('deny', '//table/tbody/tr[1]/td[@data-colname="Allow/Deny"]/a');
    }
 
}