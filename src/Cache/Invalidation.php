<?php
namespace WPGraphQL\SmartCache\Cache;

use GraphQLRelay\Relay;
use WP_Comment;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * This class handles the invalidation of the WPGraphQL Caches
 */
class Invalidation {

	/**
	 * @var Collection
	 */
	public $collection = [];

	/**
	 * @var array | null
	 */
	protected static $ignored_meta_keys = null;

	/**
	 * Instantiate the Cache Invalidation class
	 *
	 * @param Collection $collection
	 */
	public function __construct( Collection $collection ) {
		$this->collection = $collection;
	}

	/**
	 * Initialize the actions to listen for
	 */
	public function init() {
		// @phpcs:ignore
		do_action( 'graphql_cache_invalidation_init', $this );

		## POST ACTIONS

		// listen for posts to transition statuses, so we know when to purge
		add_action( 'transition_post_status', [ $this, 'on_transition_post_status_cb' ], 10, 3 );

		// listen for changes to the post author.
		// This will need to evict list queries.
		add_action( 'post_updated', [ $this, 'on_post_updated_cb' ], 10, 3 );

		// listen for posts to be deleted. Queries with deleted nodes should be purged.
		add_action( 'deleted_post', [ $this, 'on_deleted_post_cb' ], 10, 2 );

		// listen to updates to post meta
		add_action( 'updated_post_meta', [ $this, 'on_postmeta_change_cb' ], 10, 4 );

		// listen for when meta is inserted the first time
		// the updated_post_meta hook only runs when meta is being updated,
		// not when its being inserted (added) the first time
		add_action( 'added_post_meta', [ $this, 'on_postmeta_change_cb' ], 10, 4 );

		// listen for when meta is deleted
		add_action( 'deleted_post_meta', [ $this, 'on_postmeta_change_cb' ], 10, 4 );

		## TERM ACTIONS

		add_action( 'created_term', [ $this, 'on_created_term_cb' ], 10, 3 );
		add_action( 'delete_term', [ $this, 'on_deleted_term_cb' ], 10, 4 );
		add_action( 'added_term_meta', [ $this, 'on_updated_term_meta_cb' ], 10, 4 );
		add_action( 'updated_term_meta', [ $this, 'on_updated_term_meta_cb' ], 10, 4 );
		add_action( 'deleted_term_meta', [ $this, 'on_updated_term_meta_cb' ], 10, 4 );

		// when a term is edited, purge caches for that term
		// this action is called when term caches are updated on a delay.
		// for example, if a scheduled post is assigned to a term,
		// this won't be called when the post is initially inserted with the
		// term assigned, but when the post is published
		add_action( 'edited_term_taxonomy', [ $this, 'on_edited_term_taxonomy_cb' ], 10, 2 );

		## USER ACTIONS

		// user/author
		add_action( 'updated_user_meta', [ $this, 'on_user_meta_change_cb' ], 10, 4 );
		add_action( 'added_user_meta', [ $this, 'on_user_meta_change_cb' ], 10, 4 );
		add_action( 'deleted_user_meta', [ $this, 'on_user_meta_change_cb' ], 10, 4 );
		add_action( 'profile_update', [ $this, 'on_user_profile_update_cb' ], 10, 2 );
		add_action( 'deleted_user', [ $this, 'on_user_deleted_cb' ], 10, 2 );

		## MENU ACTIONS

		add_filter( 'pre_set_theme_mod_nav_menu_locations', [ $this, 'on_set_nav_menu_locations_cb' ], 10, 2 );
		add_action( 'wp_update_nav_menu', [ $this, 'on_update_nav_menu_cb' ], 10, 1 );

		add_action( 'added_term_meta', [ $this, 'on_updated_menu_meta_cb' ], 10, 4 );
		add_action( 'updated_term_meta', [ $this, 'on_updated_menu_meta_cb' ], 10, 4 );
		add_action( 'deleted_term_meta', [ $this, 'on_updated_menu_meta_cb' ], 10, 4 );

		// @todo: evict caches when meta on menu items are changed. This happens outside *_post_meta hooks as nav_menu_item is a "different" type of post type

		add_action( 'updated_post_meta', [ $this, 'on_menu_item_change_cb' ], 10, 4 );
		add_action( 'added_post_meta', [ $this, 'on_menu_item_change_cb' ], 10, 4 );
		add_action( 'deleted_post_meta', [ $this, 'on_menu_item_change_cb' ], 10, 4 );

		## MEDIA ACTIONS

		add_action( 'add_attachment', [ $this, 'on_add_attachment_cb' ], 10, 1 );
		add_action( 'edit_attachment', [ $this, 'on_edit_attachment_cb' ], 10, 1 );
		add_action( 'delete_attachment', [ $this, 'on_delete_attachment' ], 10, 1 );
		add_action( 'wp_save_image_editor_file', [ $this, 'on_save_image_file_cb' ], 10, 5 );
		add_action( 'wp_save_image_file', [ $this, 'on_save_image_file_cb' ], 10, 5 );

		## Comment actions

		add_action( 'wp_insert_comment', [ $this, 'on_insert_comment_cb' ], 10, 2 );
		add_action( 'transition_comment_status', [ $this, 'on_comment_transition_cb' ], 10, 3 );
	}

