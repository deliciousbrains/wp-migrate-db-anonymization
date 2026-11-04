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

		// A users row has several anonymized columns and each one is checked, so
		// reuse the answer for the same row rather than working it out again.
		if ( null !== self::$last_check && self::$last_check[0] === $row ) {
			return self::$last_check[1];
		}

		$not_whitelisted  = ! self::is_whitelisted_row( $row );
		self::$last_check = array( $row, $not_whitelisted );

		return $not_whitelisted;
	}

	/**
	 * The last row checked by is_not_whitelisted_user() and its result.
	 *
	 * @var array|null array( object $row, bool $not_whitelisted )
	 */
	protected static $last_check = null;

	/**
	 * @param object $row A users table row.
	 *
	 * @return bool
	 */
	protected static function is_whitelisted_row( $row ) {
		$whitelisted = false;
		if ( defined( 'WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST' ) ) {
			$whitelisted_user_logins = array_map( 'trim', explode( ',', WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST ) );

			$whitelisted = in_array( $row->user_login, $whitelisted_user_logins );
		}

		// Building a WP_User loads the user's meta from the database, which makes
		// large users tables crawl. Only do it when a callback needs the user.
		if ( ! function_exists( 'has_filter' ) || ! has_filter( 'wpmdb_anonymization_user_whitelisted' ) ) {
			return $whitelisted;
		}

		return (bool) apply_filters( 'wpmdb_anonymization_user_whitelisted', $whitelisted, new \WP_User( $row ) );
	}
}