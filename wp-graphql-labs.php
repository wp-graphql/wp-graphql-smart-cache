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
use WPGraphQL\Labs\Cache\Results;
use WPGraphQL\Labs\AdminErrors;
use WPGraphQL\Labs\Document;
use WPGraphQL\Labs\GraphiQL\GraphiQL;
use WPGraphQL\Labs\Admin\Editor;
use WPGraphQL\Labs\Admin\Settings;
use WPGraphQL\Labs\Document\Description;
use WPGraphQL\Labs\Document\Grant;
use WPGraphQL\Labs\Document\MaxAge;

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
		$results = new Results();
		$results->init();

		$collection = new Collection();
		$collection->init();
	}
);

add_filter( 'graphql_wp_object_type_config', function($config, $object_type) {
	$post_types = \WPGraphql::get_allowed_post_types();
	$n = [];
	foreach ( $post_types as $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		$n[] = strtolower( $post_type_object->graphql_single_name );
	}
	if ( isset( $config['name'] )  && in_array( strtolower( $config['name'] ), $n, true ) ) {
		$config['model'] = 'WPGraphQL\Model\Post';
	}
	return $config;
}, 10, 2);

add_filter( 'graphql_wp_object_type_config', function($config, $object_type) {
	$types = \WPGraphql::get_allowed_taxonomies();
	$n = [];
	foreach ( $types as $type ) {
		$taxonomy = get_taxonomy( $type );
		$n[] = strtolower( $taxonomy->graphql_single_name );
	}
	if ( isset( $config['name'] )  && in_array( strtolower( $config['name'] ), $n, true ) ) {
		$config['model'] = 'WPGraphQL\Model\Term';
	}
	return $config;
}, 10, 2);

add_filter( 'graphql_wp_object_type_config', function($config, $object_type) {
	if ( isset( $config['name'] ) && strtolower( $config['name'] ) === 'user' ) {
		$config['model'] = 'WPGraphQL\Model\User';
	}
	return $config;
}, 10, 2);
