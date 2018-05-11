<?php

namespace WPMDB\Anonymization\Config;

class Constraint {

	public static function is_not_whitelisted_user( $row ) {
		if ( ! defined( 'WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST' ) || empty( WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST ) ) {
			return true;
		}

		$whitelisted_user_logins = explode( ',', WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST );

		if ( ! isset( $row->user_login ) ) {
			return true;
		}

		return ! in_array( $row->user_login, $whitelisted_user_logins );
	}
}