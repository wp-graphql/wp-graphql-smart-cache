<?php

class LookupCest
{
    public function _before(AcceptanceTester $I)
    {
    }

    public function queryIdThatDoesNotExistTest(AcceptanceTester $I)
    {
       $I->sendGet('graphql', [ 'queryId' => '1234' ] );
       $I->seeResponseContainsJson([
           'errors' => [
               'message' => 'Query Not Found 1234'
           ]
       ]);
    }
 
}
