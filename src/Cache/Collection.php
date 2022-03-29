<?php
/**
 * For a GraphQL query, look for the results in the WP transient cache and return that.
 * If not cached, when return results to client, save results to transient cache for future requests.
 */

namespace WPGraphQL\Labs\Cache;

use WPGraphQL\Labs\Admin\Settings;
use GraphQLRelay\Relay;

class Collection extends Query {

	// Nodes that are part of the current/in-progress/excuting query
	public $nodes = [];

	public function init() {
		add_action( 'graphql_return_response', [ $this, 'save_query_mapping_cb' ], 10, 7 );
		add_action( 'wp_insert_post', [ $this, 'on_post_insert' ], 10, 2 );
		add_filter( 'pre_graphql_execute_request', [ $this, 'before_executing_query_cb' ], 10, 2 );
		add_filter( 'graphql_dataloader_get_model', [ $this, 'data_loaded_process_cb' ], 10, 4 );

		parent::init();
	}

	public function before_executing_query_cb( $result, $request ) {
		// Consider this the start of query execution. Clear if we had a list of saved nodes
		$this->runtime_nodes = [];
		return $result;
	}

	/**
	 * Filter the model before returning.
	 *
	 * @param mixed              $model The Model to be returned by the loader
	 * @param mixed              $entry The entry loaded by dataloader that was used to create the Model
	 * @param mixed              $key   The Key that was used to load the entry
	 * @param AbstractDataLoader $this  The AbstractDataLoader Instance
	 */
	public function data_loaded_process_cb( $model, $entry, $key, $data_loader ) {
		if ( $model->id ) {
			$this->runtime_nodes[] = $model->id;
		}
		return $model;
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
	public function node_key( $type ) {
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
		$request_key = $this->build_key( $request->params->queryId, $request->params->query, $request->params->variables, $request->params->operation );

		// Only store mappings of urls when it's a GET request
		$map_the_url = false;
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) {
			$map_the_url = true;
		}

		// We don't want POSTs during mutations or nothing on the url. cause it'll purge /graphql*
		if ( $map_the_url && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			//phpcs:ignore
			$url_to_save = wp_unslash( $_SERVER['REQUEST_URI'] );

			// Save the url this query request came in on, so we can purge it later when something changes
			$url_key = $this->url_key( $request_key );
			$urls    = $this->get( $url_key );
			$urls[]  = $url_to_save;
			$urls    = array_unique( $urls );
			//phpcs:ignore
			error_log( "Graphql Save Urls: $request_key " . print_r( $urls, 1 ) );
			$this->save( $url_key, $urls );
		}

		// Associate the node type 'post' with this query for look up later
		//$node_key = $this->node_key( 'post' );

		// Save the node ids for this query.  When one of these change in the future, we can purge the query
		foreach ( $this->runtime_nodes as $id ) {
			$key    = $this->node_key( $id );
			$data   = $this->get( $key );
			$data[] = $request_key;
			$data   = array_unique( $data );
			$this->save( $key, $data );
		}

		if ( is_array( $this->runtime_nodes ) ) {
			//phpcs:ignore
			error_log( 'Graphql Save Nodes: ' . print_r( $this->runtime_nodes, 1 ) );
		}
	}

	/**
	 * Fires once a post has been saved.
	 * Purge our saved/cached results data.
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function on_post_insert( $post_id, $post ) {
		if ( 'post' !== $post->post_type ) {
			return;
		}

		// When any post changes, look up graphql queries previously queried containing post resources and purge those
		// Look up the specific post/node/resource to purge vs $this->purge_all();
		$id    = Relay::toGlobalId( 'post', $post_id );
		$key   = $this->node_key( $id );
		$nodes = $this->get( $key );

		// Delete the cached results associated with this post/key
		if ( is_array( $nodes ) ) {
			foreach ( $nodes as $request_key ) {
				$this->delete( $request_key );
			}
		}
	}
}
