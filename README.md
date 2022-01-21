# Welcome

Install the plugin in your WP environment. It is dependent on wp-grapqhl also being installed.

Save a graphql query string for future use by an easy usable sha 256 hash or an alias name.

## Saving Queries

When submiting a query from your client, in the POST request provide a `queryId={value}` parameter with the submitted data.  This `queryId` becomes the alias name of the saved query.  It can then be used in future requests to invoke the query.

Example POST data:

```
{
  "query": "{
          posts {
            nodes {
              title
            }
          }
  }"
  "queryId": "query-get-posts-title"
}
```

After that successful POST request, you can use the GET request to invoke the query by queryId. 

    docker run -v $PWD:/app composer install --optimize-autoloader

```
https://domain.example/graphql?queryId=query-get-posts-title
```

## What about graphql variables?

Queries that require variables to execute the graphql query, will need the variables specified with each POST or GET request.  The variables are not saved with the query string in the system.

Here is an example of invoking a saved query and providing variables.

If this is the saved query in the system, 

POST

```
{
  "query": "query ($count:Int) {
          posts(first:$count) {
            nodes {
              title
            }
          }
  }"
  "queryId": "query-get-posts-title"
}
```

The GET request would look like this to provide variables for the request,

```
https://domain.example/graphql?queryId=query-get-posts-title&variables={"count":2}
```

## What about queries with multiple operation names?

Graphql query strings can contain multiple operations per string and an operation name is provided in the request to specify which query to invoke.  The same is true for saved queries.

Below is an example of a query string containing multiple operations that can be saved with queryId "multiple-query-get-posts".

POST

```
{
  "query": "
     query GetPosts {
      posts {
       nodes{
        id
        title
       }
      }
    }
    query GetPostsSlug {
      posts {
       nodes{
        id
        title
        slug
       }
      }
    }
  ",
  "queryId": "multiple-query-get-posts"
}
```

The GET request for the saved query specifying operation name looks like this,

https://domain.example/graphql?queryId=multiple-query-get-posts&operationName=GetPostsSlug

And if your query is multiple operations as well as variables, combine all of it together in your saved query and use the correct name/value parameters on the GET query requests.

## Not Found Error

If the queryId is not found in the system an error message will be returned in the response payload.  A HTTP code of 200 is returned.

```
{
    "errors": [
        {
            "message": "Query Not Found get-post-with-slug",
            "extensions": {
                "category": "request"
            }
        }
    ],
}
```