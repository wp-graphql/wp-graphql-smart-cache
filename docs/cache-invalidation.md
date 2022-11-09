# Cache Invalidation


## GraphQL Cache Invalidation

One of the primary features of the WPGraphQL Smart Cache plugin is the cache invalidation.

Unlike RESTful APIs which are 1 enpdoint per resource type, GraphQL Queries can be constructed in nearly infinite ways, and can contain resources of many types.

Thus, caching and invalidating caches can be tricky.

This is where WPGraphQL Smart Cache really shines.

WPGraphQL Smart Cache listens for events in WordPress, and purges caches for relevant queries in response to said events.

### Invalidation Strategy

When a GraphQL request is executed against the WPGraphQL endpoint, the query is analyzed to determine:

- The operation name of the query
- The ID of the query (a hash of the query document string)
- What types of nodes were asked for as a list
- The individual nodes resolved by the query

The results of this query analysis are returned in the `X-GraphQL-Keys` header.


## Invalidation Strategy

- **Publish Events:** when something is made publicly visible we call purge( `list:$types` )
- **Update Events:** when something public is updated, we call `purge( $node_id )`
- **Delete Events:** when something public is made not-public, we call `purge( $node_id )`

## Tracked Events

### Posts, Pages, Custom Post Types

- published
- updated
- deleted
- meta changes

### Categories, Tags, Custom Taxonomies

- created
- assigned to post
- unassigned to post
- deleted
- meta changes

### Users

- created
- assigned as author of a post
- deleted
- author re-assigned

### Media

- uploaded
- updated
- deleted

### Comments

### Settings / Options
