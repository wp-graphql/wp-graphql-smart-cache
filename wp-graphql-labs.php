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

use WPGraphQL\PersistedQueries\AdminErrors;
use WPGraphQL\PersistedQueries\Document;
use WPGraphQL\PersistedQueries\Admin\Editor;
use WPGraphQL\PersistedQueries\Admin\Settings;
use WPGraphQL\PersistedQueries\Document\Description;
use WPGraphQL\PersistedQueries\Document\Grant;
use WPGraphQL\PersistedQueries\Document\MaxAge;
use WPGraphQL\PersistedQueries\GraphiQL\GraphiQL;

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
			[ '\WPGraphQL\PersistedQueries\Document\Loader', 'by_query_id' ]
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
	'graphql_register_settings',
	function () {
		register_graphql_settings_section(
			'graphql_persisted_queries_section',
			[
				'title' => __( 'Persisted Queries', 'wp-graphql-persisted-queries' ),
			]
		);
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
