<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries\Admin;

use WPGraphQL\PersistedQueries\AdminErrors;
use WPGraphQL\PersistedQueries\Document;
use WPGraphQL\PersistedQueries\Document\Grant;
use WPGraphQL\PersistedQueries\Document\MaxAge;
use GraphQL\Error\SyntaxError;

class Editor {

	public function admin_init() {
		remove_action( sprintf( 'save_post_%s', Document::TYPE_NAME ), 'WPGraphQL\PersistedQueries\Document', 'save_document_cb', 10 );
		add_action( sprintf( 'save_post_%s', Document::TYPE_NAME ), [ $this, 'vaildate_and_save_cb' ], 10, 2 );
	}

	/**
	* When a post is saved, sanitize and store the data.
	*/
	public function vaildate_and_save_cb( $post_id, $post ) {
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

		$grant = new Grant();
		$data  = $grant->the_selection( sanitize_text_field( wp_unslash( $_POST['graphql_query_grant'] ) ) );
		$grant->save( $post_id, $data );

		$max_age = new MaxAge();
		$data    = sanitize_text_field( wp_unslash( $_POST['graphql_query_maxage'] ) );
		$max_age->save( $post_id, $data );

		try {
			$document = new Document();
			$document->save_document_cb( $post_id, $post );
		} catch ( SyntaxError $e ) {
			AdminErrors::add_message( 'Did not save invalid graphql query string. ' . $post['post_content'] );
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

}
