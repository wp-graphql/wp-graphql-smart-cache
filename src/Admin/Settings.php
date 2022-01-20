<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\Labs\Admin;

use WPGraphQL\Labs\Document\Grant;

class Settings {

	// set this to true to see these in wp-admin
	public static function show_in_admin() {
		$display_admin = \get_graphql_setting( 'editor_display', false, 'graphql_persisted_queries_section' );
		return ( 'on' === $display_admin );
	}

	// Settings checkbox set to on to enable caching
	public static function caching_enabled() {
		$option = \get_graphql_setting( 'cache_toggle', false, 'graphql_cache_section' );
		return ( 'on' === $option );
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
						'title' => __( 'Persisted Queries', 'wp-graphql-labs' ),
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
						'name'    => Grant::GLOBAL_SETTING_NAME,
						'label'   => __( 'Allow/Deny Mode', 'wp-graphql-labs' ),
						'desc'    => __( 'Allow or deny specific queries. Or leave your graphql endpoint wideopen with the public option (not recommended).', 'wp-graphql-labs' ),
						'type'    => 'radio',
						'default' => Grant::GLOBAL_DEFAULT,
						'options' => [
							Grant::GLOBAL_PUBLIC  => 'Public',
							Grant::GLOBAL_ALLOWED => 'Allow only specific queries',
							Grant::GLOBAL_DENIED  => 'Deny some specific queries',
						],
					]
				);

				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'    => 'editor_display',
						'label'   => __( 'Display queries in admin editor', 'wp-graphql-labs' ),
						'desc'    => __( 'Toggle to show queries in wp-admin left side menu', 'wp-graphql-labs' ),
						'type'    => 'checkbox',
						'default' => 'off',
					]
				);

				// Add a tab section to the graphql admin settings page
				register_graphql_settings_section(
					'graphql_cache_section',
					[
						'title' => __( 'Cache', 'wp-graphql-labs' ),
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
			}
		);
	}

}
