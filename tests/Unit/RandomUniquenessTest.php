<?php

namespace WPMDB\Anonymization\Tests\Unit;

use WPMDB\Anonymization\Anonymizer;
use WPMDB\Anonymization\Config\Rule;
use WPMDB\Anonymization\Deterministic;
use WPMDB\Anonymization\Tests\Doubles\FakerSpy;
use WPMDB\Anonymization\Tests\TestCase;

/**
 * Without a secret, email addresses and user names still need to be unique,
 * e.g. for plugin tables with a UNIQUE email column (#29).
 */
class RandomUniquenessTest extends TestCase {

	const EMAIL_PATTERN    = '/^[a-z0-9._-]+\.[0-9a-f]{12}@example\.com$/';
	const USERNAME_PATTERN = '/^[a-z0-9._-]+\.[0-9a-f]{12}$/';

	/**
	 * @var Deterministic Without a secret, i.e. random mode.
	 */
	protected $random;

	protected function setUp(): void {
		parent::setUp();
		$this->random = new Deterministic( '' );
	}

	/**
	 * @param string $type
	 * @param array  $extra
	 *
	 * @return Rule
	 */
	protected function rule( $type, array $extra = array() ) {
		$rule = new Rule( 'users', 'user_email' );
		$rule->load( array_merge( array( 'fake_data_type' => $type ), $extra ) );

		return $rule;
	}

	public function test_50k_random_emails_are_unique_and_formatted() {
		$this->assert_unique_values( 'email', self::EMAIL_PATTERN, 50000 );
	}

	public function test_50k_random_user_names_are_unique_and_formatted() {
		$this->assert_unique_values( 'userName', self::USERNAME_PATTERN, 50000 );
	}

	/**
	 * @param string $type
	 * @param string $pattern
	 * @param int    $count
	 */
	protected function assert_unique_values( $type, $pattern, $count ) {
		$faker = $this->make_faker();
		$rule  = $this->rule( $type );
		$seen  = array();

		for ( $i = 0; $i < $count; $i++ ) {
			// The same original every time: in random mode the output must still differ.
			$value = $rule->anonymize( $faker, 'same@original.test', $this->random );
			if ( 1 !== preg_match( $pattern, $value ) ) {
				$this->fail( "{$value} does not match {$pattern}" );
			}
			$seen[ $value ] = true;
		}

		$this->assertCount( $count, $seen );
	}

	/**
	 * @dataProvider email_type_provider
	 */
	public function test_every_email_type_uses_the_unique_format( $type ) {
		$this->assertMatchesPattern( self::EMAIL_PATTERN, $this->rule( $type )->anonymize( $this->make_faker(), 'foo@bar.com', $this->random ) );
	}

	public function email_type_provider() {
		return array(
			array( 'email' ),
			array( 'safeEmail' ),
			array( 'freeEmail' ),
			array( 'companyEmail' ),
		);
	}

	public function test_random_and_deterministic_outputs_have_the_same_format() {
		$faker         = $this->make_faker();
		$deterministic = new Deterministic( 'a-secret' );

		foreach ( array( 'email' => self::EMAIL_PATTERN, 'userName' => self::USERNAME_PATTERN ) as $type => $pattern ) {
			$this->assertMatchesPattern( $pattern, $this->rule( $type )->anonymize( $faker, 'foo@bar.com', $this->random ) );
			$this->assertMatchesPattern( $pattern, $this->rule( $type )->anonymize( $faker, 'foo@bar.com', $deterministic ) );
		}
	}

	public function test_random_unique_values_use_a_random_fragment() {
		$faker = new FakerSpy();

		$first  = $this->random->generate_random_unique( $faker, 'userName' );
		$second = $this->random->generate_random_unique( $faker, 'userName' );

		$this->assertMatchesPattern( '/^username\.[0-9a-f]{12}$/', $first );
		$this->assertNotSame( $first, $second );
	}

	public function test_non_unique_types_are_unchanged() {
		foreach ( array( 'firstName', 'lastName', 'name', 'city' ) as $type ) {
			$faker = new FakerSpy();

			$this->assertSame( $type, $this->rule( $type )->anonymize( $faker, 'original', $this->random ) );
			$this->assertSame( array( array( $type, array() ) ), $faker->calls );
		}
	}

	public function test_without_the_original_faker_output_is_returned_as_is() {
		$this->assertSame( 'email', $this->rule( 'email' )->anonymize( new FakerSpy() ) );
		$this->assertSame( 'userName', $this->rule( 'userName' )->anonymize( new FakerSpy() ) );
	}

	public function test_post_process_function_still_applies() {
		$rule = $this->rule( 'email', array( 'post_process_function' => 'strtoupper' ) );

		$this->assertMatchesPattern( '/^[A-Z0-9._-]+\.[0-9A-F]{12}@EXAMPLE\.COM$/', $rule->anonymize( $this->make_faker(), 'foo@bar.com', $this->random ) );
	}

	public function test_anonymizer_without_secret_gives_unique_emails() {
		$config     = $this->make_config( array( 'users' => array( 'user_email' => array( 'fake_data_type' => 'email' ) ) ) );
		$anonymizer = new Anonymizer( $config, $this->make_faker(), $this->random );
		$row        = $this->row( array( 'user_email' => 'foo@bar.com' ) );

		$first  = $anonymizer->anonymize_value( 'users', 'user_email', $row, 'foo@bar.com' );
		$second = $anonymizer->anonymize_value( 'users', 'user_email', $row, 'foo@bar.com' );

		$this->assertMatchesPattern( self::EMAIL_PATTERN, $first );
		$this->assertNotSame( $first, $second );
	}

	public function test_deterministic_fragment_still_comes_from_the_hmac() {
		$deterministic = new Deterministic( 'unit-test-secret' );
		$hash          = $deterministic->hash( 'email', array(), 'foo@bar.com' );
		$email         = $deterministic->generate( $this->make_faker(), 'email', array(), 'foo@bar.com' );

		$this->assertStringEndsWith( '.' . substr( $hash, 32, 12 ) . '@example.com', $email );
		$this->assertSame( '65edc30488a1', substr( $hash, 32, 12 ), 'The deterministic derivation has not changed.' );
	}

	/**
	 * @param string $pattern
	 * @param string $value
	 */
	protected function assertMatchesPattern( $pattern, $value ) {
		$this->assertSame( 1, preg_match( $pattern, $value ), "$value does not match $pattern" );
	}
}
