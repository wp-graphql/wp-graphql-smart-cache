<?php
/**
 * Plugin Name: WPGraphQL Smart Cache
 * Plugin URI: https://github.com/wp-graphql/wp-graphql-smart-cache
 * GitHub Plugin URI: https://github.com/wp-graphql/wp-graphql-smart-cache
 * Description: Smart Caching and Cache Invalidation for WPGraphQL
 * Author: WPGraphQL
 * Author URI: http://www.wpgraphql.com
 * Requires at least: 5.0
 * Tested up to: 5.9.1
 * Requires PHP: 7.4
 * Text Domain: wp-graphql-smart-cache
 * Domain Path: /languages
 * Version: 0.2.1
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
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

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WPGRAPHQL_REQUIRED_MIN_VERSION = '1.2.0';
const WPGRAPHQL_SMART_CACHE_VERSION  = '0.2.1';

require __DIR__ . '/vendor/autoload.php';

if ( ! defined( 'WPGRAPHQL_SMART_CACHE_PLUGIN_DIR' ) ) {
	define( 'WPGRAPHQL_SMART_CACHE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Check whether ACF and WPGraphQL are active, and whether the minimum version requirement has been
 * met
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
	if ( empty( defined( 'WPGRAPHQL_VERSION' ) ) ) {
		return false;
	}

	// Have we met the minimum version requirement?
	if ( true === version_compare( WPGRAPHQL_VERSION, WPGRAPHQL_REQUIRED_MIN_VERSION, 'lt' ) ) {
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
			[ '\WPGraphQL\SmartCache\Document\Loader', 'by_query_id' ]
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
 * Show admin notice to admins if this plugin is active but either ACF and/or WPGraphQL
 * are not active
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
					$text = sprintf( 'WPGraphQL (v%s+) must be active for "wp-graphql-smart-cache" to work', WPGRAPHQL_REQUIRED_MIN_VERSION );

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

function _temp_patch_for_wpe_mu_plugin( $purge_keys ) {
	if ( ! function_exists( 'graphql_get_endpoint_url' ) || ! method_exists( 'WpeCommon', 'http_to_varnish' ) ) {
		return;
	}
	WpeCommon::http_to_varnish( 'PURGE_GRAPHQL', null, [
		'GraphQL-Purge-Keys' => $purge_keys,
		'GraphQL-URL' => graphql_get_endpoint_url(),
	] );
}
add_action( 'graphql_purge', '_temp_patch_for_wpe_mu_plugin', 0, 1 );
