<?php
/**
 * For a GraphQL query, look for the results in the WP transient cache and return that.
 * If not cached, when return results to client, save results to transient cache for future requests.
 */

namespace WPGraphQL\Labs\Cache;

use WPGraphQL\Labs\Admin\Settings;
use WPGraphQL\Labs\Document;

class Query {

	const TYPE_NAME          = 'gql_cache';
	const GLOBAL_DEFAULT_TTL = 600;

	public function init() {
		add_filter( 'pre_graphql_execute_request', [ $this, 'get_query_results_from_cache_cb' ], 10, 2 );
		add_action( 'graphql_return_response', [ $this, 'save_query_results_to_cache_cb' ], 10, 7 );
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
	public function get_cache_key( $query_id, $query, $variables = null, $operation = null ) {
		// Unique identifier for this request is normalized query string, operation and variables
		// If request is by queryId, get the saved query string, which is already normalized
		if ( $query_id ) {
			$saved_query = new Document();
			$query       = $saved_query->get( $query_id );
		} elseif ( $query ) {
			// Query string provided, normalize it
			$query_ast = \GraphQL\Language\Parser::parse( $query );
			$query     = \GraphQL\Language\Printer::doPrint( $query_ast );
		}

		if ( ! $query ) {
			return false;
		}

		// WP_User
		$user = wp_get_current_user();

		$parts     = [
			'query'     => $query,
			'variables' => $variables,
			'operation' => $operation,
			'user'      => $user->ID,
		];
		$unique_id = hash( 'sha256', wp_json_encode( $parts ) );

		// This unique operation identifier
		return self::TYPE_NAME . '_' . $unique_id;
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
		if ( ! Settings::caching_enabled() ) {
			return $result;
		}
		$key = $this->get_cache_key( $request->params->queryId, $request->params->query, $request->params->variables, $request->params->operation );
		if ( ! $key ) {
			return null;
		}

		$cached_result = $this->get( $key );
		return ( false === $cached_result ) ? null : $cached_result;
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
		$operation,
		$query,
		$variables,
		$request
	) {
		if ( ! Settings::caching_enabled() ) {
			return;
		}
		$key = $this->get_cache_key( $request->params->queryId, $request->params->query, $request->params->variables, $request->params->operation );
		if ( ! $key ) {
			return;
		}

		// If do not have a cached version, or it expired, save the results again with new expiration
		$cached_result = $this->get( $key );

		if ( false === $cached_result ) {
			$expiration = \get_graphql_setting( 'global_ttl', self::GLOBAL_DEFAULT_TTL, 'graphql_cache_section' );

			$this->save( $key, $response, $expiration );
		}
	}

	/**
	 * Get the data from cache/transient based on the provided key
	 *
	 * @param string unique id for this request
	 * @return mixed|array|object|null  The graphql response or null if not found
	 */
	public function get( $key ) {
		return get_transient( $key );
	}

	/**
	 * Converts GraphQL query result to spec-compliant serializable array using provided function
	 *
	 * @param string unique id for this request
	 * @param mixed|array|object|null  The graphql response
	 * @param int Time in seconds for the data to persist in cache. Zero means no expiration.
	 */
	public function save( $key, $data, $expire = DAY_IN_SECONDS ) {
		set_transient(
			$key,
			is_array( $data ) ? $data : $data->toArray(),
			$expire
		);
	}

	/**
	 * Searches the database for all graphql transients matching our prefix
	 *
	 * @return int|false  Count of the number deleted. False if error, nothing to delete or caching not enabled.
	 */
	public function purge_all() {
		global $wpdb;

		if ( ! Settings::caching_enabled() ) {
			return false;
		}

		$prefix = self::TYPE_NAME;

		// The transient string + our prefix as it is stored in the options database
		$transient_option_name = $wpdb->esc_like( '_transient_' . $prefix . '_' ) . '%';

		// Make database query to get out transients
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$transients = $wpdb->get_results( $wpdb->prepare( "SELECT `option_name` FROM $wpdb->options WHERE `option_name` LIKE %s", $transient_option_name ), ARRAY_A ); //db call ok

		if ( is_wp_error( $transients ) ) {
			return false;
		}

		if ( ! $transients || ! is_array( $transients ) ) {
			return true;
		}

		// Loop through our transients
		foreach ( $transients as $transient ) {
			// Remove this string from the option_name to get the name we will use on delete
			$key = str_replace( '_transient_', '', $transient['option_name'] );
			delete_transient( $key );
		}

		return true;
	}
}
