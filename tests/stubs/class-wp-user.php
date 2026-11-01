<?php
/**
 * Minimal stand-in for WordPress' WP_User class.
 *
 * Mirrors the behaviour the plugin relies on: constructing from a database row
 * object and reading the row's columns as properties.
 */

if ( ! class_exists( 'WP_User' ) ) {
	class WP_User {

		/**
		 * @var object
		 */
		public $data;

		/**
		 * @var int
		 */
		public $ID = 0;

		/**
		 * @param int|object|WP_User $id
		 */
		public function __construct( $id = 0 ) {
			if ( $id instanceof WP_User ) {
				$id = $id->data;
			}

			if ( is_array( $id ) ) {
				$id = (object) $id;
			}

			if ( ! is_object( $id ) ) {
				$id = (object) array( 'ID' => (int) $id );
			}

			$this->data = $id;
			$this->ID   = isset( $id->ID ) ? (int) $id->ID : 0;
		}

		public function __get( $key ) {
			return isset( $this->data->{$key} ) ? $this->data->{$key} : null;
		}

		public function __isset( $key ) {
			return isset( $this->data->{$key} );
		}
	}
}
