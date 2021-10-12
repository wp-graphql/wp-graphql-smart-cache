<?php

class SaveQueryDescriptionCest
{
    public function _before(AcceptanceTester $I)
    {
    }

    public function createQueryWithDescriptionTest(AcceptanceTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage('/wp-admin/post-new.php?post_type=graphql_query');
        $I->fillField('post_title', 'My Test Query 1');
        $I->fillField('graphql_query_description', 'Foo Test Description');
        $I->click('Publish');
        $I->seeInField('graphql_query_description', 'Foo Test Description');

        // Open the admin page table list of queries.
        $I->amOnPage('/wp-admin/edit.php?post_type=graphql_query');
        // Verify in the table view the title and description are valid
        $I->see('My Test Query 1', "//table/tbody/tr[1]/td[1]");
        $I->see('Foo Test Description', "//table/tbody/tr[1]/td[2]");
    }
 
}
