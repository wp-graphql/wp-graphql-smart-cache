<?php
/**
 * For a GraphQL query, look for the results in the WP transient cache and return that.
 * If not cached, when return results to client, save results to transient cache for future requests.
 */

namespace WPGraphQL\PersistedQueries;

class CachedResponse {

	const TYPE_NAME = 'graphql_response_cache';

	public function init() {
		add_filter( 'pre_graphql_execute_request', [ $this, 'filter_get_query_results_from_cache' ], 10, 2 );
		add_action( 'graphql_return_response', [ $this, 'action_save_query_results_to_cache' ], 10, 7 );
	}

	public function get_cache_key( $query_id, $query, $variables, $operation ) {
		// Unique identifier for this request is query, operation and variables
		if ( $query ) {
			$query_ast = \GraphQL\Language\Parser::parse( $query );
			$query     = \GraphQL\Language\Printer::doPrint( $query_ast );
		}

		$action    = [
			'queryId'   => $query_id,
			'query'     => $query,
			'variables' => $variables,
			'operation' => $operation,
		];
		$unique_id = hash( 'sha256', wp_json_encode( $action ) );

		// This unique operation identifier
		return self::TYPE_NAME . '_' . $unique_id;
	}

	/**
	 * Look for a 'cached' response for this exact query, variables and operation name
	 *
	 * @param WPGraphql/Request
	 */
	public function filter_get_query_results_from_cache(
		$result,
		$request
	) {
		$key = $this->get_cache_key( $request->params->queryId, $request->params->query, $request->params->variables, $request->params->operation );

		$cached_result = get_transient( $key );
		if ( false === $cached_result ) {
			return null;
		}

		return $cached_result;
	}

	/**
	 * When a query response is being returned to the client, build map for each item and this query/queryId
	 * That way we will know what to invalidate on data change.
	 *
	 * @param $filtered_response GraphQL\Executor\ExecutionResult
	 * @param $response GraphQL\Executor\ExecutionResult
	 * @param $request WPGraphQL\Request
	 */
	public function action_save_query_results_to_cache(
		$filtered_response,
		$response,
		$schema,
		$operation,
		$query,
		$variables,
		$request
	) {
		$key = $this->get_cache_key( $request->params->queryId, $request->params->query, $request->params->variables, $request->params->operation );

		// If do not have a cached version, or it expired, save the results again with new expiration
		$cached_result = get_transient( $key );

		if ( false === $cached_result ) {
			// Converts GraphQL query result to spec-compliant serializable array using provided
			set_transient( $key, $response->toArray(), DAY_IN_SECONDS );
		}
	}

}
