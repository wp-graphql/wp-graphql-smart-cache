<?php
/**
 * The max age admin and filter for individual querys
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

class QueryMaxAge {

	public function init() {
		// Add to the wp-graphql admin settings page
		add_action(
			'graphql_register_settings',
			function () {
				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'    => 'global_max_age',
						'label'   => __( 'Access-Control-Max-Age Header', 'wp-graphql-persisted-queries' ),
						'desc'    => __( 'Global Max-Age HTTP header. Integer value.', 'wp-graphql-persisted-queries' ),
						'type'    => 'text',
						'default' => null,
					]
				);
			}
		);

		add_filter( 'graphql_response_headers_to_send', [ $this, 'filter_response_headers' ], 10, 1 );
	}

	public function filter_response_headers( $headers ) {
		$age = get_graphql_setting( 'global_max_age', null, 'graphql_persisted_queries_section' );
		// Access-Control-Max-Age header should be zero or positive integer, no decimals.
		if ( is_numeric( $age ) && $age >= 0 ) {
			$headers['Access-Control-Max-Age'] = intval( $age );
		}
		return $headers;
	}

}
