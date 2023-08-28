<?php

namespace WPGraphQL\SmartCache\ValidationRules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectFieldNode;
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
			NodeKind::OBJECT_FIELD => [
				'enter' => function ( ObjectFieldNode $node ) use ( $context ) {
					// if object field node->value->kind is 'Variable' that's good.
					// where clause objects.  posts(where: {title: "hello"}) { .... }
					if ( 'Variable' !== $node->value->kind ) {
						$context->reportError(new Error(
							self::shouldBeVariableMessage( $node->name->value ),
							$node
						));
					}
				},
			],
			NodeKind::ARGUMENT     => [
				'enter' => function ( ArgumentNode $node ) use ( $context ) {
					// Arguments in operation are processed here, ex.  post( id: "1", idType: DATABASE_ID )
					// Look for inputs; ie scalars, enums or more complex Input Object Types
					// where clause has object values 'ObjectValue' === $node->value->kind

					if ( 'ObjectValue' !== $node->value->kind && 'Variable' !== $node->value->kind ) {
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
		return sprintf( __( 'Argument "%s" value should use a variable.', 'wp-graphql-smart-cache' ), $varName );
	}
}
