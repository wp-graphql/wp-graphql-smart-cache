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
		add_action( sprintf( 'save_post_%s', Document::TYPE_NAME ), [ $this, 'save_document_cb' ], 10, 2 );
		add_action( sprintf( 'save_post_%s', Document::TYPE_NAME ), [ $this, 'save_grant_cb' ] );
		add_action( sprintf( 'save_post_%s', Document::TYPE_NAME ), [ $this, 'save_maxage_cb' ] );
	}

	public function save_document_cb( $post_id, $post ) {
		try {
			$document = new Document();
			$document->save_document_cb( $post_id, $post );
		} catch ( SyntaxError $e ) {
			AdminErrors::add_message( 'Did not save invalid graphql query string. ' . $post['post_content'] );
		}
	}

	/**
	* When a post is saved, sanitize and store the data.
	*/
	public function save_grant_cb( $post_id ) {
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

		$grant = new Grant();
		$data  = $grant->the_selection( sanitize_text_field( wp_unslash( $_POST['graphql_query_grant'] ) ) );

		// Save the data
		$grant->save( $post_id, $data );
	}

	/**
	 * When a post is saved, sanitize and store the data.
	 */
	public function save_maxage_cb( $post_id ) {
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

		$data = sanitize_text_field( wp_unslash( $_POST['graphql_query_maxage'] ) );

		$max_age = new MaxAge();
		$max_age->save( $post_id, $data );
	}


}
