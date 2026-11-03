<?php
/**
 * Minimal stand-ins for the WordPress functions used by the plugin.
 *
 * The hook functions implement a tiny working filter/action registry so tests
 * can register real callbacks with add_filter() and have apply_filters() run
 * them. Call wpmdb_anonymization_tests_reset_hooks() between tests (the base
 * TestCase does this automatically).
 */

if ( ! function_exists( 'wpmdb_anonymization_tests_reset_hooks' ) ) {
	/**
	 * Remove every registered filter and action.
	 */
	function wpmdb_anonymization_tests_reset_hooks() {
		$GLOBALS['wpmdb_anonymization_tests_hooks'] = array();
	}
}

wpmdb_anonymization_tests_reset_hooks();

if ( ! function_exists( 'wpmdb_anonymization_tests_set_environment_type' ) ) {
	/**
	 * Set the value wp_get_environment_type() returns. The base TestCase
	 * resets it to 'local' before each test.
	 *
	 * @param string $type e.g. 'production'.
	 */
	function wpmdb_anonymization_tests_set_environment_type( $type ) {
		$GLOBALS['wpmdb_anonymization_tests_environment_type'] = $type;
	}
}

wpmdb_anonymization_tests_set_environment_type( 'local' );

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wpmdb_anonymization_tests_hooks'][ $hook_name ][ $priority ][] = array(
			'callback'      => $callback,
			'accepted_args' => (int) $accepted_args,
		);

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value, ...$args ) {
		if ( empty( $GLOBALS['wpmdb_anonymization_tests_hooks'][ $hook_name ] ) ) {
			return $value;
		}

		$callbacks = $GLOBALS['wpmdb_anonymization_tests_hooks'][ $hook_name ];
		ksort( $callbacks );

		foreach ( $callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $hook ) {
				$all_args = array_merge( array( $value ), $args );
				$value    = call_user_func_array( $hook['callback'], array_slice( $all_args, 0, max( 1, $hook['accepted_args'] ) ) );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	function has_filter( $hook_name ) {
		return ! empty( $GLOBALS['wpmdb_anonymization_tests_hooks'][ $hook_name ] );
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( $hook_name ) {
		unset( $GLOBALS['wpmdb_anonymization_tests_hooks'][ $hook_name ] );

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook_name, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ) {
		if ( empty( $GLOBALS['wpmdb_anonymization_tests_hooks'][ $hook_name ] ) ) {
			return;
		}

		$callbacks = $GLOBALS['wpmdb_anonymization_tests_hooks'][ $hook_name ];
		ksort( $callbacks );

		foreach ( $callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $hook ) {
				call_user_func_array( $hook['callback'], array_slice( $args, 0, $hook['accepted_args'] ) );
			}
		}
	}
}

if ( ! function_exists( 'wp_hash_password' ) ) {
	/**
	 * Deterministic, clearly fake hash so tests can assert on the input.
	 */
	function wp_hash_password( $password ) {
		return 'hashed:' . $password;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( '__return_true' ) ) {
	function __return_true() {
		return true;
	}
}

if ( ! function_exists( '__return_false' ) ) {
	function __return_false() {
		return false;
	}
}

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	/**
	 * Returns the type set with wpmdb_anonymization_tests_set_environment_type().
	 */
	function wp_get_environment_type() {
		return $GLOBALS['wpmdb_anonymization_tests_environment_type'];
	}
}
