<?php
/**
 * For a GraphQL query, look for the results in the WP transient cache and return that.
 * If not cached, when return results to client, save results to transient cache for future requests.
 */

namespace WPGraphQL\SmartCache\Cache;

use WPGraphQL\SmartCache\Admin\Settings;

class Results extends Query {

	const GLOBAL_DEFAULT_TTL = 600;

	/**
	 * The cached response of a GraphQL Query execution. False if it doesn't exist.
	 *
	 * @var mixed|bool|array|object
	 */
	protected $cached_result = false;

	public function init() {
		add_filter( 'pre_graphql_execute_request', [ $this, 'get_query_results_from_cache_cb' ], 10, 2 );
		add_action( 'graphql_return_response', [ $this, 'save_query_results_to_cache_cb' ], 10, 8 );
		add_action( 'wpgraphql_cache_purge_nodes', [ $this, 'purge_nodes_cb' ], 10, 2 );
		add_filter( 'graphql_request_results', [ $this, 'add_cache_key_to_response_extensions' ], 10, 7 );

		parent::init();
	}

	/**
	 * Unique identifier for this request is normalized query string, operation and variables
	 *
	 * @param string $query_id queryId from the graphql query request
	 * @param string $query query string
	 * @param array $variables Variables sent with request or null
	 * @param string $operation Name of operation if specified on the request or null
	 *
	 * @return string|false unique id for this request or false if query not provided
	 */
	public function the_results_key( $query_id, $query, $variables = null, $operation_name = null ) {
		return $this->build_key( $query_id, $query, $variables, $operation_name );
	}


	/**
	 * Add a message to the extensions when a GraphQL request is returned from the GraphQL Object Cache
	 *
	 * @param mixed|array|object $response The response of the GraphQL Request
	 *
	 * @return array|mixed
	 */
	public function add_cache_key_to_response_extensions(
		$response,
		$schema,
		$operation_name,
		$query_string,
		$variables,
		$request,
		$query_id
	) {
		$key = $this->the_results_key( $query_id, $query_string, $variables, $operation_name );
		if ( $key ) {
			$message = [];

			// if there's no cache key, or there is no cached_result return the response as-is
			if ( ! empty( $this->cached_result[ $key ] ) ) {
				$message = [
					'message'  => __( 'This response was not executed at run-time but has been returned from the GraphQL Object Cache', 'wp-graphql-smart-cache' ),
					'cacheKey' => $key,
				];
			}

			if ( is_array( $response ) ) {
				$response['extensions']['graphqlSmartCache']['graphqlObjectCache'] = $message;
			} if ( is_object( $response ) ) {
				$response->extensions['graphqlSmartCache']['graphqlObjectCache'] = $message;
			}
		}

		// return the modified response with the graphqlSmartCache message in the extensions output
		return $response;
	}

	/**
	 * Look for a 'cached' response for this exact query, variables and operation name
	 *
	 * @param mixed|array|object $result The response from execution. Array for batch requests,
	 *                                     single object for individual requests
	 * @param WPGraphql/Request $request The Request object
	 *
	 * @return mixed|array|object|null  The response or null if not found in cache
	 */
	public function get_query_results_from_cache_cb( $result, $request ) {
		// if caching is not enabled or the request is authenticated, bail early
		// right now we're not supporting GraphQL cache for authenticated requests.
		// Possibly in the future.
		if ( ! Settings::caching_enabled() || is_user_logged_in() ) {
			return $result;
		}

		// Loop over each request and load the response. If any one are empty, not in cache, return so all get reloaded.
		if ( is_array( $request->params ) ) {
			$result = [];
			foreach ( $request->params as $req ) {
				//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$response = $this->get_result( $req->queryId, $req->query, $req->variables, $req->operation );
				// If any one is null, return all are null.
				if ( null === $response ) {
					return null;
				}
				$result[] = $response;
			}
		} else {
			//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$result = $this->get_result( $request->queryId, $request->query, $request->variables, $request->operation );
		}
		return $result;
	}

	/**
	 * Unique identifier for this request is normalized query string, operation and variables
	 *
	 * @param string $query_id queryId from the graphql query request
	 * @param string $query query string
	 * @param array $variables Variables sent with request or null
	 * @param string $operation Name of operation if specified on the request or null
	 *
	 * @return string|false unique id for this request or false if query not provided
	 */
	public function get_result( $query_id, $query_string, $variables, $operation_name ) {
		$key = $this->the_results_key( $query_id, $query_string, $variables, $operation_name );
		if ( ! $key ) {
			return null;
		}

		$this->cached_result[ $key ] = $this->get( $key );

		return ( false === $this->cached_result[ $key ] ) ? null : $this->cached_result[ $key ];
	}

	/**
	 * When a query response is being returned to the client, build map for each item and this query/queryId
	 * That way we will know what to invalidate on data change.
	 *
	 * @param $filtered_response GraphQL\Executor\ExecutionResult
	 * @param $response GraphQL\Executor\ExecutionResult
	 * @param $request WPGraphQL\Request
	 *
	 * @return void
	 */
	public function save_query_results_to_cache_cb(
		$filtered_response,
		$response,
		$schema,
		$operation_name,
		$query,
		$variables,
		$request,
		$query_id
	) {
		// if caching is not enabled or the request is authenticated, bail early
		// right now we're not supporting GraphQL cache for authenticated requests.
		// Possibly in the future.
		if ( ! Settings::caching_enabled() || is_user_logged_in() ) {
			return;
		}

		$key = $this->the_results_key( $query_id, $query, $variables, $operation_name );
		if ( ! $key ) {
			return;
		}

		// If do not have a cached version, or it expired, save the results again with new expiration
		$cached_result = $this->get( $key );

		if ( false === $cached_result ) {
			$expiration = \get_graphql_setting( 'global_ttl', self::GLOBAL_DEFAULT_TTL, 'graphql_cache_section' );

			$this->save( $key, $filtered_response, $expiration );
		}
	}

	/**
	 * Searches the database for all graphql transients matching our prefix
	 *
	 * @return int|false  Count of the number deleted. False if error, nothing to delete or caching not enabled.
	 * @return bool True on success, false on failure.
	 */
	public function purge_all() {
		if ( ! Settings::caching_enabled() ) {
			return false;
		}

		return parent::purge_all();
	}

	/**
	 * When an item changed and this callback is triggered to delete results we have cached for that list of nodes
	 * Related to the data type that changed.
	 */
	public function purge_nodes_cb( $id, $nodes ) {
		if ( is_array( $nodes ) && ! empty( $nodes ) ) {
			foreach ( $nodes as $request_key ) {
				$this->delete( $request_key );
			}

			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			graphql_debug( 'Graphql delete nodes', [ 'nodes' => $nodes ] );
		}
	}
}
