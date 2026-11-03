<?php

namespace WPMDB\Anonymization\Tests\Unit\Config;

use WPMDB\Anonymization\Config\Rule;
use WPMDB\Anonymization\Tests\Doubles\FakerSpy;
use WPMDB\Anonymization\Tests\TestCase;

/**
 * Global-scope style callback used as a string constraint.
 */
function rule_test_constraint_rejects_admin( $row ) {
	return ! ( isset( $row->user_login ) && 'admin' === $row->user_login );
}

class RuleTest extends TestCase {

	/**
	 * @param array  $data
	 * @param string $table
	 * @param string $column
	 *
	 * @return Rule
	 */
	protected function make_rule( array $data, $table = 'usermeta', $column = 'meta_value' ) {
		$rule = new Rule( $table, $column );
		$rule->load( $data );

		return $rule;
	}

	public static function constraint_rejects_editor( $row ) {
		return ! ( isset( $row->user_login ) && 'editor' === $row->user_login );
	}

	public function test_rule_without_constraint_is_always_valid() {
		$rule = $this->make_rule( array( 'fake_data_type' => 'email' ) );

		$this->assertTrue( $rule->valid( $this->row( array() ) ) );
		$this->assertTrue( $rule->valid( $this->row( array( 'meta_key' => 'anything' ) ) ) );
	}

	public function test_column_constraint_matches_row_value() {
		$rule = $this->make_rule(
			array(
				'constraint'     => array( 'meta_key' => 'billing_email' ),
				'fake_data_type' => 'email',
			)
		);

		$this->assertTrue( $rule->valid( $this->row( array( 'meta_key' => 'billing_email' ) ) ) );
		$this->assertFalse( $rule->valid( $this->row( array( 'meta_key' => 'billing_phone' ) ) ) );
	}

	public function test_column_constraint_is_strict() {
		$rule = $this->make_rule( array( 'constraint' => array( 'meta_key' => '1' ) ) );

		$this->assertFalse( $rule->valid( $this->row( array( 'meta_key' => 1 ) ) ) );
		$this->assertTrue( $rule->valid( $this->row( array( 'meta_key' => '1' ) ) ) );
	}

	public function test_column_constraint_passes_when_row_lacks_the_column() {
		$rule = $this->make_rule( array( 'constraint' => array( 'meta_key' => 'billing_email' ) ) );

		$this->assertTrue( $rule->valid( $this->row( array( 'user_login' => 'someone' ) ) ) );
	}

	public function test_function_name_constraint_is_called_with_row() {
		$rule = $this->make_rule( array( 'constraint' => __NAMESPACE__ . '\\rule_test_constraint_rejects_admin' ) );

		$this->assertFalse( $rule->valid( $this->row( array( 'user_login' => 'admin' ) ) ) );
		$this->assertTrue( $rule->valid( $this->row( array( 'user_login' => 'someone' ) ) ) );
	}

	public function test_static_method_string_constraint_is_called_with_row() {
		$rule = $this->make_rule( array( 'constraint' => __CLASS__ . '::constraint_rejects_editor' ) );

		$this->assertFalse( $rule->valid( $this->row( array( 'user_login' => 'editor' ) ) ) );
		$this->assertTrue( $rule->valid( $this->row( array( 'user_login' => 'someone' ) ) ) );
	}

	public function test_bare_method_name_resolves_to_default_constraint_class() {
		add_filter( 'wpmdb_anonymization_user_whitelisted', '__return_true' );
		$rule = $this->make_rule( array( 'constraint' => 'is_not_whitelisted_user' ), 'users', 'user_email' );

		$this->assertFalse( $rule->valid( $this->row( array( 'user_login' => 'someone' ) ) ) );
	}

	public function test_unknown_string_constraint_is_ignored() {
		$rule = $this->make_rule( array( 'constraint' => 'this_constraint_does_not_exist' ) );

		$this->assertTrue( $rule->valid( $this->row( array( 'user_login' => 'someone' ) ) ) );
	}

	public function test_pre_rule_valid_filter_can_reject_a_row() {
		add_filter( 'wpmdb_anonymization_pre_rule_valid', '__return_false' );
		$rule = $this->make_rule( array( 'fake_data_type' => 'email' ) );

		$this->assertFalse( $rule->valid( $this->row( array() ) ) );
	}

