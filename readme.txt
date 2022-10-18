=== WPGraphQL Smart Cache ===
Contributors: WPGraphQL, markkelnar, jasonbahl
Tags: GraphQL
Requires at least: 4.5
Tested up to: 5.6.1
Requires PHP: 5.6
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

**BREAKING CHANGE DISCLAIMER:** While this plugin is tested and used in production, as we iterate we expect breaking changes. Not so much to surface functionality (unless required), but definitely expect changes to underlying code, class/function/filter names as we continue to receive feedback and workout details..

WPGraphQL Smart Cache is a plugin that provides WPGraphQL users with cache and cache invalidation options. The plugin also provides Persisted Query support.

== Upgrade Notice ==

= 0.2.0 =

This release removes a lot of code that has since been released as part of WPGraphQL core.

In order to use v0.2.0+ of WPGraphQL Smart Cache, you will need WPGraphQL v1.12.0 or newer.

== Changelog ==


= 0.2.2 =

- fix bug with patch. Missing namespace

= 0.2.1 =

- add temporary patch for wp-engine users. Will be removed when the wp engine mu plugin is updated.


= 0.2.0

- chore: remove unreferenced .zip build artifact
- feat: remove a lot of logic from Collection.php that analyzes queries to generate cache keys and response headers, as this has been moved to core WPGraphQL
- feat: reference core WPGraphQL functions for storing cache maps for object caching
- chore: remove unused "use" statements in Invalidation.php
- feat: introduce new "graphql_purge" action, which can be hooked into by caching clients to purge caches by key
- chore: remove $collection->node_key() method and references to it.
- feat: add "purge("skipped:$type_name)" event when purge_nodes is called
- chore: remove model class prefixes from purge_nodes() calls
- chore: rename const WPGRAPHQL_LABS_PLUGIN_DIR to WPGRAPHQL_SMART_CACHE_PLUGIN_DIR
- chore: update tests to remove "node:" prefix from expected keys
- chore: update tests to use self::factory() instead of $this->tester->factory()
- chore: update Plugin docblock
- feat: add logic to ensure minimum version of WPGraphQL is active before executing functionality needed by it
- chore: remove filters that add model definitions to Types as that's been moved to WPGraphQL core

= 0.1.2 =

- Updates to support batch queries
- move save urls out of this plugin into the wpengine cache plugin
- updates to tests

= 0.1.1 =

- Initial release to beta users
