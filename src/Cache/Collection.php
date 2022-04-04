<?php
/**
 * When processing a GraphQL query, collect nodes based on the query and url they are part of.
 * When content changes for nodes, invalidate and trigger actions that allow caches to be invalidated for nodes, queries, urls.
 */

namespace WPGraphQL\Labs\Cache;

use WPGraphQL\Labs\Admin\Settings;
use GraphQLRelay\Relay;

class Collection extends Query {

	// Nodes that are part of the current/in-progress/excuting query
	public $nodes = [];

	public $is_query;

	public function init() {
		add_action( 'graphql_return_response', [ $this, 'save_query_mapping_cb' ], 10, 7 );
		add_filter( 'pre_graphql_execute_request', [ $this, 'before_executing_query_cb' ], 10, 2 );
		add_filter( 'graphql_dataloader_get_model', [ $this, 'data_loaded_process_cb' ], 10, 1 );

		add_action( 'graphql_after_resolve_field', [ $this, 'during_query_resolve_field' ], 10, 6 );

		add_action( 'wp_insert_post', [ $this, 'on_post_insert' ], 10, 2 );
		add_filter( 'insert_user_meta', [ $this, 'on_user_insert' ], 10, 2 );

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
	public function data_loaded_process_cb( $model ) {
		if ( $model->id ) {
			$this->runtime_nodes[] = $model->id;
		}
		return $model;
	}

	/**
	 * An action after the field resolves
	 *
	 * @param mixed           $source    The source passed down the Resolve Tree
	 * @param array           $args      The args for the field
	 * @param AppContext      $context   The AppContext passed down the ResolveTree
	 * @param ResolveInfo     $info      The ResolveInfo passed down the ResolveTree
	 * @param string          $type_name The name of the type the fields belong to
	 */
	public function during_query_resolve_field( $source, $args, $context, $info, $field_resolver, $type_name ) {
		// If at any point while processing fields and it shows this request is a query, track that.
		if ( 'RootQuery' === $type_name ) {
			$this->is_query = true;
		}
	}

	/**
	 * Unique identifier for this request for use in the collection map
	 *
	 * @param string $request_key Id for the node
	 *
	 * @return string unique id for this request
	 */
	public function nodes_key( $request_key ) {
		return 'node:' . $request_key;
	}

	/**
	 * Unique identifier for this request for use in the collection map
	 *
	 * @param string $request_key Id for the node
	 *
	 * @return string unique id for this request
	 */
	public function urls_key( $request_key ) {
		return 'url:' . $request_key;
	}

	/**
	 * @param string $key The identifier to the list
	 * @param string $content to add
	 * @return array The unique list of content stored
	 */
	public function store_content( $key, $content ) {
		$data   = $this->get( $key );
		$data[] = $content;
		$data   = array_unique( $data );
		$this->save( $key, $data );
		return $data;
	}

	/**
	 * @param $id The content node identifier
	 * @return array The unique list of content stored
	 */
	public function retrieve_nodes( $id ) {
		$key = $this->nodes_key( $id );
		return $this->get( $key );
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
			$urls = $this->store_content( $this->urls_key( $request_key ), $url_to_save );

			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			graphql_debug( "Graphql Save Urls: $request_key " . print_r( $urls, 1 ) );
		}

		// Save/add the node ids for this query.  When one of these change in the future, we can purge the query
		foreach ( $this->runtime_nodes as $node_id ) {
			$this->store_content( $this->nodes_key( $node_id ), $request_key );
		}

		if ( is_array( $this->runtime_nodes ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			graphql_debug( 'Graphql Save Nodes: ' . print_r( $this->runtime_nodes, 1 ) );
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
		$nodes = $this->retrieve_nodes( $id );

		// Delete the cached results associated with this post/key
		if ( is_array( $nodes ) ) {
			do_action( 'wpgraphql_cache_purge_nodes', 'post', $this->nodes_key( $id ), $nodes );
		}
	}

	/**
	 *
	 * @param array $meta
	 * @param WP_User $user   User object.
	 */
	public function on_user_insert( $meta, $user ) {
		$id    = Relay::toGlobalId( 'user', (string) $user->ID );
		$nodes = $this->retrieve_nodes( $id );

		// Delete the cached results associated with this key
		if ( is_array( $nodes ) ) {
			do_action( 'wpgraphql_cache_purge_nodes', 'user', $this->nodes_key( $id ), $nodes );
		}
		return $meta;
	}
}
