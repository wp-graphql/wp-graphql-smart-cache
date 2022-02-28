<?php
/**
 * For a GraphQL query, look for the results in the WP transient cache and return that.
 * If not cached, when return results to client, save results to transient cache for future requests.
 */

namespace WPGraphQL\Labs\Cache;

use WPGraphQL\Labs\Admin\Settings;

class Collection extends Query {

	const GLOBAL_DEFAULT_TTL = 600;

	public function init() {
		add_action( 'graphql_return_responseX', [ $this, 'save_query_results_to_cache_cb' ], 10, 7 );

		add_action( 'wp_insert_postX', [ $this, 'on_post_insert' ], 10, 2 );

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
	public function the_node_key( $type ) {
		return 'node:' . $type;
	}

	public function the_meta_key( $query_id, $query, $variables, $operation ) {
		return 'meta:' . $this->build_key( $query_id, $query, $variables, $operation );
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
		// The path this request came in on.
		$url = Settings::graphql_endpoint() . '?' . http_build_query( $request->app_context->request );

		// Save the url this query request came in on, so we can purge it later when something changes
		$meta_key            = $this->the_meta_key( $request->params->queryId, $request->params->query, $request->params->variables, $request->params->operation );
		$meta_data           = $this->get( $query_key );
		$meta_data['urls'][] = $url;
		$meta_data['urls']   = array_unique( $meta_data['urls'] );
		$this->save( $meta_key, $meta_data );

		// Also associate the node type 'post' with this query for look up later
		$node_key    = $this->the_node_key( 'post' );
		$node_data   = $this->get( $node_key );
		$node_data[] = $meta_key;
		$node_data[] = $this->the_results_key( $request->params->queryId, $request->params->query, $request->params->variables, $request->params->operation );
		$this->save( $node_key, array_unique( $node_data ) );
	}

	/**
	 * Fires once a post has been saved.
	 *
	 * @since 1.5.0
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	 */
	public function on_post_insert( $post_id, $post ) {
		if ( 'post' !== $post->post_type ) {
			return;
		}

		// TODO: Look up the specific post/node/resource to purge vs $response = $this->purge_all();

		// When any post changes, look up graphql queries previously queried containing post resources and purge those
		$key   = $this->the_node_key( 'post' );
		$nodes = $this->get( $key );

		// Get the list of queries associated with this key
		$paths = [];
		if ( is_array( $nodes ) ) {
			foreach ( $nodes as $node_key ) {
				$query = $this->get( $node_key );
				if ( $query && isset( $query['urls'] ) && is_array( $query['urls'] ) ) {
					// Purge specific paths
					array_push( $paths, $query['urls'] );
				}
				$this->delete( $node_key );
			}
		}
		// tell varnish about the changes? $paths
	}
}
