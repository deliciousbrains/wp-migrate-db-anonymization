<?php

namespace WPMDB\Anonymization\Config;

class Replacement {

	/**
	 * @param $password
	 *
	 * @return string
	 */
	public static function password( $password ) {
		// Hash once per run and give every anonymized user the same hash. Hashing
		// is deliberately slow (bcrypt since WordPress 6.8), so hashing a fresh
		// fake password per user caps large users tables at a few rows a second.
		// Nobody knows the shared password, so users are locked out just as before.
		if ( null === self::$password_hash ) {
			$shared = defined( 'WPMDB_ANONYMIZATION_DEFAULT_PASSWORD' ) && ! empty( WPMDB_ANONYMIZATION_DEFAULT_PASSWORD ) ? WPMDB_ANONYMIZATION_DEFAULT_PASSWORD : $password;

			self::$password_hash = wp_hash_password( $shared );
		}

		return self::$password_hash;
	}

	/**
	 * The password hash shared by every anonymized user in this run.
	 *
	 * @var string|null
	 */
	protected static $password_hash = null;

	/**
	 * Forget the shared password hash.
	 *
	 * @internal For tests.
	 */
	public static function reset_password_cache() {
		self::$password_hash = null;
	}

	/**
	 * Wrap a fake value in a serialized single-item list, for meta stored as an
	 * array of values, e.g. coupon email restrictions.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	public static function serialized_list( $value ) {
		return serialize( array( $value ) );
	}
}
