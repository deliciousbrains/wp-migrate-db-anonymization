<?php
/**
 * Minimal stand-ins for the WP-CLI API used by the migrate-anonymize command.
 *
 * WP_CLI::error() throws WPMDB_Anonymization_Tests_CLI_Error instead of
 * exiting, and every message is recorded in WP_CLI::$messages so tests can
 * assert on the output. Call WP_CLI::reset() between tests.
 *
 * The WP_CLI constant is not defined, so the plugin never registers the
 * command during tests.
 */

namespace {

	if ( ! class_exists( 'WPMDB_Anonymization_Tests_CLI_Error' ) ) {
		class WPMDB_Anonymization_Tests_CLI_Error extends RuntimeException {
		}
	}

	if ( ! class_exists( 'WP_CLI' ) ) {
		class WP_CLI {

			/**
			 * @var array List of array( type, message ).
			 */
			public static $messages = array();

			/**
			 * @var array Items passed to WP_CLI\Utils\format_items().
			 */
			public static $items = array();

			public static function reset() {
				self::$messages = array();
				self::$items    = array();
			}

			public static function log( $message ) {
				self::$messages[] = array( 'log', $message );
			}

			public static function debug( $message, $group = false ) {
				self::$messages[] = array( 'debug', $message );
			}

			public static function warning( $message ) {
				self::$messages[] = array( 'warning', $message );
			}

			public static function success( $message ) {
				self::$messages[] = array( 'success', $message );
			}

			public static function confirm( $question, $assoc_args = array() ) {
				self::$messages[] = array( 'confirm', $question );
			}

			public static function error( $message, $exit = true ) {
				self::$messages[] = array( 'error', $message );

				throw new WPMDB_Anonymization_Tests_CLI_Error( $message );
			}
		}
	}

	if ( ! function_exists( 'wp_list_pluck' ) ) {
		function wp_list_pluck( $input_list, $field ) {
			$values = array();
			foreach ( $input_list as $key => $item ) {
				$values[ $key ] = is_object( $item ) ? $item->{$field} : $item[ $field ];
			}

			return $values;
		}
	}

	if ( ! function_exists( 'wp_cache_flush' ) ) {
		/**
		 * Recorded in WP_CLI::$messages so tests can check when it ran.
		 */
		function wp_cache_flush() {
			WP_CLI::$messages[] = array( 'cache_flush', '' );

			return true;
		}
	}
}

namespace WP_CLI\Utils {

	if ( ! function_exists( 'WP_CLI\Utils\get_flag_value' ) ) {
		function get_flag_value( $assoc_args, $flag, $default = null ) {
			return isset( $assoc_args[ $flag ] ) ? $assoc_args[ $flag ] : $default;
		}
	}

	if ( ! function_exists( 'WP_CLI\Utils\make_progress_bar' ) ) {
		function make_progress_bar( $message, $count ) {
			return new class() {
				public function tick() {
				}

				public function finish() {
				}
			};
		}
	}

	if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
		function format_items( $format, $items, $fields ) {
			\WP_CLI::$items = $items;
		}
	}
}
