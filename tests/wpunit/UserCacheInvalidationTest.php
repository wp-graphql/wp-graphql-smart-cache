<?php
namespace WPGraphQL\Labs;

use WPGraphQL\Labs\Cache\Collection;

class UserCacheInvalidationTest extends \TestCase\WPGraphQLLabs\TestCase\WPGraphQLLabsTestCaseWithSeedDataAndPopulatedCaches {

    public $collection;

    public function setUp(): void {
        \WPGraphQL::clear_schema();

        if ( ! defined( 'GRAPHQL_DEBUG' ) ) {
            define( 'GRAPHQL_DEBUG', true );
        }

        $this->collection = new Collection();

        // enable caching for the whole test suite
        add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

        parent::setUp();
    }

    public function tearDown(): void {
        \WPGraphQL::clear_schema();
        // disable caching
        delete_option( 'graphql_cache_section' );
        parent::tearDown();
    }

    public function testItWorks() {
        $this->assertTrue( true );
    }

    // create user (no purge, not public yet)
    public function testCreateUserDoesNotInvalidateUserCache() {
        // all queries should be in the cache, non should be empty
        $this->assertEmpty( $this->getEvictedCaches() );

        // Create a user for this test
        self::factory()->user->create( [
            'role' => 'editor',
            'first_name' => 'foo',
            'last_name' => 'bar',
        ] );

        // there should be no caches that were emptied
        $this->assertEmpty( $this->getEvictedCaches() );
    }

    // delete user with no published posts (no purge)
    public function testDeleteUserWithNoPostsDoesNotInvalidateUserCache() {
        // Create a user for this test
        $user_id = self::factory()->user->create( [
            'role' => 'editor',
            'first_name' => 'foo',
            'last_name' => 'bar',
        ] );

        wp_delete_user( $user_id );

        // there should be no caches that were emptied
        $this->assertEmpty( $this->getEvictedCaches() );
    }

    // delete user without re-assign (what should happen here?)
    // - call purge for each post the author was the author of?
    public function testDeleteUserAndPosts() {
        wp_delete_user( $this->editor->ID );

        // caches that were emptied because the user and it's created posts were delete
        $evicted = $this->getEvictedCaches();
        $this->assertContains( 'listPost', $evicted );
        $this->assertContains( 'listContentNode', $evicted );
        $this->assertContains( 'editorUserWithPostsConnection', $evicted );
    }

    // delete user and re-assign posts
    // - purge user
    // - purge for each post (of each post type) transferred
    // - purge for the new author being assigned
    public function testDeleteUserAndReassignPosts() {
        $user_id = self::factory()->user->create( [
            'role' => 'editor',
            'first_name' => 'foo',
            'last_name' => 'bar',
        ] );

        wp_delete_user( $this->editor->ID, $user_id );

        codecept_debug( $this->getEvictedCaches() );

        // caches that were emptied because the user was deleted and posts reassigned
        $evicted = $this->getEvictedCaches();
        $this->assertContains( 'listPost', $evicted );
        $this->assertContains( 'listContentNode', $evicted );
        $this->assertContains( 'editorUserWithPostsConnection', $evicted );
    }

    // update user that has published posts
    public function testUpdateUserNameAndPurgeCache() {
        $fields['first_name'] = 'biz';

        self::factory()->user->update_object( $this->editor->ID, $fields );

        codecept_debug( $this->getEvictedCaches() );

        // caches that were emptied because the user was deleted and posts reassigned
        $evicted = $this->getEvictedCaches();
        $this->assertContains( 'listPost', $evicted );
        $this->assertContains( 'listContentNode', $evicted );
        $this->assertContains( 'editorUserWithPostsConnection', $evicted );
    }

    // update user meta (with allowed meta key)
    public function testUpdateAllowedUserMetaAndPurgeCache() {
        $updated = update_user_meta( $this->editor->ID, 'foo_data', 'bar-biz-bang' );
        $evicted = $this->getEvictedCaches();
        $this->assertContains( 'editorUserWithPostsConnection', $evicted );
    }

    // update user meta (with non-allowed meta key)
    public function testUpdateNonAllowedUserMetaAndPurgeCache() {
        $updated = update_user_meta( $this->editor->ID, 'user_email', 'foo@example.com' );
        $evicted = $this->getEvictedCaches();
        $this->assertContains( 'editorUserWithPostsConnection', $evicted );
    }

    // delete user meta (with allowed meta key)
    public function testDeleteAllowedUserMetaAndPurgeCache() {
        $updated = delete_user_meta( $this->editor->ID, 'foo_data' );
        $evicted = $this->getEvictedCaches();
        $this->assertContains( 'editorUserWithPostsConnection', $evicted );
    }

    // delete user meta (with non-allowed meta key)
    public function testDeleteNonAllowedUserMetaAndPurgeCache() {
        $updated = delete_user_meta( $this->editor->ID, 'user_email' );
        $evicted = $this->getEvictedCaches();
        $this->assertContains( 'editorUserWithPostsConnection', $evicted );
    }

}
