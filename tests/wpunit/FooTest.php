<?php

class FooTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Tests the access function that is available to format strings to GraphQL friendly format
	 */
	public function test_boolean() {

		$actual   = true;
		$expected = 'thisIsSomeFieldName';

		$this->assertEquals( $expected, $actual );

	}

}
