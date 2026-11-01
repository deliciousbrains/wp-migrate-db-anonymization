<?php

namespace WPMDB\Anonymization\Config;

class Constraint {

	/**
	 * Check a column value against the expected value of a column constraint.
	 *
	 * - A scalar must match exactly.
	 * - array( 'in' => array( ... ) ) matches any of the listed values.
	 * - array( 'regex' => '/pattern/' ) matches a PCRE pattern (delimiters included).
	 * - A plain list, e.g. array( 'a', 'b' ), is shorthand for 'in'.
	 *
	 * If both 'in' and 'regex' are given, both must match.
	 *
	 * @param mixed $value
	 * @param mixed $expected
	 *
	 * @return bool
	 */
	public static function column_matches( $value, $expected ) {
		if ( ! is_array( $expected ) ) {
			return $value === $expected;
		}

		if ( ! array_key_exists( 'in', $expected ) && ! array_key_exists( 'regex', $expected ) ) {
			return self::value_in( $value, $expected );
		}

		if ( array_key_exists( 'in', $expected ) && ! self::value_in( $value, (array) $expected['in'] ) ) {
			return false;
		}

		if ( array_key_exists( 'regex', $expected ) && 1 !== preg_match( $expected['regex'], (string) $value ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Database values are strings, so compare as strings to allow integers in config.
	 *
	 * @param mixed $value
	 * @param array $allowed
	 *
	 * @return bool
	 */
	protected static function value_in( $value, array $allowed ) {
		return in_array( (string) $value, array_map( 'strval', $allowed ), true );
	}

	public static function is_not_whitelisted_user( $row ) {
		if ( ! isset( $row->user_login ) ) {
			return true;
		}

		$user = new \WP_User( $row );

		return ! self::is_whitelisted_user( $user );
	}

	/**
	 * @param \WP_User $user
	 *
	 * @return bool
	 */
	protected static function is_whitelisted_user( $user ) {
		$whitelisted = false;
		if ( defined( 'WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST' ) ) {
			$whitelisted_user_logins = array_map( 'trim', explode( ',', WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST ) );

			$whitelisted = in_array( $user->user_login, $whitelisted_user_logins );
		}

		return (bool) apply_filters( 'wpmdb_anonymization_user_whitelisted', $whitelisted, $user );
	}
}