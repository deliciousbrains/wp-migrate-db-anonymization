<?php

namespace WPMDB\Anonymization\Config;

class Constraint {

	public static function is_not_whitelisted_user( $row ) {
		if ( ! isset( $row->user_login ) ) {
			return true;
		}

		return ! self::is_whitelisted_user( $row->user_login );
	}

	protected static function is_whitelisted_user( $user_login ) {
		$whitelisted = false;
		if ( defined( 'WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST' ) ) {
			$whitelisted_user_logins = explode( ',', WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST );

			$whitelisted = in_array( $user_login, $whitelisted_user_logins );
		}

		return apply_filters( 'wpmdb_anonymization_user_whitelisted', $whitelisted, $user_login );
	}
}