<?php
/**
 * When processing a GraphQL query, collect nodes based on the query and url they are part of.
 * When content changes for nodes, invalidate and trigger actions that allow caches to be
 * invalidated for nodes, queries, urls.
 */

namespace WPGraphQL\SmartCache\Cache;

use Exception;
use GraphQL\Error\SyntaxError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Language\Parser;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeInfo;
use WPGraphQL\SmartCache\Document;
use WPGraphQL\Request;

class Collection extends Query {

	/**
	 * Nodes that are part of the current/in-progress/executing query
	 *
	 * @var array
	 */
	public $nodes = [];

	/**
	 * Types that are referenced in the query
	 *
	 * @var array
	 */
	public $type_names = [];

	/**
	 * Models that are referenced in the query
	 *
	 * @var array
	 */
	public $model_names = [];

	/**
	 * Types in the query that are lists
	 *
	 * @var array
	 */
	public $list_types = [];

	/**
	 * @var array
	 */
	protected $runtime_nodes = [];

	// initialize the cache collection
	public function init() {
		add_action( 'graphql_return_response', [ $this, 'save_query_mapping_cb' ], 10, 8 );

		// back compat to support the test suite
		add_filter( 'graphql_query_analyzer_runtime_node', function( $id, $model ) {
			return get_class( $model ) . ':' . $id;
		}, 10, 2 );

		parent::init();
	}

	/**
	 * Create the unique identifier for this content/node/list id for use in the collection map
	 *
	 * @param string $id Id for the node
	 *
	 * @return string unique id for this request
	 */
	public function node_key( $id ) {
		return 'node:' . $id;
	}

	/**
	 * @param string $key     The identifier to the list
	 * @param string $content to add
	 *
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
	 * Get the list of nodes/content/lists associated with the id
	 *
	 * @param mixed|string|int $id The content node identifier
	 *
	 * @return array The unique list of content stored
	 */
	public function retrieve_nodes( $id ) {
		$key = $this->node_key( $id );
		return $this->get( $key );
	}

	/**
	 * When a query response is being returned to the client, build map for each item and this
	 * query/queryId That way we will know what to invalidate on data change.
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
	public function save_query_mapping_cb(
		$filtered_response,
		$response,
		$schema,
		$operation,
		$query,
		$variables,
		$request,
		$query_id
	) {

		$request_key = $this->build_key( $query_id, $query, $variables, $operation );

		// get the runtime nodes from the query analyzer
		$runtime_nodes = $request->get_query_analyzer()->get_runtime_nodes() ?: [];
		$list_types = $request->get_query_analyzer()->get_list_types() ?: [];

		do_action( 'wpgraphql_cache_save_request', $request_key, $query_id, $query, $variables, $operation, $runtime_nodes, $this->list_types );

		// Save/add the node ids for this query.  When one of these change in the future, we can purge the query
		foreach ( $runtime_nodes as $node_id ) {
			$this->store_content( $this->node_key( $node_id ), $request_key );
		}

		// For each connection resolver, store the list types associated with this graphql query request
		if ( ! empty( $list_types ) && is_array($list_types ) ) {
			$list_types = array_unique( $list_types );
			foreach ( $list_types as $type_name ) {
				$this->store_content( $type_name, $request_key );
			}
		}
	}
}
