<?php

class SaveQueryDescriptionCest
{
    public function createQueryWithDescriptionTest(AcceptanceTester $I)
    {
        $I->loginAsAdmin();

        // Create a new graphql query with title and description
        $I->amOnPage('/wp-admin/post-new.php?post_type=graphql_document');
        $I->fillField('post_title', 'My Test Query 1');
        $I->fillField('excerpt', 'Foo Test Description');

        // Save and see the description on same page
        $I->click('Publish');
        $I->seeInField('excerpt', 'Foo Test Description');

        // Open the admin page table list of queries.
        $I->amOnPage('/wp-admin/edit.php?post_type=graphql_document');
        // Verify in the table view the title and description are valid
        $I->see('My Test Query 1', "//table/tbody/tr[1]/td[1]");
        $I->see('Foo Test Description', '//table/tbody/tr[1]/td[@data-colname="Description"]');
    }
 
}
