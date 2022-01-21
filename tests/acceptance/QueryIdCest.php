<?php

class QueryIdCest
{
    public function queryIdThatDoesNotExistTest(AcceptanceTester $I)
    {
        $I->sendGet('graphql', [ 'queryId' => '1234' ] );
        $I->seeResponseContainsJson([
            'errors' => [
                'message' => 'Query Not Found 1234'
            ]
        ]);
    }
 
    public function saveQueryAndHashTest(AcceptanceTester $I)
    {
        $I->sendPost('graphql', [
            'query' => '{__typename}',
            'queryId' => '8d8f7365e9e86fa8e3313fcaf2131b801eafe9549de22373089cf27511858b39'
        ] );
        $I->seeResponseContainsJson([
           'data' => [
               '__typename' => 'RootQuery'
           ]
        ]);

        $I->sendPost('graphql', [
            'query' => '{ __typename }',
            'extensions' => [
                "persistedQuery" => [
                    "version" => 1,
                    "sha256Hash" => "8d8f7365e9e86fa8e3313fcaf2131b801eafe9549de22373089cf27511858b39"
                ]
            ]
        ] );
        $I->seeResponseContainsJson([
            'data' => [
                '__typename' => 'RootQuery'
            ]
        ]);
    }
 
}
