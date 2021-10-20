<?php
/**
 * Test the allow/deny selection for individual query grant access.
 * 
 * 		$option['graphql_persisted_queries_section'] = [
 *			'grant_mode' => 'public',
 *		];
 *
 */

namespace WPGraphQL\PersistedQueries;

use WPGraphQL\PersistedQueries\SavedQuery;
use WPGraphQL\PersistedQueries\SavedQueryGrant;
use WPGraphQL\PersistedQueries\Utils;

class AllowDenyQueryRulesTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		delete_option( 'graphql_persisted_queries_section' );
	}

	public function _after() {
		delete_option( 'graphql_persisted_queries_section' );
	}

	/**
	 * Set a persisted query with id.
	 * 
	 * @param query_sting The graphql query
	 * @param grant SavedQueryGrant allow, deny, default, false
	 *
	 * @return string The query id for the query string
	 */
	public function _createAPersistedQuery( $query_string, $grant ) {
		$query_id = Utils::generateHash( $query_string );

		$persisted_query = new SavedQuery();
		$post_id = $persisted_query->save( $query_id, $query_string);

		$query_grant = new SavedQueryGrant();
		$query_grant->save( $post_id, $grant );

		return $query_id;

		// WP_UnitTest_Factory_For_Post
		$post_id = $this->tester->factory()->post->create( [
			'post_content' => $query_string,
			'post_name'    => $query_id,
			'post_status'  => 'public',
			'post_type'    => 'graphql_query',
		] );

		// Set the query id hash for the query
		// WP_UnitTest_Factory_For_Term
		$term_id = $this->tester->factory()->term->create( [
			'name' => $query_id,
			'taxonomy' => 'graphql_query_label',
		] );
		//$this->tester->factory()->term->add_post_terms( $post_id, $query_id, 'graphql_query_label' );
		$id = wp_set_post_terms( $post_id, [$term_id], 'graphql_query_label' );
		codecept_debug( get_term($id, 'graphql_query_label') );

		// Set the allow/deny grant for this persisted query
		//$this->tester->factory()->term->add_post_terms( $post_id, $grant, 'graphql_query_grant' );
		$id = wp_set_post_terms( $post_id, [$grant], 'graphql_query_grant' );
		codecept_debug( get_term($id, 'graphql_query_grant') );

		codecept_debug( get_post($post_id, 'graphql_query') );
		codecept_debug( get_term($term_id, 'graphql_query_label') );
		codecept_debug( wp_get_post_terms($post_id) );

		return $query_id;
	}

	public function _assertError( $response, $message ) {
		$this->assertArrayNotHasKey( 'data', $response, 'Response has data but should have error instead' );
		$this->assertEquals( $response['errors'][0]['message'], $message, 'Response should have an error' );
	}

	public function testDeniedQueryWorksWhenNoGlobalSettingIsSet() {
		delete_option( 'graphql_persisted_queries_section' );

		$post_id = $this->tester->factory()->post->create();

		// Verify persisted query set as denied still works
		$query_string = '{ __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, SavedQueryGrant::DENY );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( 'RootQuery', $result['data']['__typename'] );

		// Verify a non-persisted query still works
		$non_persisted_query = 'query notPersisted { posts { nodes { slug uri } } }';
		$result = graphql( [ 'query' => $non_persisted_query ] );
		$this->assertEquals( sprintf("/?p=%d", $post_id), $result['data']['posts']['nodes'][0]['uri'] );
	}

	public function testDeniedQueryWorksWhenWhenGlobalPublicIsSet() {
		add_option( 'graphql_persisted_queries_section', [ 'grant_mode' => SavedQueryGrant::GLOBAL_PUBLIC ] );
		$post_id = $this->tester->factory()->post->create();

		// Verify persisted query set as denied still works
		$query_string = 'query setAsDenied { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, SavedQueryGrant::DENY );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( 'RootQuery', $result['data']['__typename'] );

		// Verify a non-persisted query still works
		$non_persisted_query = 'query notPersisted { posts { nodes { slug uri } } }';
		$result = graphql( [ 'query' => $non_persisted_query ] );
		$this->assertEquals( sprintf("/?p=%d", $post_id), $result['data']['posts']['nodes'][0]['uri'] );
	}

	public function testWhenGlobalOnlyAllowedIsSet() {
		add_option( 'graphql_persisted_queries_section', [ 'grant_mode' => SavedQueryGrant::GLOBAL_ALLOWED ] );
		$post_id = $this->tester->factory()->post->create();

		// Verify allowed query works
		$query_string = 'query setAsAllowed { posts { nodes { slug uri } } }';
		$query_id = $this->_createAPersistedQuery( $query_string, SavedQueryGrant::ALLOW );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( sprintf("/?p=%d", $post_id), $result['data']['posts']['nodes'][0]['uri'] );

		// Verify denied query doesn't work
		$query_string = 'query setAsDenied { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, SavedQueryGrant::DENY );
		$result = graphql( [ 'query' => $query_string ] );
		$this->_assertError( $result, 'This persisted query document has been blocked.' );

		// Verify default query doesn't work
		$query_string = 'query setAsDefault { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, SavedQueryGrant::USE_DEFAULT );
		$result = graphql( [ 'query' => $query_string ] );
		$this->_assertError( $result, 'This persisted query document has been blocked.' );

		// Verify no selection doesn't work
		$query_string = 'query setAsNone { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, SavedQueryGrant::NOT_SELECTED_DEFAULT );
		$result = graphql( [ 'query' => $query_string ] );
		$this->_assertError( $result, 'This persisted query document has been blocked.' );

		// Verify a non-persisted query doesn't work
		$post_id = $this->tester->factory()->post->create();
		$non_persisted_query = 'query notPersisted { __typename }';
		$result = graphql( [ 'query' => $non_persisted_query ] );
		$this->_assertError( $result, 'Not Found. Only specific persisted queries allowed.' );
	}

	public function testWhenGlobalDenySomeIsSet() {
		add_option( 'graphql_persisted_queries_section', [ 'grant_mode' => SavedQueryGrant::GLOBAL_DENIED ] );
		$post_id = $this->tester->factory()->post->create();

		// Verify allowed query works
		$query_string = 'query setAsAllowed { posts { nodes { slug uri } } }';
		$query_id = $this->_createAPersistedQuery( $query_string, SavedQueryGrant::ALLOW );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( sprintf("/?p=%d", $post_id), $result['data']['posts']['nodes'][0]['uri'] );

		// Verify denied query doesn't work
		$query_string = 'query setAsDenied { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, SavedQueryGrant::DENY );
		$result = graphql( [ 'query' => $query_string ] );
		$this->_assertError( $result, 'This persisted query document has been blocked.' );

		// Verify default query works
		$query_string = 'query setAsDefault { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, SavedQueryGrant::USE_DEFAULT );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( 'RootQuery', $result['data']['__typename'] );

		// Verify no selection works
		$query_string = 'query setAsNone { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, SavedQueryGrant::NOT_SELECTED_DEFAULT );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( 'RootQuery', $result['data']['__typename'] );

		// Verify a non-persisted query still works
		$non_persisted_query = 'query notPersisted { __typename }';
		$result = graphql( [ 'query' => $non_persisted_query ] );
		$this->assertEquals( 'RootQuery', $result['data']['__typename'] );
	}
}
