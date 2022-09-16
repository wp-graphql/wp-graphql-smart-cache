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
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeInfo;
use WPGraphQL\AppContext;
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
		add_filter( 'pre_graphql_execute_request', [ $this, 'before_executing_query_cb' ], 10, 2 );
		add_filter( 'graphql_dataloader_get_model', [ $this, 'data_loaded_process_cb' ], 10, 1 );

		// before execution begins, determine the type names map
		add_action( 'graphql_before_execute', [ $this, 'determine_query_types' ], 10, 1 );

		parent::init();
	}

	/**
	 * Given a query string, determine the GraphQL Types represented by the queried
	 * fields.
	 *
	 * @param Request $request
	 *
	 * @return void
	 * @throws SyntaxError
	 * @throws Exception
	 */
	public function determine_query_types( Request $request ) {

		// if the request has a queryId, use it to determine the query document
		if ( ! empty( $request->params->queryId ) ) {
			$document = new Document();
			$query    = $document->get( $request->params->queryId );
			// if no queryId was presented in the request, but a query was, use it
		} elseif ( ! empty( $request->params->query ) ) {
			$query = $request->params->query;
		}

		// if there's a query (either saved or part of the request params)
		// get the GraphQL Types being asked for by the query
		if ( ! empty( $query ) ) {
			$this->list_types  = $request->get_query_analyzer()->get_list_types() ?: [];
			$this->type_names  = $request->get_query_analyzer()->get_query_types() ?: [];
			$this->model_names = $request->get_query_analyzer()->get_query_models() ?: [];

			// @todo: should this info be output as an extension?
			// output the types as graphql debug info
			graphql_debug(
				'query_types_and_models',
				[
					'types'     => $this->type_names,
					'models'    => $this->model_names,
					'listTypes' => $this->list_types,
				]
			);
		}
	}

	public function before_executing_query_cb( $result, $request ) {
		// Consider this the start of query execution. Clear if we had a list of saved nodes
		$this->runtime_nodes = [];

		return $result;
	}

	/**
	 * Given the Schema and a query string, return a list of GraphQL Types that are being asked for
	 * by the query.
	 *
	 * @param Schema $schema The WPGraphQL Schema
	 * @param string $query  The query string
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_query_types( $schema, $query ) {
		if ( empty( $query ) || null === $schema ) {
			return [];
		}
		try {
			$ast = Parser::parse( $query );
		} catch ( SyntaxError $error ) {
			return [];
		}
		$type_map  = [];
		$type_info = new TypeInfo( $schema );
		$visitor   = [
			'enter' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info, &$type_map, $schema ) {
				$type_info->enter( $node );
				$type = $type_info->getType();
				if ( ! $type ) {
					return;
				}

				$named_type = Type::getNamedType( $type );

				if ( $named_type instanceof InterfaceType ) {
					$possible_types = $schema->getPossibleTypes( $named_type );
					foreach ( $possible_types as $possible_type ) {
						$type_map[] = strtolower( $possible_type );
					}
				} elseif ( $named_type instanceof ObjectType ) {
					$type_map[] = strtolower( $named_type );
				}
			},
			'leave' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info ) {
				$type_info->leave( $node );
			},
		];

		Visitor::visit( $ast, Visitor::visitWithTypeInfo( $type_info, $visitor ) );
		$map = array_values( array_unique( array_filter( $type_map ) ) );

		// @phpcs:ignore
		return apply_filters( 'graphql_cache_collection_get_query_types', $map, $schema, $query, $type_info );
	}

	/**
	 * Given the Schema and a query string, return a list of GraphQL model names that are being asked for
	 * by the query.
	 *
	 * @param Schema $schema The WPGraphQL Schema
	 * @param string $query  The query string
	 *
	 * @return array
	 * @throws SyntaxError|Exception
	 */
	public function get_query_models( $schema, $query ) {
		if ( empty( $query ) || null === $schema ) {
			return [];
		}
		try {
			$ast = Parser::parse( $query );
		} catch ( SyntaxError $error ) {
			return [];
		}
		$type_map  = [];
		$type_info = new TypeInfo( $schema );
		$visitor   = [
			'enter' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info, &$type_map, $schema ) {
				$type_info->enter( $node );
				$type = $type_info->getType();
				if ( ! $type ) {
					return;
				}

				$named_type = Type::getNamedType( $type );

				if ( $named_type instanceof InterfaceType ) {
					$possible_types = $schema->getPossibleTypes( $named_type );
					foreach ( $possible_types as $possible_type ) {
						if ( ! isset( $possible_type->config['model'] ) ) {
							continue;
						}
						$type_map[] = $possible_type->config['model'];
					}
				} elseif ( $named_type instanceof ObjectType ) {
					if ( ! isset( $named_type->config['model'] ) ) {
						return;
					}
					$type_map[] = $named_type->config['model'];
				}
			},
			'leave' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info ) {
				$type_info->leave( $node );
			},
		];

		Visitor::visit( $ast, Visitor::visitWithTypeInfo( $type_info, $visitor ) );
		$map = array_values( array_unique( array_filter( $type_map ) ) );

		// @phpcs:ignore
		return apply_filters( 'graphql_cache_collection_get_query_models', $map, $schema, $query, $type_info );
	}

	/**
	 * Given the Schema and a query string, return a list of GraphQL Types that are being asked for
	 * by the query.
	 *
	 * @param Schema $schema The WPGraphQL Schema
	 * @param string $query  The query string
	 *
	 * @return array
	 * @throws SyntaxError|Exception
	 */
	public function get_query_list_types( $schema, $query ) {
		if ( empty( $query ) || null === $schema ) {
			return [];
		}
		try {
			$ast = Parser::parse( $query );
		} catch ( SyntaxError $error ) {
			return [];
		}
		$type_map  = [];
		$type_info = new TypeInfo( $schema );
		$visitor   = [
			'enter' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info, &$type_map, $schema ) {
				$type_info->enter( $node );
				$type = $type_info->getType();
				if ( ! $type ) {
					return;
				}

				$named_type = Type::getNamedType( $type );

				// determine if the field is returning a list of types
				// or singular types
				// @todo: this might still be too fragile. We might need to adjust for cases where we can have list_of( nonNull( type ) ), etc
				$is_list_type = $named_type && ( Type::listOf( $named_type )->name === $type->name );

				if ( $named_type instanceof InterfaceType ) {
					$possible_types = $schema->getPossibleTypes( $named_type );
					foreach ( $possible_types as $possible_type ) {
						// if the type is a list, store it
						if ( $is_list_type && 0 !== strpos( $possible_type, '__' ) ) {
							$type_map[] = 'list:' . strtolower( $possible_type );
						}
					}
				} elseif ( $named_type instanceof ObjectType ) {
					// if the type is a list, store it
					if ( $is_list_type && 0 !== strpos( $named_type, '__' ) ) {
						$type_map[] = 'list:' . strtolower( $named_type );
					}
				}
			},
			'leave' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info ) {
				$type_info->leave( $node );
			},
		];

		Visitor::visit( $ast, Visitor::visitWithTypeInfo( $type_info, $visitor ) );
		$map = array_values( array_unique( array_filter( $type_map ) ) );

		// @phpcs:ignore
		return apply_filters( 'graphql_cache_collection_get_list_types', $map, $schema, $query, $type_info );
	}


	/**
	 * Filter the model before returning.
	 *
	 * @param mixed $model The Model to be returned by the loader
	 *
	 * @return mixed
	 */
	public function data_loaded_process_cb( $model ) {
		if ( isset( $model->id ) && in_array( get_class( $model ), $this->model_names, true ) ) {
			// Is this model type part of the requested/returned data in the asked for query?
			$this->runtime_nodes[] = get_class( $model ) . ':' . $model->id;
		}

		return $model;
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

		do_action( 'wpgraphql_cache_save_request', $request_key, $query_id, $query, $variables, $operation, $this->runtime_nodes, $this->list_types );

		// Save/add the node ids for this query.  When one of these change in the future, we can purge the query
		foreach ( $this->runtime_nodes as $node_id ) {
			$this->store_content( $this->node_key( $node_id ), $request_key );
		}

		// For each connection resolver, store the list types associated with this graphql query request
		if ( ! empty( $this->list_types ) && is_array( $this->list_types ) ) {
			$this->list_types = array_unique( $this->list_types );
			foreach ( $this->list_types as $type_name ) {
				$this->store_content( $type_name, $request_key );
			}
		}
	}
}
