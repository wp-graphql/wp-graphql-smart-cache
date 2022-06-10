<?php
/**
 * Plugin Name:     WP GraphQL Labs
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     Development projects for WP Graphql for WordPress
 * Author:          WPGraphQL
 * Author URI:      http://www.wpgraphql.com
 * Domain Path:     /languages
 * Version:         0.1.0-alpha
 *
 * Persisted Queries and Caching
 */

namespace WPGraphQL\Labs;

use WPGraphQL\Labs\Cache\Collection;
use WPGraphQL\Labs\Cache\Invalidation;
use WPGraphQL\Labs\Cache\Results;
use WPGraphQL\Labs\GraphiQL\GraphiQL;
use WPGraphQL\Labs\Admin\Editor;
use WPGraphQL\Labs\Admin\Settings;
use WPGraphQL\Labs\Document\Description;
use WPGraphQL\Labs\Document\Grant;
use WPGraphQL\Labs\Document\MaxAge;
use WPGraphQL\Model\Avatar;
use WPGraphQL\Model\Comment;
use WPGraphQL\Model\Plugin;
use WPGraphQL\Model\PostType;
use WPGraphQL\Model\Taxonomy;
use WPGraphQL\Model\User;
use WPGraphQL\Model\CommentAuthor;
use WPGraphQL\Model\Menu;
use WPGraphQL\Model\MenuItem;
use WPGraphQL\Model\UserRole;
use WPGraphQL\Model\Post;
use WPGraphQL\Type\WPInterfaceType;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Model\Term;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/vendor/autoload.php';

/**
 * Set the graphql-php server persistent query loader during server config setup.
 * When a queryId is found on the request, the call back is invoked to look up the query string.
 */
add_action(
	'graphql_server_config',
	function ( \GraphQL\Server\ServerConfig $config ) {
		$config->setPersistentQueryLoader(
			[ '\WPGraphQL\Labs\Document\Loader', 'by_query_id' ]
		);
	},
	10,
	1
);

/**
 * Initialize the functionality for interacting with persisted queries using the GraphiQL IDE.
 */
add_action(
	'admin_init',
	function () {
		$graphiql = new GraphiQL();
		$graphiql->init();
	}
);

add_action(
	'init',
	function () {
		$document = new Document();
		$document->init();

		$description = new Description();
		$description->init();

		$grant = new Grant();
		$grant->init();

		$max_age = new MaxAge();
		$max_age->init();

		$errors = new AdminErrors();
		$errors->init();

		$settings = new Settings();
		$settings->init();
	}
);

add_action(
	'admin_init',
	function () {
		$editor = new Editor();
		$editor->admin_init();
	},
	10
);

add_action(
	'wp_loaded',
	function () {

		// override the query execution with cached results, if present
		$results = new Results();
		$results->init();

		// Start collecting queries for cache
		$collection = new Collection();
		$collection->init();

		// start listening to events that should invalidate caches
		$invalidation = new Invalidation( $collection );
		$invalidation->init();
	}
);

/**
 * Defines the Model classes responsible for resolving a Type
 *
 * @param array $config The config for the type being registered to the Schema
 * @param mixed|WPObjectType|WPInterfaceType $type The instance of the Type class
 *
 * @return array
 */
function graphql_add_model_to_type_config( $config, $type ) {
	// if the object type has no name, return config as is
	if ( ! isset( $config['name'] ) ) {
		return $config;
	}

	// if the type already has a model set, use it
	if ( ! empty( $config['model'] ) ) {
		return $config;
	}

	// if there's no model set, set it now
	switch ( strtolower( $config['name'] ) ) {
		case 'user':
			$config['model'] = User::class;
			break;
		case 'comment':
			$config['model'] = Comment::class;
			break;
		case 'avatar':
			$config['model'] = Avatar::class;
			break;
		case 'commentauthor':
			$config['model'] = CommentAuthor::class;
			break;
		case 'menu':
			$config['model'] = Menu::class;
			break;
		case 'menuitem':
			$config['model'] = MenuItem::class;
			break;
		case 'plugin':
			$config['model'] = Plugin::class;
			break;
		case 'contenttype':
			$config['model'] = PostType::class;
			break;
		case 'taxonomy':
			$config['model'] = Taxonomy::class;
			break;
		case 'userrole':
			$config['model'] = UserRole::class;
			break;
	}

	return $config;
}

// Hook in when the GraphQL request is getting started
add_action(
	'init_graphql_request',
	function () {
		$post_type_graphql_types = [];
		$term_graphql_types      = [];

		// determine the graphql types that represent post types
		$post_types = \WPGraphql::get_allowed_post_types();
		foreach ( $post_types as $post_type ) {
			$post_type_object          = get_post_type_object( $post_type );
			$post_type_graphql_types[] = strtolower( $post_type_object->graphql_single_name );
		}

		// determine the graphql types that represent terms
		$taxonomies = \WPGraphql::get_allowed_taxonomies();
		foreach ( $taxonomies as $taxonomy_name ) {
			$taxonomy             = get_taxonomy( $taxonomy_name );
			$term_graphql_types[] = strtolower( $taxonomy->graphql_single_name );
		}

		// filter the model in for
		add_filter(
			'graphql_wp_object_type_config',
			function ( $config, $object_type ) use ( $term_graphql_types, $post_type_graphql_types ) {

				// if the model is already set, use it
				if ( isset( $config['model'] ) ) {
					return $config;
				}

				// if the $config has no name, return the config
				if ( ! isset( $config['name'] ) ) {
					return $config;
				}

				// if the config name matches one of the graphql types for posts, set the model
				if ( in_array( strtolower( $config['name'] ), $post_type_graphql_types, true ) ) {
					$config['model'] = Post::class;
				}

				// if the config name matches one of the graphql types for taxonomies, set the model
				if ( in_array( strtolower( $config['name'] ), $term_graphql_types, true ) ) {
					$config['model'] = Term::class;
				}

				return $config;
			},
			10,
			2
		);

		add_filter( 'graphql_wp_interface_type_config', 'WPGraphQL\Labs\graphql_add_model_to_type_config', 10, 2 );
		add_filter( 'graphql_wp_object_type_config', 'WPGraphQL\Labs\graphql_add_model_to_type_config', 10, 2 );
	}
);
