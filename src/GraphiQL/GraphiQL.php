<?php

namespace WPGraphQL\Labs\GraphiQL;

class GraphiQL {

	/**
	 * Enqueue the GraphiQL extensions
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'enqueue_graphiql_extension', [ $this, 'enqueue_graphiql_scripts' ] );
	}

	/**
	 * Enqueue the GraphiQL scripts
	 */
	public function enqueue_graphiql_scripts() {

		// filesystem path
		$build_dir = plugin_dir_path( dirname( __DIR__, 1 ) ) . '/build';

		// publicly enqueueable url
		$build_url = plugin_dir_url( dirname( __DIR__, 1 ) ) . '/build';

		// If the app files have not been built, don't enqueue it
		if ( ! file_exists( $build_dir . '/index.js' ) || ! file_exists( $build_dir . '/index.asset.php' ) ) {
			return;
		}

		// include the file from the file system
		$asset_file = include $build_dir . '/index.asset.php';

		$deps = isset( $asset_file['dependencies'] ) ? $asset_file['dependencies'] : [];
		$default_deps = [ 'wp-graphiql', 'wp-graphiql-app' ];
		$deps = array_merge( $default_deps, $deps );

		wp_enqueue_script(
			'wp-graphiql-labs', // Handle.
			$build_url . '/index.js',
			$deps,
			isset( $asset_file['version'] ) ? $asset_file['version'] : '1.0',
			true
		);

	}
}
