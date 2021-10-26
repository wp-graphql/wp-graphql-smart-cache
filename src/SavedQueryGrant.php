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

	// The string value used for the individual saved query
	const ALLOW                = 'allow';
	const DENY                 = 'deny';
	const USE_DEFAULT          = 'default';
	const NOT_SELECTED_DEFAULT = false;

	// The string value stored for the global admin setting
	const GLOBAL_ALLOWED = 'only_allowed';
	const GLOBAL_DENIED  = 'some_denied';
	const GLOBAL_PUBLIC  = 'public';
	const GLOBAL_DEFAULT = self::GLOBAL_PUBLIC; // The global admin setting default

	const GLOBAL_SETTING_NAME = 'grant_mode';

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

		add_action( sprintf( 'save_post_%s', SavedQuery::TYPE_NAME ), [ $this, 'save_cb' ] );

		// Add to the wp-graphql admin settings page
		add_action(
			'graphql_register_settings',
			function () {
				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'    => self::GLOBAL_SETTING_NAME,
						'label'   => __( 'Allow/Deny Mode', 'wp-graphql-persisted-queries' ),
						'desc'    => __( 'Allow or deny specific queries. Or leave your graphql endpoint wideopen with the public option (not recommended).', 'wp-graphql-persisted-queries' ),
						'type'    => 'radio',
						'default' => self::GLOBAL_DEFAULT,
						'options' => [
							self::GLOBAL_PUBLIC  => 'Public',
							self::GLOBAL_ALLOWED => 'Allow only specific queries',
							self::GLOBAL_DENIED  => 'Deny some specific queries',
						],
					]
				);
			}
		);
	}

	/**
	 * Draw the input field for the post edit
	 */
	public function admin_input_box( $post ) {
		wp_nonce_field( 'graphql_query_grant', 'savedquery_grant_noncename' );

		$value = $this->getQueryGrantSetting( $post->ID );
		$html  = sprintf(
			'<input type="radio" id="graphql_query_grant_allow" name="graphql_query_grant" value="%s" %s>',
			self::ALLOW,
			checked( $value, self::ALLOW, self::NOT_SELECTED_DEFAULT )
		);
		$html .= '<label for="graphql_query_grant_allow">Allowed</label>&nbsp;';
		$html .= sprintf(
			'<input type="radio" id="graphql_query_grant_deny" name="graphql_query_grant" value="%s" %s>',
			self::DENY,
			checked( $value, self::DENY, self::NOT_SELECTED_DEFAULT )
		);
		$html .= '<label for="graphql_query_grant_deny">Deny</label>&nbsp;';
		$html .= sprintf(
			'<input type="radio" id="graphql_query_grant_default" name="graphql_query_grant" value="%s" %s>',
			self::USE_DEFAULT,
			checked( $value, self::USE_DEFAULT, self::NOT_SELECTED_DEFAULT )
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
	 * Look up the allow/deny grant setting for a post
	 *
	 * @param int  The post id
	 */
	public static function getQueryGrantSetting( $post_id ) {
		$item = wp_get_object_terms( $post_id, self::TAXONOMY_NAME );
		return ! is_wp_error( $item ) && isset( $item[0]->name ) ? $item[0]->name : self::NOT_SELECTED_DEFAULT;
	}

	/**
	 * Use during processing of submitted form if value of selected input field is selected.
	 * And return value of the taxonomy.
	 *
	 * @param string The input form value
	 * @return string The string value used to save as the taxonomy value
	 */
	public function the_selection( $value ) {
		if ( in_array(
			$value,
			[
				self::ALLOW,
				self::DENY,
				self::USE_DEFAULT,
			],
			true
		) ) {
			return $value;
		}

		return self::USE_DEFAULT;
	}

	/**
	 * When a post is saved, sanitize and store the data.
	 */
	public function save_cb( $post_id ) {
		if ( empty( $_POST ) ) {
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

		if ( ! isset( $_REQUEST['savedquery_grant_noncename'] ) ) {
			return;
		}

		// phpcs:ignore
		if ( ! wp_verify_nonce( $_REQUEST['savedquery_grant_noncename'], 'graphql_query_grant' ) ) {
			return;
		}

		if ( ! isset( $_POST['graphql_query_grant'] ) ) {
			return;
		}

		$data = $this->the_selection( sanitize_text_field( wp_unslash( $_POST['graphql_query_grant'] ) ) );

		// Save the data
		$this->save( $post_id, $data );
	}

	public function save( $post_id, $grant ) {
		return wp_set_post_terms( $post_id, $grant, self::TAXONOMY_NAME );
	}

	public function filter_add_validation_rules( $validation_rules, $request ) {
		// Check the grant mode. If public for all, don't add this rule.
		$setting = get_graphql_setting( self::GLOBAL_SETTING_NAME, SavedQueryGrant::GLOBAL_DEFAULT, 'graphql_persisted_queries_section' );
		if ( SavedQueryGrant::GLOBAL_PUBLIC !== $setting ) {
			$validation_rules['allow_deny_query_document'] = new AllowDenyQueryDocument( $setting );
		}

		return $validation_rules;
	}
}
