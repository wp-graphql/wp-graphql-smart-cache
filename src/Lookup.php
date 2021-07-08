<?php
/**
 * Storage
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

class Lookup {
  /**
   * When a queryId is found on the request, this call back is invoked to look up the query string
   * Can be invoked on GET or POST params
   *
   * @param array $query_id An array containing the pieces of the data of the GraphQL request
   * @param array $operation_params An array containing the method, body and query params
   * @return string | GraphQL\Language\AST\DocumentNode 
   */
  public static function byQueryId( $query_id, $operation_params ) {
      // We look for the query id in our system and return that.
      // This is where we look for the query id in our storage and return that string as the query
      wp_send_json( [ "this is a query with an id:" => [ $query_id, $operation_params ] ] );
      return '{
          contentNodes {
            nodes {
              uri
            }
          }
        }';
      return '{__typename}';
  }
}
