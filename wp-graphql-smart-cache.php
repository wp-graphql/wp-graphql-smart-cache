<?php
/**
 * Plugin Name:     WP GraphQL Smart Cache
 * Plugin URI:      https://github.com/wp-graphql/wp-graphql-smart-cache
 * Description:     Smart Caching and Cache Invalidation for WPGraphQL
 * Author:          WPGraphQL
 * Author URI:      http://www.wpgraphql.com
 * Domain Path:     /languages
 * Version:         0.1.2
 *
 * Persisted Queries and Caching for WPGraphQL
 */

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Cache\Collection;
use WPGraphQL\SmartCache\Cache\Invalidation;
use WPGraphQL\SmartCache\Cache\Results;
use WPGraphQL\SmartCache\Admin\Editor;
use WPGraphQL\SmartCache\Admin\Settings;
use WPGraphQL\SmartCache\Document\Description;
use WPGraphQL\SmartCache\Document\Grant;
use WPGraphQL\SmartCache\Document\MaxAge;
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

if ( ! defined( 'WPGRAPHQL_LABS_PLUGIN_DIR' ) ) {
	define( 'WPGRAPHQL_LABS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Set the graphql-php server persistent query loader during server config setup.
 * When a queryId is found on the request, the call back is invoked to look up the query string.
 */
add_action(
	'graphql_server_config',
	function ( \GraphQL\Server\ServerConfig $config ) {
		$config->setPersistentQueryLoader(
			[ '\WPGraphQL\SmartCache\Document\Loader', 'by_query_id' ]
		);
	},
	10,
	1
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
