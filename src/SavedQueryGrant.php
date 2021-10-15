<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

use WPGraphQL\PersistedQueries\ValidationRules\AllowDenyQueryDocument;

class SavedQueryGrant {

	const TAXONOMY_NAME = 'graphql_query_grant';

	const ALLOW    = 'allow';
	const DENY     = 'deny';
	const DEFAULT  = 'default';

	public function init() {
		register_taxonomy(
			self::TAXONOMY_NAME,
			SavedQuery::TYPE_NAME,
			[
				'description'        => __( 'Allow/Deny access grant for a saved GraphQL query', 'wp-graphql-persisted-queries' ),
				'labels'             => [
					'name' => __( 'Allow/Deny', 'wp-graphql-persisted-queries' ),
				],
				'hierarchical'       => false,
				'show_admin_column'  => true,
				'show_in_menu'       => false,
				'show_in_quick_edit' => false,
				'meta_box_cb'        => [ $this, 'admin_input_box' ],
			]
		);

		// Add to the wpgraphql server validation rules.
		// This filter allows us to add our validation rule to check a query for allow/deny access.
		add_filter( 'graphql_validation_rules', [ $this, 'filter_add_validation_rules' ], 10, 2 );

		if ( is_admin() ) {
			add_action( 'save_post', [ $this, 'save_cb' ] );

			// Add to the wp-graphql admin settings page
			add_action( 'graphql_register_settings', function() {
				register_graphql_settings_field( 'graphql_persisted_queries_section', [
					'name'    => 'grant_mode',
					'label'   => __( 'Allow/Deny Mode', 'wp-graphql-persisted-queries' ),
					'desc'    => __( 'Allow or deny specific queries. Or leave your graphql endpoint wideopen with the public option (not recommended).', 'wp-graphql-persisted-queries' ),
					'type'    => 'radio',
					'default' => 'only_allowed',
					'options' => [
						'public' => 'Public',
						'only_allowed' => 'Allow only specific queries',
						'some_denied' => 'Deny some specific queries',
					]
				]);
			});
		}
	}

	/**
	 * Draw the input field for the post edit
	 */
	public function admin_input_box( $post ) {
		wp_nonce_field( 'graphql_query_grant', 'savedquery_grant_noncename' );

		$item  = wp_get_object_terms( $post->ID, self::TAXONOMY_NAME );
		$value = $item[0]->name;
		$html  = sprintf(
			'<input type="radio" id="graphql_query_grant_allow" name="graphql_query_grant" value="%s" %s>',
			self::ALLOW,
			checked( $value, self::ALLOW, false )
		);
		$html .= '<label for="graphql_query_grant_allow">Allowed</label>&nbsp;';
		$html  .= sprintf(
			'<input type="radio" id="graphql_query_grant_deny" name="graphql_query_grant" value="%s" %s>',
			self::DENY,
			checked( $value, self::DENY, false )
		);
		$html .= '<label for="graphql_query_grant_deny">Deny</label>&nbsp;';
		$html  .= sprintf(
			'<input type="radio" id="graphql_query_grant_default" name="graphql_query_grant" value="%s" %s>',
			self::DEFAULT,
			checked( $value, self::DEFAULT, false )
		);
		$html .= '<label for="graphql_query_grant_default">Use global default</label>&nbsp;';
		echo wp_kses(
			$html,
			[
				'input' => [
					'type'    => true,
					'id'      => true,
					'name'    => true,
					'value'   => true,
					'checked' => true,
				],
			]
		);
	}

	/**
	 * Use during processing of submitted form if value of selected input field is selected.
	 * And return value of the taxonomy.
	 *
	 * @param string The input form value
	 * @return string The string value used to save as the taxonomy value
	 */
	public function the_selection( $value ) {
		if ( in_array( $value, [
				self::ALLOW,
				self::DENY,
				self::DEFAULT
		] ) ) {
			return $value;
		}

		return self::DEFAULT;
	}

	/**
	 * When a post is saved, sanitize and store the data.
	 */
	public function save_cb( $post_id ) {
		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! check_admin_referer( 'graphql_query_grant', 'savedquery_grant_noncename' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['post_type'] ) || SavedQuery::TYPE_NAME !== $_POST['post_type'] ) {
			return;
		}

		if ( ! isset( $_POST['graphql_query_grant'] ) ) {
			return;
		}

		$data = $this->the_selection( sanitize_text_field( wp_unslash( $_POST['graphql_query_grant'] ) ) );

		// Save the data
		wp_set_post_terms( $post_id, $data, self::TAXONOMY_NAME );
	}

	public function filter_add_validation_rules( $validation_rules, $request ) {
		// Check the grant mode. If public for all, don't add this rule.
		$setting = get_graphql_setting( 'grant_mode', 'public', 'graphql_persisted_queries_section' );
		if ( 'public' !== $setting ) {
			$validation_rules['allow_deny_query_document'] = new AllowDenyQueryDocument( $setting );
		}

		return $validation_rules;
	}
}
