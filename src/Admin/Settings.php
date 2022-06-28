<?php

namespace WPGraphQL\Labs\Admin;

use WPGraphQL\Labs\Cache\Results;
use WPGraphQL\Labs\Document\Grant;

class Settings {

	// set this to true to see these in wp-admin
	public static function show_in_admin() {
		$display_admin = function_exists( 'get_graphql_setting' ) ? \get_graphql_setting( 'editor_display', false, 'graphql_persisted_queries_section' ) : false;
		return ( 'on' === $display_admin );
	}

	// Settings checkbox set to on to enable caching
	public static function caching_enabled() {

		// get the cache_toggle setting
		$option = function_exists( 'get_graphql_setting' ) ? \get_graphql_setting( 'cache_toggle', false, 'graphql_cache_section' ) : false;

		// if there's no user logged in, and GraphQL Caching is enabled
		return ( 'on' === $option );
	}

	// Date/Time of the last time purge all happened through admin.
	public static function caching_purge_timestamp() {
		return function_exists( 'get_graphql_setting' ) ? \get_graphql_setting( 'purge_all_timestamp', false, 'graphql_cache_section' ) : false;
	}

	public static function graphql_endpoint() {
		$path = function_exists( 'get_graphql_setting' ) ? \get_graphql_setting( 'graphql_endpoint', 'graphql', 'graphql_general_settings' ) : 'graphql';
		return '/' . $path;
	}

	public function init() {
		// Add to the wp-graphql admin settings page
		add_action(
			'graphql_register_settings',
			function () {

				// Add a tab section to the graphql admin settings page
				register_graphql_settings_section(
					'graphql_persisted_queries_section',
					[
						'title' => __( 'Network Cache', 'wp-graphql-labs' ),
						'desc'  => __( 'These settings apply to GraphQL queries coming over HTTP requests.', 'wp-graphql-labs' ),
					]
				);

				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'              => 'global_max_age',
						'label'             => __( 'Access-Control-Max-Age Header', 'wp-graphql-labs' ),
						'desc'              => __( 'Global Max-Age HTTP header. Integer value, greater or equal to zero.', 'wp-graphql-labs' ),
						'type'              => 'number',
						'sanitize_callback' => function ( $value ) {
							if ( $value < 0 || ! is_numeric( $value ) ) {
								return 0;
							}
							return intval( $value );
						},
					]
				);

				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'              => Grant::GLOBAL_SETTING_NAME,
						'label'             => __( 'Allow/Deny Mode', 'wp-graphql-labs' ),
						'desc'              => __( 'Allow or deny specific queries. Or leave your graphql endpoint wideopen with the public option (not recommended).', 'wp-graphql-labs' ),
						'type'              => 'radio',
						'default'           => Grant::GLOBAL_DEFAULT,
						'options'           => [
							Grant::GLOBAL_PUBLIC  => 'Public',
							Grant::GLOBAL_ALLOWED => 'Allow only specific queries',
							Grant::GLOBAL_DENIED  => 'Deny some specific queries',
						],
						'sanitize_callback' => function ( $value ) {
							// If the value changed, trigger cache purge
							if ( function_exists( 'get_graphql_setting' ) ) {
								$current_setting = \get_graphql_setting( Grant::GLOBAL_SETTING_NAME, Grant::GLOBAL_DEFAULT, 'graphql_persisted_queries_section' );
								if ( $current_setting !== $value ) {
									// Action for those listening to purge_all
									do_action( 'wpgraphql_cache_purge_all' );

									// Purge the local cache results if enabled
									$cache_object = new Results();
									$cache_object->purge_all();
								}
							}
							return $value;
						},
					]
				);

				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'    => 'editor_display',
						'label'   => __( 'Display saved queries in admin editor', 'wp-graphql-labs' ),
						'desc'    => __( 'Toggle to show saved queries in wp-admin left side menu', 'wp-graphql-labs' ),
						'type'    => 'checkbox',
						'default' => 'off',
					]
				);

				// Add a tab section to the graphql admin settings page
				register_graphql_settings_section(
					'graphql_cache_section',
					[
						'title' => __( 'Object Cache', 'wp-graphql-labs' ),
						'desc'  => __( 'Use local object or transient cache to save entire GraphQL query results.', 'wp-graphql-labs' ),
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'    => 'cache_toggle',
						'label'   => __( 'Enable results caching for improved speed', 'wp-graphql-labs' ),
						'desc'    => __( 'Toggle to enable caching of graphql query results', 'wp-graphql-labs' ),
						'type'    => 'checkbox',
						'default' => 'off',
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'              => 'global_ttl',
						'label'             => __( 'Cache expiration time', 'wp-graphql-labs' ),
						// translators: the global cache ttl default value
						'desc'              => sprintf( __( 'Global GraphQL cache expiration time in seconds. Integer value, greater or equal to zero. Default %s.', 'wp-graphql-labs' ), Results::GLOBAL_DEFAULT_TTL ),
						'type'              => 'number',
						'sanitize_callback' => function ( $value ) {
							if ( $value < 0 || ! is_numeric( $value ) ) {
								return null;
							}
							return intval( $value );
						},
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'              => 'purge_all',
						'label'             => __( 'Purge The Cache?', 'wp-graphql-labs' ),
						'desc'              => __( 'Select this box and click the save button.', 'wp-graphql-labs' ),
						'type'              => 'checkbox',
						'default'           => 'off',
						'sanitize_callback' => function ( $value ) {
							return false;
						},
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'              => 'purge_all_timestamp',
						'label'             => __( 'Did you purge the cache?', 'wp-graphql-labs' ),
						'desc'              => __( 'This field displays the last time the purge all was invoked on this page.', 'wp-graphql-labs' ),
						'type'              => 'text',
						'sanitize_callback' => function ( $value ) {
							$existing_purge_all_time = self::caching_purge_timestamp();

							if ( empty( $_POST ) || //phpcs:ignore
								! isset( $_POST['graphql_cache_section']['purge_all'] )  //phpcs:ignore
							) {
								return $existing_purge_all_time;
							}

							// Purge the cache, then return/save a new purge time
							 //phpcs:ignore
							if ( 'on' === $_POST['graphql_cache_section']['purge_all'] ) {

								// Trigger action when cache purge_all is invoked
								do_action( 'wpgraphql_cache_purge_all' );

								$cache_object = new Results();
								if ( true === $cache_object->purge_all() ) {
									return gmdate( 'D, d M Y H:i T' );
								}
							}

							return $existing_purge_all_time;
						},
					]
				);
			}
		);
	}

}
