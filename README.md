# WPGraphQL Smart Cache

This plugin provides support for caching and cache-invalidation of WPGraphQL Queries.

## Docs (IN PROGRESS. UPDATING NOV 4, 2022)

Below you will find documentation about the plugin.

- [Plugin Overview](#overview)
  - [Performance Issues](#performance-issues)
  - [Solving WPGraphQL Performance Problems](#solving-wpgraphql-performance-problems)
- [Quick Start](#quick-start)
- [Caching](#caching):
  - [Object Cache](#object-cache)
  - [Network Cache (recommended)](#network-cache-recommended)
  - [Cache Invalidation](#cache-invalidation)
- [Extending](#extending)
- [FAQ & Troubleshooting](#faq--troubleshooting)
- [Local Development & Contributing](./docs/development.md)

## Overview

WPGraphQL has essentially become the standard when it comes to building headless WordPress experiences.

The flexibility and tooling around GraphQL is attractive, but it can come at a cost.

### Performance Issues

Chances are, if you're reading this, you've likely experienced some performance issues with WPGraphQL.

While WPGraphQL is optimized for run-time performance by using [DataLoader](https://www.wpgraphql.com/docs/wpgraphql-vs-wp-rest-api#dataloader-and-the-n1-problem) methods and other techniques to reduce run time execution cost, the fact is that WPGraphQL is built on top of WordPress and respects the WordPress application layer – including actions, filters, authentication rules, and the WordPress database structure.

This means that anytime a WPGraphQL request is executed against WordPress (much like any type of request made to WordPress) there is a cost, and the cost can eventually be problematic.

### Solving WPGraphQL Performance Problems

One of the fastest ways to load WordPress, is to prevent WordPress from being loaded at all!

This is no different for WPGraphQL.

And this is where WPGraphQL Smart Cache comes in.

WPGraphQL Smart Cache has integrations with a multiple layers of caching to provide users of WPGraphQL with both fast and accurate data managed in WordPress.

The over-simplification of WPGraphQL Smart Cache, is to capture the results of GraphQL Requests, cache the response, and use the cached response for future requests instead of executing the query and all of its resolvers at each request.


## Caching

Below we will discuss different methods of caching, how they each play into the bigger picture with WPGraphQL and how WPGraphQL Smart Cache works with them.

### Network Cache (recommended)

For many years, managed WordPress hosts have sped up WordPress by using network-level page caching and serving WordPress pages to users from a cache, preventing the WordPress application itself from being executed to build the page for most requests.

For example, when you host your WordPress website with [WP Engine](https://wpengine.com/atlas), when a visitor visits your website, the first visitor will execute WordPress to build the page, then the page is stored in Varnish cache and subsequent users are served the cached page and WordPress itself is not loaded for those users.

WPGraphQL Smart Cache was built to work in environments, such as WP Engine, that have a network caching layer that caches requests via URL.

Below, we’ll look at how WPGraphQL Smart Cache works with network caching layers.

Because the network cache & cache invalidation strategy requires specific implementation by the host, network caching + invalidation will currently only work on [supported hosts](#supported-hosts).

### POST Requests

If you’ve been using GraphQL for some time, there’s a good chance you use GraphQL via HTTP POST requests.

For most WordPress hosts, the network cache layer will not be in effect when using HTTP POST requests. That means HTTP POST requests to the WPGraphQL endpoint will bypass the network cache layer and will still be sent to WordPress for execution.

See the [GraphQL Object Cache](#object-cache) section below for more information on how to enable object caching for POST requests.

### GET Requests

To benefit the most from WPGraphQL Smart Cache, you will want to use HTTP GET requests when fetching public data, and you will need to be on a [supported WordPress host](#supported-hosts) such as [WP Engine](https://wpengine.com/atlas).

When making HTTP GET requests to the WPGraphQL Endpoint, the first request for a particular query will be a Cache Miss in the network cache and will be passed to WordPress for execution.

The response will be cached and subsequent requests for the same query (from any client) will be served from teh cache and the WordPress server won't be loaded or tasked with executing.

This significantly reduces the load on your WordPress server, and significantly increases the speed of the API responses.

#### GET Requests In Action

Visit the following URL in your browser: [https://content.wpgraphql.com/graphql?query={posts{nodes{id,title}}}](https://content.wpgraphql.com/graphql?query={posts{nodes{id,title}}})

If you inspect the "Network" tab in your browser's developer tools, you should see either an `X-Cache: MISS` header or a `X-Cache: Hit #` header, showing the number of times this query has been served from cache.

**X-Cache: MISS**

Below is a screenshot of a Cache Miss. Here, we see the `x-cache: MISS` header.

This means the request was passed through the network cache to WordPress for execution.

The total response time for the cache miss was ~520ms.

![Screenshot showing a cache miss in the network panel](./docs/images/x-cache-miss.png)

**X-Cache: HIT 26**

After refreshing the browser 26 times, I now see the header `x-cache: HIT 26`.

This means that the request was served from the network cache (in this case Varnish) and was not passed through to WordPress for execution.

This means that the WordPress server didn't experience any load from the 26 refreshes.

Your results may vary, but for me, the total response time for the cache hit was ~110ms, nearly 5x faster than the cache miss!

_Ideally, we'll be able to work with hosts to serve these cache hits even faster!_

![Screenshot showing a cache hit in the network panel](./docs/images/x-cache-hit.png)

#### GET Query String Limitations?

One concern you might have with making GraphQL requests via HTTP GET is limitations around Query String Length.

GraphQL Queries can get quite long, so it's easy to push up against [max query string length limitations](https://stackoverflow.com/questions/812925/what-is-the-maximum-possible-length-of-a-query-string#answer-812962).

This takes us to the topic of "Persisted Queries", a feature bundled with the WPGraphQL Smart Cache plugin.

## Persisted Queries

In the GraphQL ecosystem, the term “Persisted Queries” refers to the idea that a GraphQL Query Document can be “persisted” on the server, and can be referenced via a Query ID.

There are several advantages to using Persisted Queries:

- Avoid query string limits
- Reduced upload cost per request (uploading large GraphQL query documents for each request has a cost)
- Ability to mark specific queries as allow/deny
- Ability to customize the cache-expiration time per query
- Future customizations at the individual query level

WP GraphQL Smart Cache provides support for "Persisted Queries" and plays nice with the [Apollo Persisted Query Link](https://www.apollographql.com/docs/react/api/link/persisted-queries/).

_**NOTE:** You can see the [implementation](https://github.com/wp-graphql/wpgraphql.com/blob/bab1429c0f25ba93ddd3dfba2e6998eec67b331a/src/plugins/PersistedQueriesPlugin.js) of the Apollo Persisted Queries link on WPGraphQL.com, a headless WordPress front-end powered by [NextJS](https://nextjs.org) and [FaustWP](https://www.npmjs.com/package/@faustwp/core)._

When WPGraphQL Smart Cache is active, there’s a non-public post type registered named “graphql_document”. This post type is used to “persist” query documents, associate them with unique IDs and maintain rules about the queries.

### Persisted Queries Admin UI

The Persisted Queries functionality of this plugin uses a private  “graphql_document” post_type. Since it’s a Post Type, we can make use of WordPress core ui’s.

**NOTE**: In the future we plan to have UIs more tightly integrated with the GraphiQL IDE, but for now, you can use the core WordPress UIs to interact with the saved GraphQL Documents.

There’s a setting under "GraphQL > Settings > Saved Queries" that lets you display the admin ui for saved queries.

![Screenshot showing how to show the Saved Queries UI](./docs/images/show-saved-queries.png)

With the UI set to be displayed, you will see a "GraphQL Documents" menu item.

![Screenshot showing the GraphQL Documents Post Type](./docs/images/graphql-documents-post-type.png)

From here, you can use the standard WordPress Admin User Interfaces to Create, Update and Delete GraphQL Documents.

Each document has the following editable attributes:

- **title**: A human readable reference to the document. Automated Persisted Queries will derive the title from the document name(s) in the document, but the title can be updated to something you prefer for your own reference.
- **document**: The query string document
- **description**: A description of the document, for reference. For example, you might have been testing a document for a staging site and want to leave yourself a note here.
- **alias names**: Alias Names can be used to query a document by queryId.
- **allow/deny**: Depending on the rules set at "GraphQL > Settings > Saved Documents", you can control whether a specific query should be allowed to execute (when the endpoint is otherwise locked down), or if a specific query should be denied execution, even though the endpoint is publicly queryable.
- **max-age header:** You can set a Cache expiration for this query, in seconds. This means that if no event occurs to purge the query will automatically purge when it hits this length of time. This can be useful for queries for data (such as custom plugin data) that might not be tracked in the Invalidation events.


@TODO CONTINUE THIS SECTION, AND BELOW


### Object Cache

GraphQL Object Cache is a layer of the WPGraphQL Smart Cache plugin that, when enabled, will cache GraphQL responses in the object cache.

If your WordPress environment supports a persistent object cache (i.e. Redis, Memcached, etc) WPGraphQL Smart Cache will store responses to GraphQL queries in the object cache, and for future requests of the same query results will be returned from the cache instead of re-executing all the resolvers each time the request is made.

#### Enabling WPGraphQL Smart Cache Object Caching

**NOTE:** Enabling this is only recommended if want some benefits of caching, but are not able to take advantage of [Network Caching](#network-cache-recommended).

With the WPGraphQL Smart Cache plugin active, a new "Cache" tab is added to the WPGraphQL Settings page.

![Screenshot of the WPGraphQL Smart Cache "Cache" Settings](./docs/images/object-cache-enable.png)

From this settings page, clicking "Use Object Cache" will enable object caching.

In addition to enabling Object Caching, you can:

- set an expiration (in seconds) for caches to auto expire
- purge all object caches
- see the last time caches were purged

Setting the expiration, and purging caches will only take effect if Object Caching is enabled.

## Quick Start

## Caching

### Network Cache

### Object Cache

### Cache Invalidation

## Extending

## FAQ & Troubleshooting

## Supported Hosts

- [WP Engine](https://wpengine.com/atlas) WPEngine's "EverCache for WPGraphQL" is the first formal hosting integration to support WPGraphQL Smart Cache.

If you are hosting provider or manage your own servers and want to add support for WPGraphQL Smart Cache, see our [hosting guide](#hosting-guide)

## Hosting Guide

In order for a WordPress host to play nice with WPGraphQL Smart Cache, it needs to be able to cache the response of WPGraphQL requsts, use the X-GraphQL-Keys header to tag the cached response, and be able to purge the cache(s) associated with a tag when a the Smart Cache "purge" event is triggered.

### Caching & Tagging

@todo

### Purging

@todo
