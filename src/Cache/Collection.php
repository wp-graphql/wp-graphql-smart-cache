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
use WPGraphQL\Labs\Document;
use WPGraphQL\Request;

class Collection extends Query {

	/**
	 * Nodes that are part of the current/in-progress/excuting query
	 *
	 * @var array
	 */
	public $nodes = [];

	/**
	 * Whether the query is a query (not a mutation or subscription)
	 *
	 * @var boolean
	 */
	public $is_query;

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
		add_action( 'graphql_return_response', [ $this, 'save_query_mapping_cb' ], 10, 7 );
		add_filter( 'pre_graphql_execute_request', [ $this, 'before_executing_query_cb' ], 10, 2 );
		add_filter( 'graphql_dataloader_get_model', [ $this, 'data_loaded_process_cb' ], 10, 1 );

		add_action( 'graphql_after_resolve_field', [ $this, 'during_query_resolve_field' ], 10, 6 );

		// listen for posts to transition statuses, so we know when to purge
		add_action( 'transition_post_status', [ $this, 'on_transition_post_status_cb' ], 10, 3 );

		// listen for changes to the post author.
		// This will need to evict list queries.
		add_action( 'post_updated', [ $this, 'on_post_updated_cb' ], 10, 3 );

		// listen for posts to be deleted. Queries with deleted nodes should be purged.
		add_action( 'deleted_post', [ $this, 'on_deleted_post_cb' ], 10, 2 );

		// when a term is edited, purge caches for that term
		// this action is called when term caches are updated on a delay.
		// for example, if a scheduled post is assigned to a term,
		// this won't be called when the post is initially inserted with the
		// term assigned, but when the post is published
		add_action( 'edited_term_taxonomy', [ $this, 'on_edited_term_taxonomy_cb' ], 10, 2 );

		// user/author
		add_filter( 'insert_user_meta', [ $this, 'on_user_change_cb' ], 10, 3 );
		add_action( 'deleted_user', [ $this, 'on_user_deleted_cb' ], 10, 2 );

		// listen to updates to post meta
		add_action( 'updated_post_meta', [ $this, 'on_postmeta_change_cb' ], 10, 4 );

		// listen for when meta is inserted the first time
		// the updated_post_meta hook only runs when meta is being updated,
		// not when its being inserted (added) the first time
		add_action( 'added_post_meta', [ $this, 'on_postmeta_change_cb' ], 10, 4 );

		// listen for when meta is deleted
		add_action( 'deleted_post_meta', [ $this, 'on_postmeta_deleted_cb' ], 10, 4 );

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
			$this->list_types = $this->get_query_list_types( $request->schema, $query );
			$this->type_names  = $this->get_query_types( $request->schema, $query );
			$this->model_names = $this->get_query_models( $request->schema, $query );

