<?php

namespace WPGraphQL\SmartCache\ValidationRules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;

/**
 * Class ArgShouldBeVariable
 *
 * @package WPGraphQL\SmartCache\Rules
 */
class ArgShouldBeVariable extends ValidationRule {

	/**
	 * Returns structure suitable for GraphQL\Language\Visitor
	 *
	 * @see \GraphQL\Language\Visitor
	 *
	 * @return mixed[]
	 */
	public function getVisitor( ValidationContext $context ) {

		return [
			NodeKind::ARGUMENT => [
				'enter' => function ( ArgumentNode $node ) use ( $context ) {
					// Arguments in operation are process here, ex.  post( id: "1", idType: DATABASE_ID )

					// If this argument should map to a variable
					if ( 'StringValue' === $node->value->kind ) {
						$context->reportError(new Error(
							self::shouldBeVariableMessage( $node->name->value ),
							$node
						));
					}
				},
			],
		];
	}

	/**
	 * @param string $varName
	 * @return string
	 */
	public static function shouldBeVariableMessage( $varName ) {
		return sprintf( __( 'Argument "$%s" should be a variable.', 'wp-graphql-smart-cache' ), $varName );
	}
}
