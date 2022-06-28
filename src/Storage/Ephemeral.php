<?php
/**
 * Nothing fancy here. Use in memory array to 'store' some data.
 * Makes a good test class.
 */
namespace WPGraphQL\SmartCache\Storage;

class Ephemeral {

	public $data;

	public function __construct( $group_name ) {
		$this->data = [];
	}

	/**
	 * Get the data from cache/transient based on the provided key
	 *
	 * @param string unique id for this request
	 * @return mixed|array|object|null  The graphql response or false if not found
	 */
	public function get( $key ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : false;
	}

	/**
	 * @param string unique id for this request
	 * @param mixed|array|object|null  The graphql response
	 * @param int Time in seconds for the data to persist in cache. Zero means no expiration.
	 *
	 * @return bool False if value was not set and true if value was set.
	 */
	public function set( $key, $data, $expire ) {
		$this->data[ $key ] = is_array( $data ) ? $data : $data->toArray();
		return true;
	}

	/**
	 * Searches the database for all graphql transients matching our prefix
	 *
	 * @return bool True on success, false on failure.
	 */
	public function purge_all() {
		$this->data = [];
		return true;
	}

	/**
	 * @return bool True on successful removal, false on failure.
	 */
	public function delete( $key ) {
		$ret = isset( $this->data[ $key ] );
		unset( $this->data[ $key ] );
		return $ret;
	}

}
