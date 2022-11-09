# GraphQL Query Object Caching

- How it works?
  - entire queries are cached
  - stored in persistent object cache (or transients)
  - events call purge
  - object cache for related queries are evicted
- Settings
  - enable/disable
  - purge all caches
  - max age
- POST Requests
- GET Requests
- Troubleshooting
  - how to identify if the request is served from object cache or not?
  - extensions payload, etc


## Object Cache (with HTTP POST requests)

_**NOTE:** When possible, we recommend taking advantage of [Network Caching and HTTP GET requests](#network-cache)_

If you’ve been using GraphQL for some time, there’s a good chance you use GraphQL via HTTP POST requests. It's the default HTTP method for many GraphQL APIs.

For most WordPress hosts, the [network cache](#network-cache) layer will **_NOT_** be in effect when using HTTP POST requests. That means HTTP POST requests to the WPGraphQL endpoint will bypass the network cache layer and will still be sent to WordPress for execution.

That means, if you want to take advantage of caching functionality for POST requests, you can use WPGraphQL Smart Cache's Object Cache functionality.

GraphQL Object Caching is a feature of the WPGraphQL Smart Cache plugin that, when enabled, will cache GraphQL responses in the object cache.

If your WordPress environment supports a persistent object cache (i.e. Redis, Memcached, etc) WPGraphQL Smart Cache will store responses to GraphQL queries in the object cache, and for future requests of the same query results will be returned from the cache instead of re-executing all the resolvers each time the request is made.

For WordPress environments that do not support persistent object cache, [transients](https://developer.wordpress.org/apis/handbook/transients/) will be used.

### Enabling WPGraphQL Smart Cache Object Caching

**NOTE:** Enabling this is only recommended if want some benefits of caching, but are not able to take advantage of [Network Caching](#network-cache-recommended).

With the WPGraphQL Smart Cache plugin active, a new "Cache" tab is added to the WPGraphQL Settings page.

![Screenshot of the WPGraphQL Smart Cache "Cache" Settings](./docs/images/object-cache-enable.png)

From this settings page, clicking "Use Object Cache" will enable object caching.

In addition to enabling Object Caching, you can:

- set an expiration (in seconds) for caches to auto expire
- purge all object caches
- see the last time caches were purged

Setting the expiration, and purging caches will only take effect if Object Caching is enabled.

## 👉 Up Next:

- [Network Cache](./docs/network-cache.md)
- [Persisted Queries](./docs/persisted-queries.md)
- [Cache Invalidation](./docs/cache-invalidation.md)
