<?php
/**
 * Content
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Document;

class AdminErrors {

	const TRANSIENT_NAME      = 'graphql_save_graphql_query_validation_error_messages';
	const MESSAGE_TTL_SECONDS = 60;

	public function init() {
		add_action( 'admin_notices', [ $this, 'display_validation_messages' ] );
	}

	public static function add_message( $message ) {
		set_transient( self::TRANSIENT_NAME, [ $message ], self::MESSAGE_TTL_SECONDS );
	}

	public function display_validation_messages() {
		$screen = get_current_screen();
		if ( $screen && Document::TYPE_NAME !== $screen->post_type ) {
			return;
		}

		$error_messages = get_transient( self::TRANSIENT_NAME );
		if ( empty( $error_messages ) ) {
			return;
		}

		foreach ( $error_messages as $message ) {
			$html = sprintf( '<div id="plugin-message" class="error below-h2"><p>%s</p></div>', $message );
			echo wp_kses(
				$html,
				[
					'div' => [
						'id'    => true,
						'class' => true,
					],
					'p'   => true,
				]
			);
		}

		delete_transient( self::TRANSIENT_NAME );
	}
}
