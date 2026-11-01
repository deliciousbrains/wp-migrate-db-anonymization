<?php

namespace WPMDB\Anonymization\Tests\Unit;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use WPMDB\Anonymization\Config\Rule;
use WPMDB\Anonymization\Deterministic;

/**
 * Deterministic anonymization. Needs only Faker and the plugin classes, no WordPress.
 */
class DeterministicTest extends TestCase {

	/**
	 * @var \Faker\Generator
	 */
	protected $faker;

	/**
	 * @var Deterministic
	 */
	protected $deterministic;

	protected function setUp(): void {
		$this->faker         = Factory::create();
		$this->deterministic = new Deterministic( 'unit-test-secret' );
	}

	public function test_same_input_gives_same_output_regardless_of_call_order() {
		$first = $this->deterministic->generate( $this->faker, 'firstName', array(), 'Alice' );

		$this->faker->firstName();
		$this->deterministic->generate( $this->faker, 'firstName', array(), 'Bob' );

		$this->assertSame( $first, $this->deterministic->generate( $this->faker, 'firstName', array(), 'Alice' ) );
	}

	public function test_different_secret_gives_different_output() {
		$other = new Deterministic( 'another-secret' );

		$this->assertNotSame(
			$this->deterministic->generate( $this->faker, 'email', array(), 'foo@bar.com' ),
			$other->generate( $this->faker, 'email', array(), 'foo@bar.com' )
		);
	}

	public function test_emails_are_normalised_and_share_one_key() {
		$email = $this->deterministic->generate( $this->faker, 'email', array(), 'foo@bar.com' );

		$this->assertSame( $email, $this->deterministic->generate( $this->faker, 'email', array(), '  Foo@BAR.com ' ) );
		$this->assertSame( $email, $this->deterministic->generate( $this->faker, 'safeEmail', array(), 'foo@bar.com' ) );
	}

	public function test_unique_types_embed_hash_fragment() {
		$email    = $this->deterministic->generate( $this->faker, 'email', array(), 'foo@bar.com' );
		$username = $this->deterministic->generate( $this->faker, 'userName', array(), 'foo' );

		$this->assertMatchesPattern( '/^[a-z0-9._-]+\.[0-9a-f]{12}@example\.com$/', $email );
		$this->assertMatchesPattern( '/^[a-z0-9._-]+\.[0-9a-f]{12}$/', $username );
		$this->assertLessThanOrEqual( 50, strlen( $username ) );
	}

	public function test_unique_types_do_not_collide() {
		$emails    = array();
		$usernames = array();
		$count     = 20000;

		for ( $i = 0; $i < $count; $i++ ) {
			$emails[ $this->deterministic->generate( $this->faker, 'email', array(), "person{$i}@example.org" ) ] = true;
			$usernames[ $this->deterministic->generate( $this->faker, 'userName', array(), "user{$i}" ) ]     = true;
		}

		$this->assertCount( $count, $emails );
		$this->assertCount( $count, $usernames );
	}

	public function test_type_and_args_are_part_of_the_key() {
		$this->assertNotSame( $this->deterministic->hash( 'firstName', array(), 'x' ), $this->deterministic->hash( 'lastName', array(), 'x' ) );
		$this->assertNotSame( $this->deterministic->hash( 'date', array( 'Y' ), 'x' ), $this->deterministic->hash( 'date', array( 'Y-m-d' ), 'x' ) );
	}

	public function test_randomness_is_restored_afterwards() {
		$this->deterministic->generate( $this->faker, 'firstName', array(), 'Alice' );
		$first = mt_rand();
		$this->deterministic->generate( $this->faker, 'firstName', array(), 'Alice' );

		$this->assertNotSame( $first, mt_rand() );
	}

	public function test_applies() {
		$this->assertTrue( $this->deterministic->applies( 'email', 'x' ) );
		$this->assertFalse( $this->deterministic->applies( 'password', 'x' ) );
		$this->assertFalse( $this->deterministic->applies( 'email', null ) );
		$this->assertFalse( ( new Deterministic( '' ) )->applies( 'email', 'x' ) );
	}

	public function test_rule_anonymize_is_deterministic_with_original() {
		$rule = new Rule( 'users', 'user_email' );
		$rule->load( array( 'fake_data_type' => 'email' ) );

		$this->assertSame(
			$rule->anonymize( $this->faker, 'foo@bar.com', $this->deterministic ),
			$rule->anonymize( $this->faker, 'FOO@bar.com', $this->deterministic )
		);
		$this->assertNotSame(
			$rule->anonymize( $this->faker, 'foo@bar.com', new Deterministic( '' ) ),
			$rule->anonymize( $this->faker, 'foo@bar.com', new Deterministic( '' ) )
		);
	}

	public function test_constraint_values() {
		$rule = new Rule( 'usermeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_key' => 'billing_email' ), 'fake_data_type' => 'email' ) );
		$this->assertSame( array( 'meta_key', array( 'billing_email' ) ), $rule->get_constraint_values() );

		$rule = new Rule( 'postmeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_key' => array( 'in' => array( '_billing_city', '_shipping_city' ) ) ), 'fake_data_type' => 'city' ) );
		$this->assertSame( array( 'meta_key', array( '_billing_city', '_shipping_city' ) ), $rule->get_constraint_values() );

		$rule = new Rule( 'postmeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_key' => array( '_billing_city', 5 ) ), 'fake_data_type' => 'city' ) );
		$this->assertSame( array( 'meta_key', array( '_billing_city', '5' ) ), $rule->get_constraint_values() );

		$rule = new Rule( 'postmeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_key' => array( 'in' => array( 'tel-1', 'tel-2' ), 'regex' => '/^tel-1$/' ) ), 'fake_data_type' => 'phoneNumber' ) );
		$this->assertSame( array( 'meta_key', array( 'tel-1', 'tel-2' ) ), $rule->get_constraint_values() );

		$rule = new Rule( 'usermeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_key' => array( 'regex' => '/_stripe_customer_id$/' ) ), 'fake_data_type' => 'ean13' ) );
		$this->assertNull( $rule->get_constraint_values() );

		$rule = new Rule( 'users', 'user_email' );
		$rule->load( array( 'constraint' => 'WPMDB\\Anonymization\\Config\\Constraint::is_not_whitelisted_user', 'fake_data_type' => 'email' ) );
		$this->assertNull( $rule->get_constraint_values() );

		$rule = new Rule( 'comments', 'comment_author' );
		$rule->load( array( 'fake_data_type' => 'userName' ) );
		$this->assertNull( $rule->get_constraint_values() );
	}

	/**
	 * assertMatchesRegularExpression() only exists in PHPUnit 9+.
	 *
	 * @param string $pattern
	 * @param string $value
	 */
	protected function assertMatchesPattern( $pattern, $value ) {
		$this->assertSame( 1, preg_match( $pattern, $value ), "$value does not match $pattern" );
	}
}
