<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Document;

class DocumentTest extends \Codeception\TestCase\WPTestCase {

	public function testThrowNeedsVariableUsingWhere() {
        $this->expectException( \GraphQL\Server\RequestError::class );
        $this->expectExceptionMessage( 'Validation Error: Argument "title" value should use a variable.' );
		$document = new Document();

        $query = 'query one {
            posts(where: {title: "hello"}) {
                nodes {
                    title
                }
            }
        }';

		$document->valid_or_throw( $query, '1' );
    }

	public function testSuccessUsingVariablesWithWhere() {
		$document = new Document();
        $query = 'query one($tag: String!) {
            posts(where: {tag: $tag}) {
                nodes {
                    title
                }
            }
        }';

        // This does not throw error
		$pretty_print = $document->valid_or_throw( $query, '1' );
        $this->assertEquals("query one(\$tag: String!) {\n  posts(where: {tag: \$tag}) {\n    nodes {\n      title\n    }\n  }\n}\n", $pretty_print);
    }

	public function testThrowNeedsVariable() {
        $this->expectException( \GraphQL\Server\RequestError::class );
        $this->expectExceptionMessage( 'Validation Error: Argument "id" value should use a variable.' );
		$document = new Document();

        $query = 'query one {
            post(id: "1", idType: DATABASE_ID) {
                title
            }
        }';

		$document->valid_or_throw( $query, '1' );
    }
}
