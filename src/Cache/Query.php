<?php
/**
 * For a GraphQL query, look for the results in the WP transient cache and return that.
 * If not cached, when return results to client, save results to transient cache for future requests.
 */

namespace WPGraphQL\Labs\Cache;

use WPGraphQL\Labs\Document;

class Query {

	const GROUP_NAME = 'gql_cache';

	// The storage object for the actual system of choice transient, database, object, memory, etc
	public static $storage = null;

	public function init() {
		if ( ! self::$storage ) {
			self::$storage = apply_filters(
				'graphql_cache_storage_object', //phpcs:ignore
				wp_using_ext_object_cache() ? new WpCache() : new Transient()
			);
		}

		// register_graphql_mutation( 'purgePostMutation', [
		// 	'description' => 'Graphql Purge Post Mutation',
		// 	'inputFields' => [
		// 		'purgePostInput' => [
		// 			'type'        => 'String',
		// 			'description' => 'thing thing',
		// 		]
		// 	],
		// 	'outputFields' => [
		// 		'purgePostOutput' => [
		// 			'type'        => 'String',
		// 			'description' => 'thing thing',
		// 			'resolve' => function ( $payload, $args, AppContext $context, ResolveInfo $info ) {
		// 				return isset( $payload['purgePostOutput'] ) ? $payload['purgePostOutput'] : null;
		// 			}
		// 		]
		// 	],
		// 	'mutateAndGetPayload' => function ( $input, $context, $info ) {
		// 		// Do any logic here to sanitize the input, check user capabilities, etc
		// 		$exampleOutput = null;
		// 		if ( ! empty( $input['purgePostInput'] ) ) {
		// 			$exampleOutput = 'Your input was: ' . $input['purgePostInput'];
		// 		}
		// 		return [
		// 			'purgePostOutput' => $exampleOutput,
		// 		];
		// 	}
		// ] );

		// register_graphql_mutation( 'exampleMutation', [

		// 	# inputFields expects an array of Fields to be used for inputting values to the mutation
		// 	'inputFields'         => [
		// 		'exampleInput' => [
		// 			'type' => 'String',
		// 			'description' => __( 'Description of the input field', 'your-textdomain' ),
		// 		]
		// 	],
		
		// 	# outputFields expects an array of fields that can be asked for in response to the mutation
		// 	# the resolve function is optional, but can be useful if the mutateAndPayload doesn't return an array
		// 	# with the same key(s) as the outputFields
		// 	'outputFields'        => [
		// 		'exampleOutput' => [
		// 			'type' => 'String',
		// 			'description' => __( 'Description of the output field', 'your-textdomain' ),
		// 			'resolve' => function( $payload, $args, $context, $info ) {
		// 						   return isset( $payload['exampleOutput'] ) ? $payload['exampleOutput'] : null;
		// 			}
		// 		]
		// 	],
		
		// 	# mutateAndGetPayload expects a function, and the function gets passed the $input, $context, and $info
		// 	# the function should return enough info for the outputFields to resolve with
		// 	'mutateAndGetPayload' => function( $input, $context, $info ) {
		// 		// Do any logic here to sanitize the input, check user capabilities, etc
		// 		$exampleOutput = null;
		// 		if ( ! empty( $input['exampleInput'] ) ) {
		// 			$exampleOutput = 'Your input was: ' . $input['exampleInput'];
		// 		}
		// 		return [
		// 			'exampleOutput' => $exampleOutput,
		// 		];
		// 	}
		// ] );
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
	public function build_key( $query_id, $query, $variables = null, $operation = null ) {
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

		$parts = [
			'query'     => $query,
			'variables' => $variables,
			'operation' => $operation,
			'user'      => $user->ID,
		];
		return hash( 'sha256', wp_json_encode( $parts ) );
	}

	/**
	 * Get the data from cache/transient based on the provided key
	 *
	 * @param string unique id for this request
	 * @return mixed|array|object|null  The graphql response or null if not found
	 */
	public function get( $key ) {
		return self::$storage->get( $key );
	}

	/**
	 * Converts GraphQL query result to spec-compliant serializable array using provided function
	 *
	 * @param string unique id for this request
	 * @param mixed|array|object|null  The graphql response
	 * @param int Time in seconds for the data to persist in cache. Zero means no expiration.
	 *
	 * @return bool False if value was not set and true if value was set.
	 */
	public function save( $key, $data, $expire = DAY_IN_SECONDS ) {
		return self::$storage->set( $key, $data, $expire );
	}

	/**
	 * Delete the data from cache/transient based on the provided key
	 *
	 * @param string unique id for this request
	 * @return bool True on successful removal, false on failure.
	 */
	public function delete( $key ) {
		return self::$storage->delete( $key );
	}

	/**
	 * Searches the database for all graphql transients matching our prefix
	 *
	 * @return int|false  Count of the number deleted. False if error, nothing to delete or caching not enabled.
	 * @return bool True on success, false on failure.
	 */
	public function purge_all() {
		return self::$storage->purge_all();
	}
}