	public function test_pre_rule_valid_filter_receives_rule_and_row() {
		$received = array();
		add_filter(
			'wpmdb_anonymization_pre_rule_valid',
			function ( $valid, $rule, $row ) use ( &$received ) {
				$received = array( $valid, $rule, $row );

				return $valid;
			},
			10,
			3
		);

		$rule = $this->make_rule( array( 'fake_data_type' => 'email' ) );
		$row  = $this->row( array( 'meta_key' => 'billing_email' ) );
		$rule->valid( $row );

		$this->assertTrue( $received[0] );
		$this->assertSame( $rule, $received[1] );
		$this->assertSame( $row, $received[2] );
	}

	public function test_pre_rule_valid_filter_returning_true_still_applies_constraint() {
		add_filter( 'wpmdb_anonymization_pre_rule_valid', '__return_true' );
		$rule = $this->make_rule( array( 'constraint' => array( 'meta_key' => 'billing_email' ) ) );

		$this->assertFalse( $rule->valid( $this->row( array( 'meta_key' => 'other' ) ) ) );
	}

	public function test_load_ignores_unknown_keys() {
		$rule = $this->make_rule(
			array(
				'fake_data_type' => 'email',
				'not_a_property' => 'ignored',
			)
		);

		$this->assertFalse( property_exists( $rule, 'not_a_property' ) );
		$this->assertSame( 'email', $rule->anonymize( new FakerSpy() ) );
	}

	public function test_anonymize_without_fake_data_type_returns_empty_string() {
		$faker = new FakerSpy();
		$rule  = $this->make_rule( array( 'constraint' => array( 'meta_key' => 'x' ) ) );

		$this->assertSame( '', $rule->anonymize( $faker ) );
		$this->assertSame( array(), $faker->calls, 'Faker is not called.' );
	}

	public function test_anonymize_calls_the_faker_formatter() {
		$faker = new FakerSpy();
		$rule  = $this->make_rule( array( 'fake_data_type' => 'firstName' ) );

		$this->assertSame( 'firstName', $rule->anonymize( $faker ) );
		$this->assertSame( array( array( 'firstName', array() ) ), $faker->calls );
	}

	public function test_anonymize_passes_fake_data_args() {
		$faker = new FakerSpy();
		$rule  = $this->make_rule(
			array(
				'fake_data_type' => 'date',
				'fake_data_args' => array( 'Y-m-d' ),
			)
		);

		$rule->anonymize( $faker );

		$this->assertSame( array( array( 'date', array( 'Y-m-d' ) ) ), $faker->calls );
	}

	public function test_anonymize_ignores_non_array_fake_data_args() {
		$faker = new FakerSpy();
		$rule  = $this->make_rule(
			array(
				'fake_data_type' => 'date',
				'fake_data_args' => 'Y-m-d',
			)
		);

		$rule->anonymize( $faker );

		$this->assertSame( array( array( 'date', array() ) ), $faker->calls );
	}

	public function test_anonymize_with_real_faker_and_args() {
		$rule = $this->make_rule(
			array(
				'fake_data_type' => 'numberBetween',
				'fake_data_args' => array( 7, 7 ),
			)
		);

		$this->assertSame( 7, $rule->anonymize( $this->make_faker() ) );
	}

	public function test_anonymize_applies_post_process_function() {
		$rule = $this->make_rule(
			array(
				'fake_data_type'        => 'firstName',
				'post_process_function' => 'strtoupper',
			)
		);

		$this->assertSame( 'FIRSTNAME', $rule->anonymize( new FakerSpy() ) );
	}

	public function test_anonymize_applies_static_method_post_process_function() {
		$rule = $this->make_rule(
			array(
				'fake_data_type'        => 'password',
				'post_process_function' => 'WPMDB\\Anonymization\\Config\\Replacement::password',
			)
		);

		$this->assertSame( 'hashed:password', $rule->anonymize( new FakerSpy() ) );
	}

	public function test_anonymize_ignores_non_callable_post_process_function() {
		$rule = $this->make_rule(
			array(
				'fake_data_type'        => 'firstName',
				'post_process_function' => 'not_a_real_function',
			)
		);

		$this->assertSame( 'firstName', $rule->anonymize( new FakerSpy() ) );
	}
}
