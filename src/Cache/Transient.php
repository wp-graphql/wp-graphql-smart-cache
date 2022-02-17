<?php

namespace WPGraphQL\Labs\Cache;

class Transient {

	/**
	 * Get the data from cache/transient based on the provided key
	 *
	 * @param string unique id for this request
	 * @return mixed|array|object|null  The graphql response or null if not found
	 */
	public function get( $key ) {
		return get_transient( $key );
	}

	/**
	 * @param string unique id for this request
	 * @param mixed|array|object|null  The graphql response
	 * @param int Time in seconds for the data to persist in cache. Zero means no expiration.
	 *
	 * @return bool False if value was not set and true if value was set.
	 */
	public function save( $key, $data, $expire ) {
		return set_transient(
			$key,
			is_array( $data ) ? $data : $data->toArray(),
			$expire
		);
	}

	/**
	 * Searches the database for all graphql transients matching our prefix
	 *
	 * @return bool True on success, false on failure.
	 */
	public function purge_all() {
		global $wpdb;

		$prefix = Query::KEY_PREFIX;

		// The transient string + our prefix as it is stored in the options database
		$transient_option_name = $wpdb->esc_like( '_transient_' . $prefix . '_' ) . '%';

		// Make database query to get out transients
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$transients = $wpdb->get_results( $wpdb->prepare( "SELECT `option_name` FROM $wpdb->options WHERE `option_name` LIKE %s", $transient_option_name ), ARRAY_A ); //db call ok

		if ( ! $transients || is_wp_error( $transients ) || ! is_array( $transients ) ) {
			return false;
		}

		// Loop through our transients
		foreach ( $transients as $transient ) {
			// Remove this string from the option_name to get the name we will use on delete
			$key = str_replace( '_transient_', '', $transient['option_name'] );
			delete_transient( $key );
		}

		return true;
	}
}
