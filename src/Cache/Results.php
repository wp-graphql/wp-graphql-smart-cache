<?php
/**
 * For a GraphQL query, look for the results in the WP transient cache and return that.
 * If not cached, when return results to client, save results to transient cache for future requests.
 */

namespace WPGraphQL\SmartCache\Cache;

use WPGraphQL;
use WPGraphQL\Request;
use WPGraphQL\SmartCache\Admin\Settings;

class Results extends Query {

	const GLOBAL_DEFAULT_TTL = 600;

	/**
	 * Indicator of the GraphQL Query execution cached or not.
	 *
	 * @array bool
	 */
	protected $is_cached = [];

	/**
	 * @var
	 */
	protected $request;

	public function init() {
		add_filter( 'pre_graphql_execute_request', [ $this, 'get_query_results_from_cache_cb' ], 10, 2 );
		add_action( 'graphql_return_response', [ $this, 'save_query_results_to_cache_cb' ], 10, 8 );
		add_action( 'wpgraphql_cache_purge_nodes', [ $this, 'purge_nodes_cb' ], 10, 2 );
		add_action( 'wpgraphql_cache_purge_all', [ $this, 'purge_all_cb' ], 10, 0 );
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
	 * @param WPSchema   $schema    The schema object for the root query
	 * @param string     $operation The name of the operation
	 * @param string     $query     The query that GraphQL executed
	 * @param array|null $variables Variables to passed to your GraphQL request
	 * @param Request    $request   Instance of the Request
	 * @param string|null $query_id The query id that GraphQL executed
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

			// If we know that the results were pulled from cache, add messaging
			if ( isset( $this->is_cached[ $key ] ) && true === $this->is_cached[ $key ] ) {
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
	 * @param mixed|array|object $result   The response from execution. Array for batch requests,
	 *                                     single object for individual requests
	 * @param Request            $request
	 *
	 * @return mixed|array|object|null  The response or null if not found in cache
	 */
	public function get_query_results_from_cache_cb( $result, Request $request ) {
		$this->request = $request;

		// if caching is not enabled or the request is authenticated, bail early
		// right now we're not supporting GraphQL cache for authenticated requests.
		// Possibly in the future.
		if ( ! $this->is_object_cache_enabled() ) {
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
			$result = $this->get_result( $request->params->queryId, $request->params->query, $request->params->variables, $request->params->operation );
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
	 * @return string|null The response or null if not found in cache
	 */
	public function get_result( $query_id, $query_string, $variables, $operation_name ) {
		$key = $this->the_results_key( $query_id, $query_string, $variables, $operation_name );
		if ( ! $key ) {
			return null;
		}

		$result = $this->get( $key );
		if ( false === $result ) {
			return null;
		}

		$this->is_cached[ $key ] = true;

		return $result;
	}

	/**
	 * Determine whether object cache is enabled
	 *
	 * @return bool
	 */
	protected function is_object_cache_enabled() {

		// default to disabled
		$enabled = false;

		// if caching is enabled, respect it
		if ( Settings::caching_enabled() ) {
			$enabled = true;
		}

		// however, if the user is logged in, we should bypass the cache
		if ( is_user_logged_in() ) {
			$enabled = false;
		}

		// @phpcs:ignore
		return (bool) apply_filters( 'graphql_cache_is_object_cache_enabled', $enabled, $this->request );
	}

	/**
	 * When a query response is being returned to the client, build map for each item and this query/queryId
	 * That way we will know what to invalidate on data change.
	 *
	 * @param ExecutionResult $filtered_response The response after GraphQL Execution has been
	 *                                           completed and passed through filters
	 * @param ExecutionResult $response          The raw, unfiltered response of the GraphQL
	 *                                           Execution
	 * @param Schema          $schema            The WPGraphQL Schema
	 * @param string          $operation         The name of the Operation
	 * @param string          $query             The query string
	 * @param array           $variables         The variables for the query
	 * @param Request         $request           The WPGraphQL Request object
	 * @param string|null     $query_id          The query id that GraphQL executed
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

		// if caching is NOT enabled
		// or the request is authenticated
		// or the request is a GET request
		// bail early
		// right now we're not supporting GraphQL cache for authenticated requests,
		// and we're recommending caching clients (varnish, etc) handle GET request caching
		//
		// Possibly in the future we'll have solutions for authenticated request caching
		if ( ! $this->is_object_cache_enabled() ) {
			return;
		}

		$key = $this->the_results_key( $query_id, $query, $variables, $operation_name );
		if ( ! $key ) {
			return;
		}

		// If we do not have a cached version, or it expired, save the results again with new expiration
		$cached_result = $this->get( $key );

		if ( false === $cached_result ) {
			$expiration = \get_graphql_setting( 'global_ttl', self::GLOBAL_DEFAULT_TTL, 'graphql_cache_section' );

			$this->save( $key, $filtered_response, $expiration );
		}
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

	/**
	 * Purge the local cache results if enabled
	 */
	public function purge_all_cb() {
		$this->purge_all();
	}
}
