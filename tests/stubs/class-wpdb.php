<?php
/**
 * Minimal stand-in for WordPress' wpdb class.
 *
 * Only the properties the plugin reads are provided. Set them per test as needed.
 */

if ( ! class_exists( 'wpdb' ) ) {
	class wpdb {

		/**
		 * @var string
		 */
		public $base_prefix;

		/**
		 * @var string
		 */
		public $prefix;

		/**
		 * @param string $base_prefix
		 */
		public function __construct( $base_prefix = 'wp_' ) {
			$this->base_prefix = $base_prefix;
			$this->prefix      = $base_prefix;
		}
	}
}
