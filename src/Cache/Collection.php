<?php
/**
 * When processing a GraphQL query, collect nodes based on the query and url they are part of.
 * When content changes for nodes, invalidate and trigger actions that allow caches to be
 * invalidated for nodes, queries, urls.
 */

namespace WPGraphQL\Labs\Cache;

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
use GraphQLRelay\Relay;
use WP_Post;
use WP_User;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Loader\AbstractDataLoader;
use WPGraphQL\Labs\Document;
use WPGraphQL\Request;

class Collection extends Query {

	// Nodes that are part of the current/in-progress/excuting query
	public $nodes = [];

	// whether the query is a query (not a mutation or subscription)
	public $is_query;

	// Types that are referenced in the query
	public $type_names = [];

	public function init() {
		add_action( 'graphql_return_response', [ $this, 'save_query_mapping_cb' ], 10, 7 );
		add_filter( 'pre_graphql_execute_request', [ $this, 'before_executing_query_cb' ], 10, 2 );
		add_filter( 'graphql_dataloader_get_model', [ $this, 'data_loaded_process_cb' ], 10, 1 );

		add_action( 'graphql_after_resolve_field', [ $this, 'during_query_resolve_field' ], 10, 6 );

		// listen for posts to transition statuses so we know when to purge
		add_action( 'transition_post_status', [ $this, 'on_transition_post_status_cb' ], 10, 3 );

		// user/author
		add_filter( 'insert_user_meta', [ $this, 'on_user_change_cb' ], 10, 3 );

		// meta For acf, which calls WP function update_metadata
		add_action( 'updated_post_meta', [ $this, 'on_postmeta_change_cb' ], 10, 4 );

		// before execution begins, determine the type names map
		add_action( 'graphql_before_execute', [ $this, 'determine_query_types' ], 10, 1 );

		parent::init();
	}

	public function before_executing_query_cb( $result, $request ) {
		// Consider this the start of query execution. Clear if we had a list of saved nodes
		$this->runtime_nodes    = [];
		$this->connection_names = [];

		return $result;
	}

	/**
	 * Given a query string, determine the GraphQL Types represented by the queried
	 * fields.
	 *
	 * @param Request $request
	 *
	 * @return void
	 * @throws SyntaxError
	 */
	public function determine_query_types( Request $request ) {
		// if the request has a query, use it
		if ( ! empty( $request->params->query ) ) {
			$query = $request->params->query;
			// else, use the requests queryId
		} elseif ( ! empty( $request->params->queryId ) ) {
			$document = new Document();
			$query    = $document->get( $request->params->queryId );
		}

		// if there's a query (saved or part of the params) get the query types
		// from the query
		if ( ! empty( $query ) ) {
			$this->type_names = $this->get_query_types( $request->schema, $query );

			// @todo: should this info be output as an extension?
			// output the types as graphql debug info
			graphql_debug( 'query_types', [ 'types' => $this->type_names ] );
		}
	}

	/**
	 * Filter the model before returning.
	 *
	 * @param mixed              $model The Model to be returned by the loader
	 * @param mixed              $entry The entry loaded by dataloader that was used to create the
	 *                                  Model
	 * @param mixed              $key   The Key that was used to load the entry
	 * @param AbstractDataLoader $this  The AbstractDataLoader Instance
	 */
	public function data_loaded_process_cb( $model ) {
		if ( isset( $model->id ) ) {
			$this->runtime_nodes[] = $model->id;
		}

		return $model;
	}

	/**
	 * An action after the field resolves
	 *
	 * @param mixed       $source    The source passed down the Resolve Tree
	 * @param array       $args      The args for the field
	 * @param AppContext  $context   The AppContext passed down the ResolveTree
	 * @param ResolveInfo $info      The ResolveInfo passed down the ResolveTree
	 * @param string      $type_name The name of the type the fields belong to
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
	 * @param mixed|string|int $id The content node identifier
	 * @return array The unique list of content stored
	 */
	public function retrieve_nodes( $id ) {
		$key = $this->nodes_key( $id );

		return $this->get( $key );
	}