	/**
	 * Return a list of ignored meta keys
	 *
	 * @return array|null
	 */
	public static function get_ignored_meta_keys() {
		if ( null !== self::$ignored_meta_keys ) {
			return self::$ignored_meta_keys;
		}

		// Default list of ignored meta keys
		$ignored_meta_keys = [
			// see: https://github.com/wp-graphql/wp-graphql-smart-cache/issues/206
			'apple_news_notice',
		];

		$ignored_meta_keys = apply_filters( 'graphql_cache_ignored_meta_keys', $ignored_meta_keys );

		// make sure the filter returns an array
		self::$ignored_meta_keys = is_array( $ignored_meta_keys ) ? $ignored_meta_keys : [];

		// return the ignored meta keys array
		return self::$ignored_meta_keys;
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

		// If the meta key is ignored, don't track it for cache purging
		if ( in_array( $meta_key, self::get_ignored_meta_keys(), true ) ) {
			return false;
		}

		// If the meta key starts with an underscore, don't track it
		if ( 0 === strpos( $meta_key, '_' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * For the provided key, load the collected data elements from memory and trigger the purge action for said key,
	 * prodiving the list of nodes.
	 *
	 * The key represents either an individual url request, a list of nodes, list of types, etc.
	 *
	 * @param string $key An identifiers for data stored in memory.
	 */
	public function purge( $key ) {

		// This action is emitted with the key to purge.
		// Plugins can respond to this action to evict caches for that key
		// phpcs:ignore
		do_action( 'graphql_purge', $key );

		$nodes = $this->collection->get( $key );
		if ( is_array( $nodes ) && ! empty( $nodes ) ) {
			do_action( 'wpgraphql_cache_purge_nodes', $key, $nodes );
		}
	}

	/**
	 * The 'runtime nodes' for a graphql queries are collected and stored into memory when requested by a client.
	 * This function allows those runtime nodes to be loaded from memory and invalidated.
	 *
	 * See the Collection class runtime_nodes.
	 *
	 * @param string $id_prefix The type name specific to the id to form the "global ID" that is unique among all types
	 * @param mixed|string|int $id The node entity identifier
	 */
	public function purge_nodes( $id_prefix, $id ) {
		if ( ! method_exists( Relay::class, 'toGlobalId' ) ) {
			return;
		}

		$relay_id = Relay::toGlobalId( $id_prefix, $id );

		// purge the node
		$this->purge( $relay_id );

		// purge caches that had skipped keys of the type
		// because of header limitations, WPGraphQL truncates the X-GraphQL-Key
		// header, then depending on the types of node IDs that were skipped,
		// skipped:$type_name keys are added to the list
		$this->purge( 'skipped:' . $id_prefix );
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

		// if the post author hasn't changed, do nothing
		if ( $post_after->post_author === $post_before->post_author ) {
			return;
		}

		// evict caches for the before and after post author (purge their archive pages)
		$this->purge_nodes( 'user', $post_after->post_author );

		// evict caches for the before and after post author (purge their archive pages)
		$this->purge_nodes( 'user', $post_before->post_author );

		// Delete the cached results associated with this post/key
		$this->purge_nodes( 'post', $post_id );
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

		// If the post type is not public and not publicly queryable
		// don't track it
		if ( false === $post_type_object->public && false === $post_type_object->publicly_queryable ) {
			return;
		}

		// Delete the cached results associated with this post/key
		$this->purge_nodes( 'post', $post->ID );
	}

	/**
	 * Tracks creation of terms
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Taxonomy term ID.
	 * @param string $taxonomy Taxonomy name.
	 */
	public function on_created_term_cb( $term_id, $tt_id, $taxonomy ) {
		$tax_object = get_taxonomy( $taxonomy );

		if ( false === $tax_object || ! in_array( $taxonomy, \WPGraphQL::get_allowed_taxonomies(), true ) ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$type_name = strtolower( $tax_object->graphql_single_name );
		$this->purge( 'list:' . $type_name );
	}

	/**
	 * Whether the taxonomy is tracked
	 *
	 * @param string $taxonomy The name of the taxonomy to check
	 *
	 * @return bool
	 */
	public function is_taxonomy_tracked( $taxonomy ) {
		return in_array( $taxonomy, \WPGraphQL::get_allowed_taxonomies(), true );
	}

	/**
	 * Tracks creation of terms
	 *
	 * @param int    $term_id      Term ID.
	 * @param int    $tt_id        Taxonomy term ID.
	 * @param string $taxonomy     Taxonomy name.
	 * @param mixed  $deleted_term Deleted term object.
	 */
	public function on_deleted_term_cb( $term_id, $tt_id, $taxonomy, $deleted_term ) {
		$tax_object = get_taxonomy( $taxonomy );

		if ( false === $tax_object || ! $this->is_taxonomy_tracked( $taxonomy ) ) {
			return;
		}

		if ( ! $deleted_term instanceof WP_Term ) {
			return;
		}

		// Delete the cached results associated with this term/key
		$this->purge_nodes( 'term', $term_id );
	}

	/**
	 * Evict caches when terms are updated
	 *
	 * @param int $meta_id ID of updated metadata entry.
	 * @param int $object_id ID of the object metadata is for.
	 * @param string $meta_key Metadata key.
	 * @param mixed $meta_value Metadata value. Serialized if non-scalar.
	 */
	public function on_updated_term_meta_cb( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( empty( $term = get_term( $object_id ) ) || ! $term instanceof WP_Term ) {
			return;
		}

		$tax_object = get_taxonomy( $term->taxonomy );

		// If the updated term is of a post type that isn't being tracked, do nothing
		if ( false === $tax_object || ! $this->is_taxonomy_tracked( $term->taxonomy ) ) {
			return;
		}

		if ( false === $this->should_track_meta( $meta_key, $meta_value, $term ) ) {
			return;
		}

		// Delete the cached results associated with this post/key
		$this->purge_nodes( 'term', $term->term_id );
	}

	/**
	 * Listen for changes to the Term Taxonomy. This is called after posts that have
	 * a taxonomy associated with them are published. We don't always want to purge
	 * caches related to terms when they're associated with a post, but rather when the association
	 * becomes public. For example, a term being associated with a draft post shouldn't purge
	 * cache, but the publishing of the draft post that has a term associated with it
	 * should purge the terms cache.
	 *
	 * @param int    $tt_id The Term Taxonomy ID of the term
	 * @param string $taxonomy The name of the taxonomy the term belongs to
	 *
	 * @return void
	 */
	public function on_edited_term_taxonomy_cb( $tt_id, $taxonomy ) {
		if ( ! $this->is_taxonomy_tracked( $taxonomy ) ) {
			return;
		}

		$term = get_term_by( 'term_taxonomy_id', $tt_id, $taxonomy );

		if ( ! $term instanceof WP_Term ) {
			return;
		}

		// Delete the cached results associated with this post/key
		$this->purge_nodes( 'term', $term->term_id );
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

		// If the post type is not a public post type
		// that is set to show in GraphQL, ignore it
		if ( ! in_array( $post->post_type, \WPGraphQL::get_allowed_post_types(), true ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );

		// If the post type is not public and not publicly queryable
		// don't track it
		if ( false === $post_type_object->public && false === $post_type_object->publicly_queryable ) {
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

		$post_type_object = get_post_type_object( $post->post_type );
		$type_name        = strtolower( $post_type_object->graphql_single_name );

		// if we create a post
		// we need to purge lists of the type
		// as the created node might affect the list
		if ( 'CREATE' === $action_type ) {
			$this->purge( 'list:' . $type_name );
		}

		// if we update or delete a post
		// we need to purge any queries that have that
		// specific node in it
		if ( 'UPDATE' === $action_type || 'DELETE' === $action_type ) {
			// Delete the cached results associated with this post/key
			$this->purge_nodes( 'post', $post->ID );
		}
	}

	/**
	 * Listen for changes to the user profile
	 *
	 * @param int     $user_id       User ID.
	 * @param WP_User $old_user_data Object containing user's data prior to update.
	 */
	public function on_user_profile_update_cb( $user_id, $old_user_data ) {
		// Delete the cached results associated with this key
		$this->purge_nodes( 'user', $user_id );
	}

	/**
	 * Listens for changes to the user object and evicts caches related to that user.
	 *
	 * @param int    $meta_id     ID of updated metadata entry.
	 * @param int    $object_id   ID of the object metadata is for.
	 * @param string $meta_key    Metadata key.
	 * @param mixed  $_meta_value Metadata value. Serialized if non-scalar.
	 */
	public function on_user_meta_change_cb( $meta_id, $object_id, $meta_key, $_meta_value ) {
		$user = get_user_by( 'id', $object_id );

		if ( ! $user ) {
			return;
		}

		if ( ! $this->should_track_meta( $meta_key, $_meta_value, $user ) ) {
			return;
		}

		// Delete the cached results associated with this key
		$this->purge_nodes( 'user', $user->ID );
	}

	/**
	 *
	 * @param int      $deleted_id       ID of the deleted user.
	 * @param int|null $reassign_id ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 */
	public function on_user_deleted_cb( $deleted_id, $reassign_id ) {
		global $wpdb;

		$this->purge_nodes( 'user', $deleted_id );

		if ( $reassign_id ) {
			$this->purge_nodes( 'user', $reassign_id );

			// get the ids of the posts the user was the author of
			// this query runs inside the wp_delete_user function
			// but is not directly hookable/filterable
			// so we do it here to collect the IDs of the posts
			// that were re-assigned so that we can evict related caches properly
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$reassigned_post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d", $reassign_id ) );
			if ( ! empty( $reassigned_post_ids ) ) {
				foreach ( $reassigned_post_ids as $reassigned_post_id ) {
					$this->purge_nodes( 'post', $reassigned_post_id );
				}
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

		// Make sure that $post is a valid post object.
		if ( empty( $post ) ) {
			return;
		}

		// if the post type is not tracked, ignore it
		if ( ! in_array( $post->post_type, \WPGraphQL::get_allowed_post_types(), true ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );

		// If the post type is not public and not publicly queryable
		// don't track it
		if ( false === $post_type_object->public && false === $post_type_object->publicly_queryable ) {
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

		// Delete the cached results associated with this post/key
		$this->purge_nodes( 'post', $post->ID );
	}

	/**
	 * Determines whether a menu is considered public and should be tracked
	 * by the activity monitor
	 *
	 * @param int $menu_id ID of the menu
	 *
	 * @return bool
	 */
	public function is_menu_public( $menu_id ) {
		$locations         = get_theme_mod( 'nav_menu_locations' );
		$assigned_menu_ids = ! empty( $locations ) ? array_values( $locations ) : [];

		if ( empty( $assigned_menu_ids ) ) {
			return false;
		}

		if ( in_array( $menu_id, $assigned_menu_ids, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Callback for menu locations theme mod. When menu locations are set/unset.
	 *
	 * @param array $value The old value of the nav menu locations
	 * @param mixed|array|bool $old_value The new value of the nav menu locations
	 *
	 * @return array
	 */
	public function on_set_nav_menu_locations_cb( $value, $old_value ) {
		$old_locations = ! empty( $old_value ) && is_array( $old_value ) ? $old_value : [];
		$new_locations = ! empty( $value ) && is_array( $value ) ? $value : [];

		// If old locations are same as new locations, do nothing
		if ( $old_locations === $new_locations ) {
			return $value;
		}

		// Trigger an action for each added location
		$added = array_diff( $new_locations, $old_locations );
		if ( ! empty( $added ) ) {
			$this->purge( 'list:menu' );
		}

		// Trigger an action for each location deleted
		$removed = array_diff( $old_locations, $new_locations );
		if ( ! empty( $removed ) ) {
			foreach ( $removed as $location => $removed_menu_id ) {
				$this->purge_nodes( 'term', $removed_menu_id );
			}
		}

		return $value;
	}

	/**
	 * Evict caches when nav menus are updated
	 *
	 * @param int   $menu_id The ID of the menu being updated
	 *
	 * @return void
	 */
	public function on_update_nav_menu_cb( $menu_id ) {
		if ( ! $this->is_menu_public( $menu_id ) ) {
			return;
		}

		$menu = get_term_by( 'id', absint( $menu_id ), 'nav_menu' );

		// menus have a term:id relay global ID, as they use the term loader
		$this->purge_nodes( 'term', $menu->term_id );
	}

	/**
	 * Evict caches when terms are updated
	 *
	 * @param int $meta_id ID of updated metadata entry.
	 * @param int $object_id ID of the object metadata is for.
	 * @param string $meta_key Metadata key.
	 * @param mixed $meta_value Metadata value. Serialized if non-scalar.
	 *
	 * @return void
	 */
	public function on_updated_menu_meta_cb( $meta_id, $object_id, $meta_key, $meta_value ) {

		// if the object id isn't a valid term
		if ( empty( $term = get_term( $object_id ) ) || ! $term instanceof WP_Term ) {
			return;
		}

		// If nav_menu isn't the taxonomy, proceed
		if ( 'nav_menu' !== $term->taxonomy ) {
			return;
		}

		// if the menu isn't public do nothing
		if ( ! $this->is_menu_public( $term->term_id ) ) {
			return;
		}

		if ( false === $this->should_track_meta( $meta_key, $meta_value, $term ) ) {
			return;
		}

		$this->purge_nodes( 'term', $term->term_id );
	}

	/**
	 * Listens for changes to meta for menu items
	 *
	 * @todo wire this up to an action. {updated/added/deleted}_post_meta isn't fired when ACF stores data on menu items
	 *
	 * @param int    $meta_id    ID of updated metadata entry.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value. This will be a PHP-serialized string
	 *                           representation of the value if the value is an array, an object,
	 *                           or itself a PHP-serialized string.
	 */
	public function on_menu_item_change_cb( $meta_id, $post_id, $meta_key, $meta_value ) {

		// get the post object being modified
		$post = get_post( $post_id );

		// if the post is not found or the post type is not nav menu item, ignore it
		if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
			return;
		}

		// if the meta key isn't tracked, ignore it
		//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		if ( false === $this->should_track_meta( $meta_key, $meta_value, $post ) ) {
			return;
		}

		$this->purge_nodes( 'post', $post->ID );
	}

	/**
	 * When an attachment is created, purge lists of media
	 *
	 * @param int $attachment_id
	 *
	 * @return void
	 */
	public function on_add_attachment_cb( $attachment_id ) {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return;
		}

		$this->purge( 'list:mediaitem' );
	}

	/**
	 * Evicts cache when image files are uploaded
	 *
	 * @param string $dummy      Unused.
	 * @param string $filename   Filename.
	 * @param string $image      Unused.
	 * @param string $mime_type  Unused.
	 * @param int    $post_id    Post ID.
	 */
	public function on_save_image_file_cb( $dummy, $filename, $image, $mime_type, $post_id ) {
		$this->on_edit_attachment_cb( $post_id );
	}

	public function on_edit_attachment_cb( $attachment_id ) {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return;
		}

		$this->purge_nodes( 'post', $attachment_id );
	}

	/**
	 * Handle purging when attachment is deleted
	 *
	 * @param $attachment_id
	 *
	 * @return void
	 */
	public function on_delete_attachment( $attachment_id ) {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return;
		}

		$this->purge_nodes( 'post', $attachment_id );
	}

	/**
	 * Fires when the comment status is in transition.
	 *
	 * @param int|string $new_status The new comment status.
	 * @param int|string $old_status The old comment status.
	 * @param WP_Comment $comment    Comment object.
	 */
	public function on_comment_transition_cb( $new_status, $old_status, $comment ) {
		// Only evict cache if transitioning to or from 'approved'
		if ( in_array( 'approved', [ $new_status, $old_status ], true ) ) {
			$this->purge_nodes( 'comment', $comment->comment_ID );
			$this->purge( 'list:comment' );
		}
	}

	/**
	 * Fires immediately after a comment is inserted into the database.
	 *
	 * @param int        $comment_id The comment ID.
	 * @param WP_Comment $comment    Comment object.
	 */
	public function on_insert_comment_cb( $comment_id, $comment ) {
		if ( isset( $comment->comment_approved ) && '1' === $comment->comment_approved ) {
			$this->purge_nodes( 'comment', $comment_id );
			$this->purge( 'list:comment' );
		}
	}
}