			// @todo: should this info be output as an extension?
			// output the types as graphql debug info
			graphql_debug(
				'query_types_and_models',
				[
					'types'  => $this->type_names,
					'models' => $this->model_names,
					'listTypes' => $this->list_types
				]
			);
		}
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
	 *
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
	 * Determines whether the meta should be tracked or not.
	 *
	 * By default, meta keys that start with an underscore are treated as
	 * private and are not tracked for cache evictions. They can be filtered to
	 * be allowed.
	 *
	 * @param string $meta_key Metadata key.
	 * @param mixed $meta_value Metadata value. Serialized if non-scalar.
	 * @param object $object The object the metadata is for.
	 *
	 * @return bool
	 */
	public function should_track_meta( $meta_key, $meta_value, $object ) {

		/**
		 * This filter allows plugins to opt-in or out of tracking for meta.
		 *
		 * @param bool $should_track Whether the meta key should be tracked.
		 * @param string $meta_key Metadata key.
		 * @param int $meta_id ID of updated metadata entry.
		 * @param mixed $meta_value Metadata value. Serialized if non-scalar.
		 * @param mixed $object The object the meta is being updated for.
		 *
		 * @param bool $tracked whether the meta key is tracked for purging caches
		 */
		//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$should_track = apply_filters( 'graphql_cache_should_track_meta_key', null, $meta_key, $meta_value, $object );

		// If the filter has been applied return it
		if ( null !== $should_track ) {
			return (bool) $should_track;
		}

		// If the meta key starts with an underscore, don't track it
		if ( strpos( $meta_key, '_' ) === 0 ) {
			return false;
		}

		return true;
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
						$type_map[] =  $possible_type->config['model'];
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
						if ( $is_list_type ) {
							$type_map[] = 'list:' . strtolower( $possible_type );
						}
					}
				} elseif ( $named_type instanceof ObjectType ) {
					// if the type is a list, store it
					if ( $is_list_type ) {
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
	 * Listen for updates to a post so we can purge caches relevant to the change
	 *
	 * @param int     $post_id The ID of the post being updated
	 * @param WP_Post $post_after The Post Object after the update
	 * @param WP_Post $post_before The Post Object before the update
	 *
	 * @return void
	 */
	public function on_post_updated_cb( $post_id, WP_Post $post_after, WP_Post $post_before ) {
		return;

		// if the post author hasn't changed, do nothing
		if ( $post_after->post_author === $post_before->post_author ) {
			return;
		}

		$post_type_object = get_post_type_object( $post_after->post_type );
		$type_name        = strtolower( $post_type_object->graphql_single_name );

		$relay_id         = Relay::toGlobalId( 'post', $post_id );
		$nodes            = $this->retrieve_nodes( $relay_id );

		// Delete the cached results associated with this post/key
		if ( is_array( $nodes ) && ! empty( $nodes ) ) {
			do_action( 'wpgraphql_cache_purge_nodes', $type_name, $this->nodes_key( $relay_id ), $nodes );
		}
	}

	/**
	 * Listen for posts being deleted and purge relevant caches
	 *
	 * @param int     $post_id The ID of the post being deleted
	 * @param WP_Post $post The Post object that is being deleted
	 *
	 * @return void
	 */
	public function on_deleted_post_cb( $post_id, WP_Post $post ) {
		if ( ! in_array( $post->post_type, \WPGraphQL::get_allowed_post_types(), true ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		$relay_id         = Relay::toGlobalId( 'post', $post->ID );
		$type_name        = strtolower( $post_type_object->graphql_single_name );
		$nodes            = $this->retrieve_nodes( $relay_id );

		// Delete the cached results associated with this post/key
		if ( is_array( $nodes ) && ! empty( $nodes ) ) {
			do_action( 'wpgraphql_cache_purge_nodes', $type_name, $this->nodes_key( $relay_id ), $nodes );
		}
	}

	/**
	 * Listen for changes to the Term Taxonomy. This is called after posts that have
	 * a taxonomy associated with them are published. We don't always want to purge
	 * caches related to terms when they're associated with a post, but rather when the association
	 * becomes public. For example, a term being associated with a draft post shouldn't purge
	 * cache, but the publishing of the draft post that has a term associated with it
	 * should purge the terms cache.
	 *
	 * @param int         $tt_id The Term Taxonomy ID of the term
	 * @param string $taxonomy The name of the taxonomy the term belongs to
	 *
	 * @return void
	 */
	public function on_edited_term_taxonomy_cb( $tt_id, $taxonomy ) {
		if ( ! in_array( $taxonomy, \WPGraphQL::get_allowed_taxonomies(), true ) ) {
			return;
		}

		$term = get_term_by( 'term_taxonomy_id', $tt_id, $taxonomy );

		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		$relay_id  = Relay::toGlobalId( 'term', $term->term_id );
		$type_name = strtolower( get_taxonomy( $taxonomy )->graphql_single_name );
		$nodes     = $this->retrieve_nodes( $relay_id );

		// Delete the cached results associated with this post/key
		if ( is_array( $nodes ) && ! empty( $nodes ) ) {
			do_action( 'wpgraphql_cache_purge_nodes', $type_name, $this->nodes_key( $relay_id ), $nodes );
		}
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
		if ( ! empty( $this->list_types ) && is_array( $this->list_types ) ) {
			$this->list_types = array_unique( $this->list_types );
			foreach ( $this->list_types as $type_name ) {
				$this->store_content( $type_name, $request_key );
			}
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
	 * Listens for changes to the user object.
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
	 *
	 * @param int      $deleted_id       ID of the deleted user.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 */
	public function on_user_deleted_cb( $deleted_id, $reassign_id ) {
		$id    = Relay::toGlobalId( 'user', (string) $deleted_id );
		$nodes = $this->retrieve_nodes( $id );

		// Delete the cached results associated with this key
		if ( is_array( $nodes ) ) {
			do_action( 'wpgraphql_cache_purge_nodes', 'user', $this->nodes_key( $id ), $nodes );
		}

		if ( $reassign_id ) {
			$id    = Relay::toGlobalId( 'user', (string) $reassign_id );
			$nodes = $this->retrieve_nodes( $id );

			// Delete the cached results associated with this key
			if ( is_array( $nodes ) ) {
				do_action( 'wpgraphql_cache_purge_nodes', 'user', $this->nodes_key( $id ), $nodes );
			}
		}
	}

	/**
	 * Listens for changes to postmeta
	 *
	 * @param int    $meta_id    ID of updated metadata entry.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value. This will be a PHP-serialized string
	 *                           representation of the value if the value is an array, an object,
	 *                           or itself a PHP-serialized string.
	 */
	public function on_postmeta_change_cb( $meta_id, $post_id, $meta_key, $meta_value ) {

		// get the post object being modified
		$post = get_post( $post_id );

		// if the post type is not tracked, ignore it
		if ( ! in_array( $post->post_type, \WPGraphQL::get_allowed_post_types(), true ) ) {
			return;
		}

		// if the post is not published, ignore it
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// if the meta key isn't tracked, ignore it
		//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		if ( false === $this->should_track_meta( $meta_key, $meta_value, $post ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		$type_name        = strtolower( $post_type_object->graphql_single_name );
		$relay_id         = Relay::toGlobalId( 'post', $post->ID );
		$nodes            = $this->retrieve_nodes( $relay_id );

		// Delete the cached results associated with this post/key
		if ( is_array( $nodes ) && ! empty( $nodes ) ) {
			do_action( 'wpgraphql_cache_purge_nodes', $type_name, $this->nodes_key( $relay_id ), $nodes );
		}
	}

	/**
	 * Purges caches when meta is deleted on a post
	 *
	 * @param string[] $meta_ids    An array of metadata entry IDs to delete.
	 * @param int      $object_id   ID of the object metadata is for.
	 * @param string   $meta_key    Metadata key.
	 * @param mixed    $meta_value Metadata value. Serialized if non-scalar.
	 */
	public function on_postmeta_deleted_cb( $meta_ids, $object_id, $meta_key, $meta_value ) {
		$post = get_post( $object_id );

		// if the post type is not tracked, ignore it
		if ( ! in_array( $post->post_type, \WPGraphQL::get_allowed_post_types(), true ) ) {
			return;
		}

		// if the post is not published, ignore it
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// if the meta key isn't tracked, ignore it
		//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		if ( false === $this->should_track_meta( $meta_key, $meta_value, $post ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		$type_name        = strtolower( $post_type_object->graphql_single_name );
		$relay_id         = Relay::toGlobalId( 'post', $post->ID );
		$nodes            = $this->retrieve_nodes( $relay_id );

		// Delete the cached results associated with this post/key
		if ( is_array( $nodes ) && ! empty( $nodes ) ) {
			do_action( 'wpgraphql_cache_purge_nodes', $type_name, $this->nodes_key( $relay_id ), $nodes );
		}
	}
}
