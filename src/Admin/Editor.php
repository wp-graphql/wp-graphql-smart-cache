<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries\Admin;

class Editor {

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
