<?php

namespace WPGraphQL\Labs\GraphiQL;

class GraphiQL {
	public function init() {
		add_action( 'enqueue_graphiql_extension', [ $this, 'enqueue_graphiql_scripts' ] );
	}

	/**
	 * Enqueue the GraphiQL scripts
	 */
	public function enqueue_graphiql_scripts() {
		$app_asset_file = include plugin_dir_path( __FILE__ ) . 'app/build/index.asset.php';
		$src            = plugins_url( 'app/build/index.js', __FILE__ );
		$deps           = array_merge( [ 'wp-graphiql' ], $app_asset_file['dependencies'] );
		$version        = $app_asset_file['version'];

		/**
		 * Enqueue the script to the GraphiQL IDE screen
		 */
		wp_enqueue_script( 'wp-graphiql-persisted-queries', $src, $deps, $version, true );
	}
}
