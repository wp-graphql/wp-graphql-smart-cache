<?php
/**
 * Content
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache\Admin;

use WPGraphQL\SmartCache\AdminErrors;
use WPGraphQL\SmartCache\Document;
use WPGraphQL\SmartCache\Document\Grant;
use WPGraphQL\SmartCache\Document\MaxAge;
use GraphQL\Error\SyntaxError;
use GraphQL\Server\RequestError;

class Editor {

	public function admin_init() {
		add_filter( 'wp_insert_post_data', [ $this, 'validate_before_save_cb' ], 10, 2 );
		add_action( sprintf( 'save_post_%s', Document::TYPE_NAME ), [ $this, 'save_document_cb' ], 10, 2 );

		// Enable excerpts for the persisted query post type for the wp admin editor
		add_post_type_support( Document::TYPE_NAME, 'excerpt' );

		// Change the text from Excerpt to Description where it is visible.
		add_filter( 'gettext', [ $this, 'translate_excerpt_text_cb' ], 10, 1 );
		add_filter( sprintf( 'manage_%s_posts_columns', Document::TYPE_NAME ), [ $this, 'add_description_column_to_admin_cb' ], 10, 1 );
		add_action( sprintf( 'manage_%s_posts_custom_column', Document::TYPE_NAME ), [ $this, 'fill_excerpt_content_cb' ], 10, 2 );
		add_filter( sprintf( 'manage_edit-%s_sortable_columns', Document::TYPE_NAME ), [ $this, 'make_excerpt_column_sortable_in_admin_cb' ], 10, 1 );

		add_filter( 'wp_editor_settings', [ $this, 'wp_editor_settings' ], 10, 2 );
	}

	/**
	 * If existing post is edited, verify query string in content is valid graphql
	 */
	public function validate_before_save_cb( $data, $post ) {
		try {
			$document = new Document();
			$data     = $document->validate_before_save_cb( $data, $post );
		} catch ( RequestError $e ) {
			$existing_post = get_post( $post['ID'] );

			// Overwrite new/invalid query with previous working query, or empty
			$data['post_content'] = $existing_post->post_content;

			AdminErrors::add_message( $e->getMessage() );
		}
		return $data;
	}

	public function is_valid_form( $post_id ) {
		if ( empty( $_POST ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['post_type'] ) || Document::TYPE_NAME !== $_POST['post_type'] ) {
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

		if ( ! isset( $_REQUEST['savedquery_maxage_noncename'] ) ) {
			return;
		}

		// phpcs:ignore
		if ( ! wp_verify_nonce( $_REQUEST['savedquery_maxage_noncename'], 'graphql_query_maxage' ) ) {
			return;
		}

		if ( ! isset( $_POST['graphql_query_maxage'] ) ) {
			return;
		}

		return true;
	}

	/**
	* When a post is saved, sanitize and store the data.
	*/
	public function save_document_cb( $post_id, $post ) {
		if ( ! $this->is_valid_form( $post_id ) ) {
			AdminErrors::add_message( 'Something is wrong with the form data' );
			return;
		}

		$grant = new Grant();
		// phpcs:ignore
		$data  = $grant->the_selection( sanitize_text_field( wp_unslash( $_POST['graphql_query_grant'] ) ) );
		$grant->save( $post_id, $data );

		try {
			$document = new Document();
			$document->save_document_cb( $post_id, $post );

			$max_age = new MaxAge();
			// phpcs:ignore
			$data    = sanitize_text_field( wp_unslash( $_POST['graphql_query_maxage'] ) );
			$max_age->save( $post_id, $data );
		} catch ( SyntaxError $e ) {
			AdminErrors::add_message( 'Did not save invalid graphql query string. ' . $post['post_content'] );
		} catch ( RequestError $e ) {
			AdminErrors::add_message( $e->getMessage() );
		}
	}

	/**
	 * Draw the input field for the post edit
	 */
	public static function grant_input_box_cb( $post ) {
		wp_nonce_field( 'graphql_query_grant', 'savedquery_grant_noncename' );

		$value = Grant::getQueryGrantSetting( $post->ID );
		$html  = sprintf(
			'<input type="radio" id="graphql_query_grant_allow" name="graphql_query_grant" value="%s" %s>',
			Grant::ALLOW,
			checked( $value, Grant::ALLOW, false )
		);
		$html .= '<label for="graphql_query_grant_allow">Allowed</label><br >';
		$html .= sprintf(
			'<input type="radio" id="graphql_query_grant_deny" name="graphql_query_grant" value="%s" %s>',
			Grant::DENY,
			checked( $value, Grant::DENY, false )
		);
		$html .= '<label for="graphql_query_grant_deny">Deny</label><br >';
		$html .= sprintf(
			'<input type="radio" id="graphql_query_grant_default" name="graphql_query_grant" value="%s" %s>',
			Grant::USE_DEFAULT,
			checked( $value, Grant::USE_DEFAULT, false )
		);
		$html .= '<label for="graphql_query_grant_default">Use global default</label><br >';
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
				'br'    => true,
			]
		);
	}

	/**
	 * Draw the input field for the post edit
	 */
	public static function maxage_input_box_cb( $post ) {
		wp_nonce_field( 'graphql_query_maxage', 'savedquery_maxage_noncename' );

		$max_age = new MaxAge();
		$value   = $max_age->get( $post->ID );
		$value   = absint( $value ) ? $value : 0;
		$html    = sprintf( '<input type="text" id="graphql_query_maxage" name="graphql_query_maxage" value="%s" />', $value );
		$html   .= '<br><label for="graphql_query_maxage">Max-Age HTTP header. Integer value.</label>';
		echo wp_kses(
			$html,
			[
				'input' => [
					'type'  => true,
					'id'    => true,
					'name'  => true,
					'value' => true,
				],
				'br'    => [],
			]
		);
	}

	/**
	 * Change the text from Excerpt to Description where it is visible.
	 *
	 * @param String  The string for the __() or _e() translation
	 * @return String  The translated or original string
	 */
	public function translate_excerpt_text_cb( $string ) {
		$post = get_post();
		if ( $post && Document::TYPE_NAME === $post->post_type ) {
			if ( 'Excerpt' === $string ) {
				return __( 'Description', 'wp-graphql-smart-cache' );
			}
			if ( 'Excerpts are optional hand-crafted summaries of your content that can be used in your theme. <a href="%s">Learn more about manual excerpts</a>.' === $string ) {
				return __( 'Add the query description.', 'wp-graphql-smart-cache' );
			}
		}
		return $string;
	}

	/**
	 * Enable excerpt as the description.
	 */
	public function add_description_column_to_admin_cb( $columns ) {
		// Use 'description' as the text the user sees
		$columns['excerpt'] = __( 'Description', 'wp-graphql-smart-cache' );
		return $columns;
	}

	public function fill_excerpt_content_cb( $column, $post_id ) {
		if ( 'excerpt' === $column ) {
			echo esc_html( get_the_excerpt( $post_id ) );
		}
	}

	public function make_excerpt_column_sortable_in_admin_cb( $columns ) {
		$columns['excerpt'] = true;
		return $columns;
	}

	public function wp_editor_settings( $settings, $editor_id ) {
		if ( 'content' === $editor_id && Document::TYPE_NAME === get_current_screen()->post_type ) {
			$settings['tinymce']       = false;
			$settings['quicktags']     = false;
			$settings['media_buttons'] = false;
		}

		return $settings;
	}
}
