<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Admin\Settings;

/**
 * Test the wp-graphql request to cached query is faster
 */

class AdminSettingsCacheTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		delete_option( 'graphql_cache_section' );
	}

	public function _after() {
		delete_option( 'graphql_cache_section' );
	}

	public function testCacheSettingsOff() {
		delete_option( 'graphql_cache_section' );
		$this->assertFalse( Settings::caching_enabled() );

		update_option( 'graphql_cache_section', [] );
		$this->assertFalse( Settings::caching_enabled() );

		update_option( 'graphql_cache_section', [ 'cache_toggle' => 'off' ] );
		$this->assertFalse( Settings::caching_enabled() );

		update_option( 'graphql_cache_section', [ 'cache_toggle' => false ] );
		$this->assertFalse( Settings::caching_enabled() );
	}

	public function testCacheSettingsOn() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );
		$this->assertTrue( Settings::caching_enabled() );
	}
}
