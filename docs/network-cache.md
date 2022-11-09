# Network Cache

- [Quick Start](#quick-start)
- [Supported Hosts](#supported-hosts)
- [How WPGraphQL Network Cache works?](#how-wpgraphql-network-cache-works)
- [Settings](#settings)
- [Purging Caches](#purging-caches)
- [Troubleshooting](#troubleshooting)
- [Hosting Guide](#hosting-guide)

----

## 🚀 Quick Start

This is the Quick Start guide to show the benefits of WPGraphQL Network Cache & Cache Invalidation.

**NOTE:**

Network Cache requires your WordPress instance to be on a [supported host](#supported-hosts).

As more hosts work with us to support this feature, some nuances (for example header responses) in this guide might be different from host to host.

To follow this quick start guide, we recommend having a WordPress install on WP Engine. You can [sign up for a FREE WP Engine Atlas sandbox account](https://wpengine.com/atlas) to follow this guide.

### 👩‍💻 Execute a GraphQL Query (as an HTTP GET request)

Using a browser that is not authenticated to your WordPress site (or an incognito window), execute a GraphQL query as an HTTP GET request by visiting a url for a GraphQL query in the browser, like so (replacing the domain with your domain): `https://${yourdomain.com}/graphql?query={posts{nodes{id,title,uri}}}`

### 🧐 Inspect the Headers

Inspect the headers using your browser developer tools Network tab.

See an `x-cache` header with either a `MISS` value or a `Hit: #` value. The hit is the number of times the cached response has been served. The cache hits are returned from the network cache layer, instead of executing in WordPress.

Refresh the browser a few times and see the `x-cache: Hit: #` count increase.

### 📝 Edit Some Content

In another browser (or incognito window), login to your WordPress dashboard.

Edit the title of your most recent post (add a period to the title, for example) and click update.

### 🥳 Verify the Cache was Purged

Refresh the browser tab with the query and inspect the headers and the JSON response.

The response should have your updated title, and the `x-cache` header should be a `MISS`.

You just witnessed the power of WPGraphQL Smart Cache!

GraphQL responses are served from a cache and WPGraphQL Smart Cache tracks events (publishing, updating, deleting content, etc) and purges appropriate cache(s) in response to said events!

### 💸 Profit

To learn more about the plugin, continue reading the docs below.

----

----

## Supported Hosts



----

## How WPGraphQL Network Cache works

- entire queries are cached by the Host's network cache layer (varnish, etc)
- cached documents are tagged with the values of the x-graphql-keys header
- future requests will be served from the cache, preventing WordPress from loading
- listen to events and call purge( $key_name )
- documents tagged with the key name being purged will be evicted from the network cache
- next request will be a cache miss

For many years, managed WordPress hosts have sped up WordPress by using network-level page caching and serving WordPress pages to users from a cache, preventing the WordPress application itself from being executed to build the page for most requests.

For example, when you host your WordPress website with [WP Engine](https://wpengine.com/atlas), when a visitor visits your website, the first visitor will execute WordPress to build the page, then the page is stored in Varnish cache and subsequent users are served the cached page and WordPress itself is not loaded for those users.

WPGraphQL Smart Cache was built to work in environments that have a network caching layer that caches requests via URL.

Hosts might have to make changes to their environment to fully work with WPGraphQL Smart Cache. We have a hosting guide

Below, we’ll look at how WPGraphQL Smart Cache works with network caching layers.

Because the network cache & cache invalidation strategy requires specific implementation by the host, network caching + invalidation will currently only work on [supported hosts](#supported-hosts).

## GET Requests

To benefit the most from WPGraphQL Smart Cache, you will want to use HTTP GET requests when fetching public data, and you will need to be on a [supported WordPress host](#supported-hosts) such as [WP Engine](https://wpengine.com/atlas).

When making HTTP GET requests to the WPGraphQL Endpoint, the first request for a particular query will be a Cache Miss in the network cache and will be passed to WordPress for execution.

The response will be cached and subsequent requests for the same query (from any client) will be served from teh cache and the WordPress server won't be loaded or tasked with executing.

This significantly reduces the load on your WordPress server, and significantly increases the speed of the API responses.

### GET Requests In Action

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

### GET Query String Limitations?

One concern you might have with making GraphQL requests via HTTP GET is limitations around Query String Length.

GraphQL Queries can get quite long, so it's easy to push up against [max query string length limitations](https://stackoverflow.com/questions/812925/what-is-the-maximum-possible-length-of-a-query-string#answer-812962).

This takes us to the topic of "Persisted Queries", a feature bundled with the WPGraphQL Smart Cache plugin.


----

## Settings

- max age

----

## Purging Caches

----

## Troubleshooting

- how to identify if the request is served from network cache (inspect headers)
- customizing the headers (limiting when to tag/invalidate)
- skipped types headers (big queries with a lot of nodes)


----

## Hosting Guide

This guide is intended to help WordPress hosts support caching and cache invalidation of WPGraphQL Queries at the network cache level.

Each host will likely have its own stack and opinionated implementations so this guide won't prescribe specific implementation details, but will explain concepts that hosts can use to apply to their hosting environment.

In this guide we'll cover the following topics to have your hosting environment work well with WPGraphQL Smart Cache:

- [Caching WPGraphQL Responses](#caching-responses)
- [Purging Documents](#purging-caches)

### Caching Responses

When GraphQL queries are executed over HTTP GET, the network cache layer should cache the document.

The cache should include query parameters, such as: `query`, `variables` and `operationName`.

Making the same query multiple times should return a cached response until an event purges the cache, or the cache expires based on the Max Age header.

### Tagging Cached Documents

The cached document should be "tagged" using the values returned by the `x-graphql-keys` header.

### Purging Caches

WPGraphQL Smart Cache tracks events and sends out "purge" actions.

The network cache layer should respond to calls of the purge action and purge any cached document(s) tagged with the key being purged.

