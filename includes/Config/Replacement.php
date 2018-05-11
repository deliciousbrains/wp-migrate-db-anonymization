<?php

namespace WPMDB\Anonymization\Config;

class Replacement {

	/**
	 * @param $password
	 *
	 * @return string
	 */
	public static function password( $password ) {
		if ( defined( 'WPMDB_ANONYMIZATION_DEFAULT_PASSWORD' ) && ! empty( WPMDB_ANONYMIZATION_DEFAULT_PASSWORD )) {
			$password = WPMDB_ANONYMIZATION_DEFAULT_PASSWORD;
		}

		return wp_hash_password( $password );
	}
}