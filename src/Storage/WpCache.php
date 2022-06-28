<?php

namespace WPGraphQL\SmartCache\Storage;

class WpCache {

	public $group_name;

	public function __construct( $group_name ) {
		$this->group_name = $group_name;
	}

	/**
	 * Get the data from cache/transient based on the provided key
	 *
	 * @param string unique id for this request
	 * @return mixed|array|object|null  The graphql response or false if not found
	 */
	public function get( $key ) {
		return wp_cache_get( $key, $this->group_name );
	}

	/**
	 * @param string unique id for this request
	 * @param mixed|array|object|null  The graphql response
	 * @param int Time in seconds for the data to persist in cache. Zero means no expiration.
	 *
	 * @return bool False if value was not set and true if value was set.
	 */
	public function set( $key, $data, $expire ) {
		return wp_cache_set(
			$key,
			is_array( $data ) ? $data : $data->toArray(),
			$this->group_name,
			$expire
		);
	}

	/**
	 * @return bool True on success, false on failure.
	 */
	public function purge_all() {
		return wp_cache_flush();
	}

	/**
	 * @return bool True on successful removal, false on failure.
	 */
	public function delete( $key ) {
		return wp_cache_delete( $key, $this->group_name );
	}

}
