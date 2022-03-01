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
		add_action( 'graphql_return_response', [ $this, 'save_query_mapping_cb' ], 10, 7 );
		add_action( 'wp_insert_post', [ $this, 'on_post_insert' ], 10, 2 );

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
	public function nodes_key( $type ) {
		return 'node:' . $type;
	}

	public function url_key( $request_key ) {
		return 'urls:' . $request_key;
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
	public function save_query_mapping_cb(
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
		$request_key = $this->build_key( $request->params->queryId, $request->params->query, $request->params->variables, $request->params->operation );
		$url_key     = $this->url_key( $request_key );
		$urls        = $this->get( $url_key );
		$urls[]      = $url;
		$urls        = array_unique( $urls );
		$this->save( $url_key, $urls );

		// Also associate the node type 'post' with this query for look up later
		$node_key = $this->nodes_key( 'post' );
		$nodes    = $this->get( $node_key );
		$nodes[]  = $request_key;
		$nodes    = array_unique( $nodes );

		$this->save( $node_key, $nodes );
	}

	/**
	 * Fires once a post has been saved.
	 *
	 * @since 1.5.0
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function on_post_insert( $post_id, $post ) {
		if ( 'post' !== $post->post_type ) {
			return;
		}

		// Look up the specific post/node/resource to purge vs $response = $this->purge_all();
		// When any post changes, look up graphql queries previously queried containing post resources and purge those
		$key   = $this->nodes_key( 'post' );
		$nodes = $this->get( $key );

		// Delete the cached results associated with this post/key
		if ( is_array( $nodes ) ) {
			foreach ( $nodes as $request_key ) {
				$this->delete( $request_key );
			}
		}
	}
}
