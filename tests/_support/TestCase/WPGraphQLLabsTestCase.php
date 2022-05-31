<?php
namespace TestCase\WPGraphQLLabs\TestCase;

use Tests\WPGraphQL\TestCase\WPGraphQLTestCase;
use WP_Comment;
use WP_Post;
use WP_Term;
use WP_User;
use WPGraphQL\Labs\Cache\Collection;

class WPGraphQLLabsTestCase extends WPGraphQLTestCase {

	/**
	 * @var WP_User
	 */
	public $admin;

	/**
	 * @var Collection
	 */
	public $collection;

	/**
	 * @var WP_Post
	 */
	public $published_post;

	/**
	 * @var WP_Post
	 */
	public $draft_post;

	/**
	 * @var WP_Post
	 */
	public $published_page;

	/**
	 * @var WP_Term
	 */
	public $category;

	/**
	 * @var WP_Post
	 */
	public $mediaItem;

	/**
	 * @var WP_Comment
	 */
	public $comment;

	/**
	 * @var WP_Term
	 */
	public $tag;

	public function setUp(): void {
		parent::setUp();
		// enable caching for the whole test suite
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		register_post_type( 'test_post_type', [
			'public' => true,
			'show_in_graphql' => true,
			'graphql_single_name' => 'TestPostType',
			'graphql_plural_name' => 'TestPostTypes'
		] );

		register_taxonomy( 'test_taxonomy', [ 'test_post_type' ], [
			'public' => true,
			'show_in_graphql' => true,
			'graphql_single_name' => 'TestTaxonomyTerm',
			'graphql_plural_name' => 'TestTaxonomyTerms'
		] );

		register_post_type( 'private_post_type', [
			'public' => true,
			'show_in_graphql' => true,
			'graphql_single_name' => 'PrivatePostType',
			'graphql_plural_name' => 'PrivatePostTypes'
		] );

		register_taxonomy( 'private_taxonomy', [ 'private_post_type' ], [
			'public' => true,
			'show_in_graphql' => true,
			'graphql_single_name' => 'PrivateTaxonomyTerm',
			'graphql_plural_name' => 'PrivateTaxonomyTerms'
		] );

		$this->_createSeedData();
		$this->_populateCaches();
		$this->clearSchema();
	}

	public function tearDown(): void {

		// disable caching
		delete_option( 'graphql_cache_section' );

		parent::tearDown();
	}

