<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries\Admin;

use WPGraphQL\PersistedQueries\Document\Grant;

class Settings {

	// set this to true to see these in wp-admin
	public static function show_in_admin() {
		$display_admin = get_graphql_setting( 'editor_display', false, 'graphql_persisted_queries_section' );
		return ( 'on' === $display_admin );
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
						'title' => __( 'Persisted Queries', 'wp-graphql-persisted-queries' ),
					]
				);

				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'              => 'global_max_age',
						'label'             => __( 'Access-Control-Max-Age Header', 'wp-graphql-persisted-queries' ),
						'desc'              => __( 'Global Max-Age HTTP header. Integer value, greater or equal to zero.', 'wp-graphql-persisted-queries' ),
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
						'label'   => __( 'Allow/Deny Mode', 'wp-graphql-persisted-queries' ),
						'desc'    => __( 'Allow or deny specific queries. Or leave your graphql endpoint wideopen with the public option (not recommended).', 'wp-graphql-persisted-queries' ),
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
						'label'   => __( 'Display queries in admin editor', 'wp-graphql-persisted-queries' ),
						'desc'    => __( 'Toggle to show queries in wp-admin left side menu', 'wp-graphql-persisted-queries' ),
						'type'    => 'checkbox',
						'default' => 'off',
					]
				);
			}
		);
	}

}