	/**
	 * @param mixed|string|int $id The content node identifier
	 *
	 * @return array The unique list of content stored
	 */
	public function retrieve_urls( $id ) {
		$key = $this->urls_key( $id );

		return $this->get( $key );
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

				// determine if the field is returning a list of types
				// or singular types
				// @todo: this might still be too fragile. We might need to adjust for cases where we can have list_of( nonNull( type ) ), etc
				$prefix = $named_type && ( Type::listOf( $named_type )->name === $type->name ) ? 'list:' : null;

				if ( $named_type instanceof InterfaceType ) {
					$possible_types = $schema->getPossibleTypes( $named_type );
					foreach ( $possible_types as $possible_type ) {
						$type_map[] = $prefix . strtolower( $possible_type );
					}
				} elseif ( $named_type instanceof ObjectType ) {
					$type_map[] = $prefix . strtolower( $named_type );
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
	 * @param Request The WPGraphQL Request object
	 *
	 * @return void
	 * @throws SyntaxError
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

			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			error_log( "Graphql Save Urls: $request_key " . print_r( $urls, 1 ) );
		}

		// Save/add the node ids for this query.  When one of these change in the future, we can purge the query
		foreach ( $this->runtime_nodes as $node_id ) {
			$this->store_content( $this->nodes_key( $node_id ), $request_key );
		}

		// For each connection resolver, store the url key
		if ( ! empty( $this->type_names ) && is_array( $this->type_names ) ) {
			$this->type_names = array_unique( $this->type_names );
			foreach ( $this->type_names as $type_name ) {
				$this->store_content( $type_name, $request_key );
			}
		}

		if ( is_array( $this->runtime_nodes ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			error_log( "Graphql Save Nodes: $request_key " . print_r( $this->runtime_nodes, 1 ) );
		}
	}

	/**
	 * Fires once a post has been saved.
	 * Purge our saved/cached results data.
	 *
	 * @param string  $new_status The new status of the post
	 * @param string  $old_status The old status of the post
	 * @param WP_Post $post       The post being updated
	 */
	public function on_transition_post_status_cb( $new_status, $old_status, WP_Post $post ) {

		// bail if it's an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// If the post type is not intentionally tracked, ignore it
		if ( ! in_array( $post->post_type, \WPGraphQL::get_allowed_post_types(), true ) ) {
			return;
		}

		$initial_post_statuses = [ 'auto-draft', 'inherit', 'new' ];

		// If the post is a fresh post that hasn't been made public, don't track the action
		if ( in_array( $new_status, $initial_post_statuses, true ) ) {
			return;
		}

		// Updating a draft should not log actions
		if ( 'draft' === $new_status && 'draft' === $old_status ) {
			return;
		}

		// If the post isn't coming from a "publish" state or going to a "publish" state
		// we can ignore the action.
		if ( 'publish' !== $old_status && 'publish' !== $new_status ) {
			return;
		}

		// Default action type is update when the transition_post_status hook is run
		$action_type = 'UPDATE';

		// If a post is moved from 'publish' to any other status, set the action_type to delete
		if ( 'publish' !== $new_status && 'publish' === $old_status ) {
			$action_type = 'DELETE';

			// If a post that was not published becomes published, set the action_type to create
		} elseif ( 'publish' === $new_status && 'publish' !== $old_status ) {
			$action_type = 'CREATE';
		}

		$relay_id         = Relay::toGlobalId( 'post', $post->ID );
		$post_type_object = get_post_type_object( $post->post_type );
		$type_name        = strtolower( $post_type_object->graphql_single_name );

		// if we create a post
		// we need to purge lists of the type
		// as the created node might affect the list
		if ( 'CREATE' === $action_type ) {
			$nodes = $this->get( 'list:' . $type_name );
			if ( is_array( $nodes ) ) {
				do_action( 'wpgraphql_cache_purge_nodes', 'list:' . $type_name, $type_name, $nodes );
			}
		}

		// if we update or delete a post
		// we need to purge any queries that have that
		// specific node in it
		if ( 'UPDATE' === $action_type || 'DELETE' === $action_type ) {
			$nodes = $this->retrieve_nodes( $relay_id );
			// Delete the cached results associated with this post/key
			if ( is_array( $nodes ) && ! empty( $nodes ) ) {
				do_action( 'wpgraphql_cache_purge_nodes', $type_name, $this->nodes_key( $relay_id ), $nodes );
			}
		}
	}

	/**
	 *
	 * @param array   $meta
	 * @param WP_User $user   User object.
	 * @param bool    $update Whether the user is being updated rather than created.
	 */
	public function on_user_change_cb( $meta, $user, $update ) {
		if ( false === $update ) {
			// if created, clear any cached connection lists for this type
			do_action( 'wpgraphql_cache_purge_nodes', 'user', 'users', [] );
		} else {
			$id    = Relay::toGlobalId( 'user', (string) $user->ID );
			$nodes = $this->retrieve_nodes( $id );

			// Delete the cached results associated with this key
			if ( is_array( $nodes ) ) {
				do_action( 'wpgraphql_cache_purge_nodes', 'user', $this->nodes_key( $id ), $nodes );
			}
		}

		return $meta;
	}

	/**
	 * @param int    $meta_id    ID of updated metadata entry.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value. This will be a PHP-serialized string
	 *                           representation of the value if the value is an array, an object,
	 *                           or itself a PHP-serialized string.
	 */
	public function on_postmeta_change_cb( $meta_id, $post_id, $meta_key, $meta_value ) {

		// get the post object being modified
		$object = get_post( $post_id );

		/**
		 * This filter allows plugins to opt-in or out of tracking for meta.
		 *
		 * @param bool   $should_track Whether the meta key should be tracked.
		 * @param string $meta_key     Metadata key.
		 * @param int    $meta_id      ID of updated metadata entry.
		 * @param mixed  $meta_value   Metadata value. Serialized if non-scalar.
		 * @param mixed  $object       The object the meta is being updated for.
		 *
		 * @param bool   $tracked      whether the meta key is tracked for purging caches
		 */
		//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$should_track = apply_filters( 'graphql_cache_should_track_meta_key', null, $meta_key, $meta_value, $object );

		// If the filter has been applied
		if ( null !== $should_track && false === $should_track ) {
			return;
		}

		// if the meta key starts with an underscore, treat
		// it as private meta and don't purge the cache
		if ( strpos( $meta_key, '_' ) === 0 ) {
			return;
		}

		// clear any cached connection lists for this type
		$post_type_object = get_post_type_object( $object->post_type );

		if ( ! in_array( $post_type_object->name, \WPGraphQL::get_allowed_post_types(), true ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $object->post_type );
		$type_name        = strtolower( $post_type_object->graphql_single_name );
		$relay_id         = Relay::toGlobalId( 'post', $object->ID );
		$nodes            = $this->retrieve_nodes( $relay_id );

		// Delete the cached results associated with this post/key
		if ( is_array( $nodes ) && ! empty( $nodes ) ) {
			do_action( 'wpgraphql_cache_purge_nodes', $type_name, $this->nodes_key( $relay_id ), $nodes );
		}
	}
}
