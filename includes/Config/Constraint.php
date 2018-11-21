<?php

namespace WPMDB\Anonymization\Config;

class Constraint {

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