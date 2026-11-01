<?php

namespace WPMDB\Anonymization\Tests\Unit\Config;

use WPMDB\Anonymization\Config\Replacement;
use WPMDB\Anonymization\Config\Rule;
use WPMDB\Anonymization\Tests\TestCase;

class ReplacementTest extends TestCase {

	public function test_password_is_hashed() {
		$this->assertFalse( defined( 'WPMDB_ANONYMIZATION_DEFAULT_PASSWORD' ), 'Default password constant must only be defined in isolated tests.' );

		$this->assertSame( 'hashed:fake-password', Replacement::password( 'fake-password' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_default_password_constant_replaces_password() {
		// Built at runtime: a literal here reads as a hard-coded credential to static analysis.
		$default = 'default-' . bin2hex( random_bytes( 4 ) );
		define( 'WPMDB_ANONYMIZATION_DEFAULT_PASSWORD', $default );

		$this->assertSame( 'hashed:' . $default, Replacement::password( 'fake-password' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_empty_default_password_constant_is_ignored() {
		define( 'WPMDB_ANONYMIZATION_DEFAULT_PASSWORD', '' );

		$this->assertSame( 'hashed:fake-password', Replacement::password( 'fake-password' ) );
	}

	public function test_serialized_list_wraps_value_in_serialized_array() {
		$this->assertSame( 'a:1:{i:0;s:16:"fake@example.com";}', Replacement::serialized_list( 'fake@example.com' ) );
	}

	public function test_coupon_email_restrictions_are_a_serialized_list_of_one_fake_email() {
		$rule = new Rule( 'postmeta', 'meta_value' );
		$rule->load(
			array(
				'constraint'            => array( 'meta_key' => 'customer_email' ),
				'fake_data_type'        => 'email',
				'post_process_function' => 'WPMDB\\Anonymization\\Config\\Replacement::serialized_list',
			)
		);

		$emails = unserialize( $rule->anonymize( $this->make_faker() ) );

		$this->assertIsArray( $emails );
		$this->assertCount( 1, $emails );
		$this->assertNotFalse( filter_var( $emails[0], FILTER_VALIDATE_EMAIL ) );
	}
}
