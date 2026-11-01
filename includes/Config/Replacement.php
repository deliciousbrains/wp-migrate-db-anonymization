<?php

namespace WPMDB\Anonymization\Config;

class Replacement {

	/**
	 * @param $password
	 *
	 * @return string
	 */
	public static function password( $password ) {
		static $default_password_hash = null;

		if ( defined( 'WPMDB_ANONYMIZATION_DEFAULT_PASSWORD' ) && ! empty( WPMDB_ANONYMIZATION_DEFAULT_PASSWORD )) {
			// Hash the shared default password once: hashing is deliberately slow
			// and every user gets the same password anyway.
			if ( null === $default_password_hash ) {
				$default_password_hash = wp_hash_password( WPMDB_ANONYMIZATION_DEFAULT_PASSWORD );
			}

			return $default_password_hash;
		}

		return wp_hash_password( $password );
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