	public function _createSeedData() {

		// setup access to the Cache Collection class
		$this->collection = new Collection();

		// create an admin user
		$this->admin = self::factory()->user->create_and_get([
			'role' => 'administrator'
		]);

		// create a test tag
		$this->tag = self::factory()->term->create_and_get([
			'taxonomy' => 'post_tag',
			'term' => 'Test Tag'
		]);

		// create a test category
		$this->category = self::factory()->term->create_and_get([
			'taxonomy' => 'category',
			'term' => 'Test Category'
		]);

		// create a published post
		$this->published_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'publish',
			'tax_input' => [
				'post_tag' => [ $this->tag->term_id ],
				'category' => [ $this->category->term_id ]
			],
			'post_author' => $this->admin->ID,
		]);

		// create a draft post
		$this->draft_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'draft',
			'tax_input' => [
				'post_tag' => [ $this->tag->term_id ],
				'category' => [ $this->category->term_id ]
			],
			'post_author' => $this->admin->ID,
		]);

		// create a published page
		$this->published_page = self::factory()->post->create_and_get([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_author' => $this->admin->ID,
		]);
	}


	public function _populateCaches() {

		// purge all caches to clean up
		$this->collection->purge_all();

		$this->assertEmpty( $this->collection->get( 'post' ) );
		$this->assertEmpty( $this->collection->get( 'term' ) );
		$this->assertEmpty( $this->collection->get( 'user' ) );
		$this->assertEmpty( $this->collection->get( 'comment' ) );
		$this->assertEmpty( $this->collection->get( 'nav_menu' ) );
		$this->assertEmpty( $this->collection->get( 'menu_item' ) );
		$this->assertEmpty( $this->collection->get( 'list:post' ) );
		$this->assertEmpty( $this->collection->get( 'list:term' ) );
		$this->assertEmpty( $this->collection->get( 'list:user' ) );
		$this->assertEmpty( $this->collection->get( 'list:comment' ) );
		$this->assertEmpty( $this->collection->get( 'list:nav_menu' ) );
		$this->assertEmpty( $this->collection->get( 'list:menu_item' ) );


		$query = $this->getListPostQuery();
		$cache_key = $this->collection->build_key( null, $query );

		// The cache key should not be present in the cache
		$this->assertEmpty( $this->collection->get( $cache_key ) );

		// execute the graphql query
		$actual = $this->graphql([
			'query' => $query
		]);

		// assert that the query was successful
		$this->assertQuerySuccessful( $actual, [
			$this->expectedObject( 'posts.nodes', [
				'__typename' => 'Post',
				'databaseId' => $this->published_post->ID
			])
		] );

		//
		codecept_debug( [
			'results' => $actual,
			'cacheKey' => $cache_key,
			'listPost' => $this->collection->get( 'list:post' ),
		]);

		$this->assertSame( $actual, $this->collection->get( $cache_key ) );
		$this->assertNotEmpty( $this->collection->get( 'list:post' ) );

	}

	// ensure the seed data has been created as expected
	public function testSeedDataCreated() {
		$this->assertInstanceOf( \WP_User::class, $this->admin );
		$this->assertInstanceOf( \WP_Post::class, $this->published_post );
		$this->assertInstanceOf( \WP_Post::class, $this->draft_post );
		$this->assertInstanceOf( \WP_Post::class, $this->published_page );
		$this->assertInstanceOf( \WP_Term::class, $this->category );
		$this->assertInstanceOf( \WP_Term::class, $this->tag );
	}

	/**
	 * @return string
	 */
	public function getSinglePostByDatabaseIdQuery() {
		return '
		query GetPost($id:ID!) {
		  post(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListPostQuery() {
		return '
		query GetPosts {
		  posts {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSinglePageByDatabaseIdQuery() {
		return '
		query GetPage($id:ID!) {
		  page(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListPageQuery() {
		return '
		query GetPages {
		  pages {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleTestPostTypeByDatabaseIdQuery() {
		return '
		query GetTestPostType($id:ID!) {
		  testPostType(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListTestPostTypeQuery() {
		return '
		query GetTestPostTypes {
		  testPostTypes {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSinglePrivatePostTypeByDatabaseIdQuery() {
		return '
		query GetPrivatePostType($id:ID!) {
		  privatePostType(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListPrivatePostTypeQuery() {
		return '
		query GetPrivatePostTypes {
		  privatePostTypes {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListContentNodeQuery() {
		return '
		query GetContentNodes {
		  contentNodes {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleContentNodeByDatabaseId() {
		return '
		query GetContentNode($id:ID!) {
		  contentNode(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleNodeByUriQuery() {
		return '
		query GetNodeByUri($uri: String!) {
		  nodeByUri(uri: $uri) {
		    __typename
		    id
		    ... on DatabaseIdentifier {
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleNodeByIdQuery() {
		return '
		query GetNode($id: ID!) {
		  nodeByUri(id: $id) {
		    __typename
		    id
		    ... on DatabaseIdentifier {
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListTagQuery() {
		return '
		query GetTags {
		  tags {
		    nodes {
		      __typename
		      databaseId
		      name
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleTagByDatabaseIdQuery() {
		return '
		query GetTag($id:ID!) {
		  tag(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListCategoryQuery() {
		return '
		query GetCategories {
		  categories {
		    nodes {
		      __typename
		      databaseId
		      name
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleCategoryByDatabaseIdQuery() {
		return '
		query GetCategory($id:ID!) {
		  category(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListTestTaxonomyTermsQuery() {
		return '
		query GetTestTaxonomyTerms {
		  testTaxonomyTerms {
		    nodes {
		      __typename
		      databaseId
		      name
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleTestTaxonomyTermByDatabaseIdQuery() {
		return '
		query GetTestTaxonomyTerm($id:ID!) {
		  testTaxonomyTerm(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListPrivateTaxonomyTermsQuery() {
		return '
		query GetPrivateTaxonomyTerms {
		  privateTaxonomyTerms {
		    nodes {
		      __typename
		      databaseId
		      name
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSinglePrivateTaxonomyTermByDatabaseIdQuery() {
		return '
		query GetPrivateTaxonomyTerm($id:ID!) {
		  privateTaxonomyTerm(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListMenusQuery() {
		return '
		query GetMenus {
		  menus {
		    nodes {
		      __typename
		      id
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleMenuByDatabaseIdQuery() {
		return '
		query GetMenu($id:ID!) {
		  menu(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListMenuItemsQuery() {
		return '
		query GetMenuItems {
		  menuItems {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleMenuItemByDatabaseIdQuery() {
		return '
		query GetMenuItem($id:ID!) {
		  menuItem(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getUsersQuery() {
		return '
		query GetUsers {
		  users {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleUserByDatabaseIdQuery() {
		return '
		query GetUser($id:ID!) {
		  user(id:$id idType:DATABASE_ID) {
	        __typename
	        databaseId
		  }
		}
		';
	}

	/**
	 * Returns a query for a user by database ID, as well
	 * as the users connected posts
	 *
	 * @return string
	 */
	public function getSingleUserByDatabaseIdWithAuthoredPostsQuery() {

		return '
		query GetUser($id:ID!) {
		  user(id:$id idType:DATABASE_ID) {
	        __typename
	        databaseId
	        posts {
	          nodes {
	            __typename
	            id
	          }
	        }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListCommentsQuery() {
		return '
		query GetComments {
		  comments {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @todo: As of writing this, the comment query doesn't allow
	 *      fetching a single comment by database id
	 *
	 * @return string
	 */
	public function getSingleCommentByGlobalIdQuery() {
		return '
		query GetComment($id:ID!) {
		  comment(id:$id) {
	        __typename
	        databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getGeneralSettingsQuery() {
		return '
		query GetGeneralSettings {
		  generalSettings {
		    dateFormat
		    description
		    email
		    language
		    startOfWeek
		    timeFormat
		    timezone
		    title
		    url
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getReadingSettingsQuery() {
		return '
		query GetReadingSettings {
		  readingSettings {
		    postsPerPage
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getWritingSettingsQuery() {
		return '
		query GetWritingSettings {
		  writingSettings {
		    defaultCategory
		    defaultPostFormat
		    useSmilies
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getDiscussionSettingsQuery() {
		return '
		query GetDiscussionSettings {
		  defaultCommentStatus
		  defaultPingStatus
		}
		';
	}

	/**
	 * @return string
	 */
	public function getAllSettingsQuery() {
		return '
		query GetAllSettings {
		  allSettings {
		    discussionSettingsDefaultCommentStatus
		    discussionSettingsDefaultPingStatus
		    generalSettingsDateFormat
		    generalSettingsDescription
		    generalSettingsEmail
		    generalSettingsLanguage
		    generalSettingsStartOfWeek
		    generalSettingsTimeFormat
		    readingSettingsPostsPerPage
		    writingSettingsDefaultCategory
		    writingSettingsDefaultPostFormat
		    writingSettingsUseSmilies
		  }
		}
		';
	}

}
