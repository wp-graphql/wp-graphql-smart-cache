<?php
/**
 * Plugin Name:     WP GraphQL Persisted Queries
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     Persisted Queries and Caching for WP Graphql for WordPress
 * Author:          WPGraphQL
 * Author URI:      http://www.wpgraphql.com
 * Domain Path:     /languages
 * Version:         0.1.0-alpha
 */

namespace WPGraphQL\PersistedQueries;

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
			[ __NAMESPACE__ . '\Lookup', 'by_query_id' ]
		);
	},
	10,
	1
);

add_action(
	'init',
	function () {
		$query_object = new SavedQuery();
		$query_object->init();

		$query_description = new SavedQueryDescription();
		$query_description->init();
	}
);
