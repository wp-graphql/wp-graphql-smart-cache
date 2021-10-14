<?php

namespace WPGraphQL\PersistedQueries\ValidationRules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;

use WPGraphQL\PersistedQueries\SavedQuery;
use WPGraphQL\PersistedQueries\SavedQueryGrant;
use WPGraphQL\PersistedQueries\Utils;

/**
 * Class AllowOrDenyQuery
 *
 * @package WPGraphQL\PersistedQueries\Rules
 */
class AllowDenyQueryDocument extends ValidationRule {

	/**
	 * @var string
	 */
	private $access_setting;

	/**
	 * AllowDenyQueryDocument constructor.
	 */
	public function __construct( $setting ) {
        $this->access_setting = $setting;
	}

    public function getVisitor( ValidationContext $context ) {
        return [
            NodeKind::DOCUMENT => function ( DocumentNode $node ) use ( $context ) : void {
                // We are here because the global graphql settin is not public. Meaning allow or deny
                // certain queries.

                // Check is the query document is persisted
                // Get post using the normalized hash of the query string
                $hash = Utils::generateHash( $context->getDocument() );

                // Look up the persisted query
                $post = Utils::getPostByTermId( $hash, SavedQuery::TYPE_NAME, SavedQuery::TAXONOMY_NAME );

                // If set to allow only specific queries, must be explicitely allowed.
                // If set to deny some queries, only deny if persisted and explicitely denied.
                if ( 'some_denied' === $this->access_setting ) {
                    // If this query is not persisted do not deny.
                    if ( ! $post ) {
                        return;
                    }

                    // When the allow/deny setting denies some queries, see if this query is denied
                    if ( SavedQueryGrant::DENY === $this->getQueryGrantSetting( $post->ID ) ) {
                        $context->reportError( new Error(
                            self::deniedDocumentMessage(),
                            [$node->type]
                        ) );
                    }
                } elseif ( 'only_allowed' === $this->access_setting ) {
                    // When the allow/deny setting only allows certain queries, verify this query is allowed

                    // If this query is not persisted do not deny.
                    if ( ! $post || SavedQueryGrant::ALLOW !== $this->getQueryGrantSetting( $post->ID ) ) {
                        $context->reportError( new Error(
                            sprintf( __( 'Blocked. Query Not Found %s', 'wp-graphql-persisted-queries' ), $hash ),
                            [$node->type]
                        ) );
                    }
                }
            },
        ];
    }

    public function getQueryGrantSetting( $post_id ) {
        $item = wp_get_object_terms( $post_id, SavedQueryGrant::TAXONOMY_NAME );
        return $item[0]->name;
    }

    public static function deniedDocumentMessage() {
        return __( 'This persisted query document has been blocked', 'wp-graphql-persisted-queries' );
    }

}
