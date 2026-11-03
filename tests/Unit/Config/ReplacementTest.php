<?php

namespace WPMDB\Anonymization\Tests\Unit\Config;

use WPMDB\Anonymization\Config\Replacement;
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
}
