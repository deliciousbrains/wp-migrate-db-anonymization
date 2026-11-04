<?php

namespace WPMDB\Anonymization\Tests\Unit\Config;

use WPMDB\Anonymization\Config\Constraint;
use WPMDB\Anonymization\Tests\TestCase;

class ConstraintTest extends TestCase {

	public function test_row_without_user_login_is_not_whitelisted() {
		$this->assertTrue( Constraint::is_not_whitelisted_user( $this->row( array( 'meta_key' => 'x' ) ) ) );
	}

	public function test_user_is_not_whitelisted_by_default() {
		$this->assertFalse( defined( 'WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST' ), 'Whitelist constant must only be defined in isolated tests.' );

		$this->assertTrue( Constraint::is_not_whitelisted_user( $this->row( array( 'user_login' => 'admin' ) ) ) );
	}

	public function test_filter_can_whitelist_a_user() {
		add_filter( 'wpmdb_anonymization_user_whitelisted', '__return_true' );

		$this->assertFalse( Constraint::is_not_whitelisted_user( $this->row( array( 'user_login' => 'admin' ) ) ) );
	}

	public function test_filter_receives_default_and_wp_user_built_from_row() {
		$received = array();
		add_filter(
			'wpmdb_anonymization_user_whitelisted',
			function ( $whitelisted, $user ) use ( &$received ) {
				$received = array( $whitelisted, $user );

				return false !== strpos( $user->user_email, '@example.org' );
			},
			10,
			2
		);

		$row = $this->row(
			array(
				'ID'         => '5',
				'user_login' => 'jane',
				'user_email' => 'jane@example.org',
			)
		);

		$this->assertFalse( Constraint::is_not_whitelisted_user( $row ) );
		$this->assertFalse( $received[0] );
		$this->assertInstanceOf( \WP_User::class, $received[1] );
		$this->assertSame( 5, $received[1]->ID );
		$this->assertSame( 'jane', $received[1]->user_login );

		$other = $this->row(
			array(
				'ID'         => '6',
				'user_login' => 'john',
				'user_email' => 'john@example.com',
			)
		);
		$this->assertTrue( Constraint::is_not_whitelisted_user( $other ) );
	}

	public function test_no_wp_user_is_built_without_a_whitelist_filter() {
		$before = \WP_User::$constructed;

		for ( $i = 1; $i <= 50; $i++ ) {
			$this->assertTrue( Constraint::is_not_whitelisted_user( $this->row( array( 'ID' => (string) $i, 'user_login' => "user{$i}" ) ) ) );
		}

		$this->assertSame( $before, \WP_User::$constructed, 'Without a filter the login is compared directly.' );
	}

	public function test_each_row_is_checked_once_across_its_columns() {
		$calls = 0;
		add_filter(
			'wpmdb_anonymization_user_whitelisted',
			function ( $whitelisted, $user ) use ( &$calls ) {
				$calls++;

				return 'keep' === $user->user_login;
			},
			10,
			2
		);

		$kept  = $this->row( array( 'ID' => '1', 'user_login' => 'keep' ) );
		$other = $this->row( array( 'ID' => '2', 'user_login' => 'other' ) );
		$before = \WP_User::$constructed;

		// One check per anonymized column of the same row.
		for ( $column = 0; $column < 6; $column++ ) {
			$this->assertFalse( Constraint::is_not_whitelisted_user( $kept ) );
		}
		for ( $column = 0; $column < 6; $column++ ) {
			$this->assertTrue( Constraint::is_not_whitelisted_user( $other ) );
		}

		$this->assertSame( 2, $calls, 'The filter runs once per row.' );
		$this->assertSame( $before + 2, \WP_User::$constructed, 'One WP_User per row.' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_whitelist_constant_whitelists_listed_logins() {
		define( 'WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST', 'admin, editor ,  ops' );

		$this->assertFalse( Constraint::is_not_whitelisted_user( $this->row( array( 'user_login' => 'admin' ) ) ) );
		$this->assertFalse( Constraint::is_not_whitelisted_user( $this->row( array( 'user_login' => 'editor' ) ) ) );
		$this->assertFalse( Constraint::is_not_whitelisted_user( $this->row( array( 'user_login' => 'ops' ) ) ) );
		$this->assertTrue( Constraint::is_not_whitelisted_user( $this->row( array( 'user_login' => 'subscriber' ) ) ) );
		$this->assertTrue( Constraint::is_not_whitelisted_user( $this->row( array( 'user_login' => 'Admin' ) ) ), 'Logins are matched case-sensitively.' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_filter_can_override_whitelist_constant() {
		define( 'WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST', 'admin' );
		add_filter( 'wpmdb_anonymization_user_whitelisted', '__return_false' );

		$this->assertTrue( Constraint::is_not_whitelisted_user( $this->row( array( 'user_login' => 'admin' ) ) ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_filter_receives_constant_result() {
		define( 'WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST', 'admin' );
		$received = null;
		add_filter(
			'wpmdb_anonymization_user_whitelisted',
			function ( $whitelisted ) use ( &$received ) {
				$received = $whitelisted;

				return $whitelisted;
			}
		);

		Constraint::is_not_whitelisted_user( $this->row( array( 'user_login' => 'admin' ) ) );

		$this->assertTrue( $received );
	}
}
