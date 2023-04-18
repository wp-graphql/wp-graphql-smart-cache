<?php
/**
 * Plugin Name: WPGraphQL Smart Cache
 * Plugin URI: https://github.com/wp-graphql/wp-graphql-smart-cache
 * GitHub Plugin URI: https://github.com/wp-graphql/wp-graphql-smart-cache
 * Description: Smart Caching and Cache Invalidation for WPGraphQL
 * Author: WPGraphQL
 * Author URI: http://www.wpgraphql.com
 * Requires at least: 5.6
 * Tested up to: 6.1
 * Requires PHP: 7.4
 * Text Domain: wp-graphql-smart-cache
 * Domain Path: /languages
 * Version: 1.0.3
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Persisted Queries and Caching for WPGraphQL
 */

namespace WPGraphQL\SmartCache;

use Appsero\Client;
use WPGraphQL\SmartCache\Cache\Collection;
use WPGraphQL\SmartCache\Cache\Invalidation;
use WPGraphQL\SmartCache\Cache\Results;
use WPGraphQL\SmartCache\Admin\Editor;
use WPGraphQL\SmartCache\Admin\Settings;
use WPGraphQL\SmartCache\Document\Description;
use WPGraphQL\SmartCache\Document\Grant;
use WPGraphQL\SmartCache\Document\MaxAge;
use WPGraphQL\SmartCache\Document\Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If the autoload file exists, require it.
// If the plugin was installed from composer, the autoload
// would be required elsewhere in the project
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

if ( ! defined( 'WPGRAPHQL_SMART_CACHE_VERSION' ) ) {
	define( 'WPGRAPHQL_SMART_CACHE_VERSION', '1.0.3' );
}

if ( ! defined( 'WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION' ) ) {
	define( 'WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION', '1.12.0' );
}

if ( ! defined( 'WPGRAPHQL_SMART_CACHE_PLUGIN_DIR' ) ) {
	define( 'WPGRAPHQL_SMART_CACHE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Check whether WPGraphQL is active, and whether the minimum version requirement has been met,
 * and whether the autoloader is working as expected
 *
 * @return bool
 * @since 0.3
 */
function can_load_plugin() {

	// Is WPGraphQL active?
	if ( ! class_exists( 'WPGraphQL' ) ) {
		return false;
	}

	// Do we have a WPGraphQL version to check against?
	if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
		return false;
	}

	// Have we met the minimum version requirement?
	if ( true === version_compare( WPGRAPHQL_VERSION, WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION, 'lt' ) ) {
		return false;
	}

	// If the Document class doesn't exist, then the autoloader failed to load.
	// This likely means that the plugin was installed via composer and the parent
	// project doesn't have the autoloader setup properly
	if ( ! class_exists( Document::class ) ) {
		return false;
	}

	return true;
}

/**
 * Set the graphql-php server persistent query loader during server config setup.
 * When a queryId is found on the request, the call back is invoked to look up the query string.
 */
add_action(
	'graphql_server_config',
	function ( \GraphQL\Server\ServerConfig $config ) {
		$config->setPersistentQueryLoader(
			[ Loader::class, 'by_query_id' ]
		);
	},
	10,
	1
);

add_action(
	'init',
	function () {

		/**
		 * If WPGraphQL is not active, or is an incompatible version, show the admin notice and bail
		 */
		if ( false === can_load_plugin() ) {
			// Show the admin notice
			add_action( 'admin_init', __NAMESPACE__ . '\show_admin_notice' );

			// Bail
			return;
		}

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

/**
 * Show admin notice to admins if this plugin is active but WPGraphQL
 * is not active, or doesn't meet version requirements
 *
 * @return bool
 */
function show_admin_notice() {

	/**
	 * For users with lower capabilities, don't show the notice
	 */
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}

	add_action(
		'admin_notices',
		function () {
			?>
			<div class="error notice">
				<p>
					<?php
					// translators: placeholder is the version number of the WPGraphQL Plugin that this plugin depends on
					$text = sprintf( 'WPGraphQL (v%s+) must be active for "wp-graphql-smart-cache" to work', WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION );

					// phpcs:ignore
					esc_html_e( $text, 'wp-graphql-smart-cache' );
					?>
				</p>
			</div>
			<?php
		}
	);
}

add_action(
	'admin_init',
	function () {
		if ( false === can_load_plugin() ) {
			return;
		}

		$editor = new Editor();
		$editor->admin_init();
	},
	10
);

add_action(
	'wp_loaded',
	function () {
		if ( false === can_load_plugin() ) {
			return;
		}

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

add_action(
	'graphql_purge',
	function ( $purge_keys ) {
		if ( ! function_exists( 'graphql_get_endpoint_url' ) || ! class_exists( 'WpeCommon' ) || ! method_exists( 'WpeCommon', 'http_to_varnish' ) ) {
			return;
		}
		\WpeCommon::http_to_varnish(
			'PURGE_GRAPHQL',
			null,
			[
				'GraphQL-Purge-Keys' => $purge_keys,
				'GraphQL-URL'        => graphql_get_endpoint_url(),
			]
		);
	},
	0,
	1
);

add_action(
	'wpgraphql_cache_purge_all',
	function ( $purge_keys ) {
		if ( ! function_exists( 'graphql_get_endpoint_url' ) || ! class_exists( 'WpeCommon' ) || ! method_exists( 'WpeCommon', 'http_to_varnish' ) ) {
			return;
		}
		\WpeCommon::http_to_varnish(
			'PURGE_GRAPHQL',
			null,
			[
				'GraphQL-Purge-Keys' => 'graphql:Query',
				'GraphQL-URL'        => graphql_get_endpoint_url(),
			]
		);
	},
	0,
	1
);

/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function appsero_init_tracker_wpgraphql_smart_cache() {

	// If the class doesn't exist, or code is being scanned by PHPSTAN, move on.
	if ( ! class_exists( 'Appsero\Client' ) || defined( 'PHPSTAN' ) ) {
		return;
	}

	$client = new Client( '66f03878-3df1-40d7-8be9-0069994480d4', 'WPGraphQL Smart Cache', __FILE__ );

	$insights = $client->insights();

	// If the Appsero client has the add_plugin_data method, use it
	if ( method_exists( $insights, 'add_plugin_data' ) ) {
		// @phpstan-ignore-next-line
		$insights->add_plugin_data();
	}

	// @phpstan-ignore-next-line
	$insights->init();
}

appsero_init_tracker_wpgraphql_smart_cache();
