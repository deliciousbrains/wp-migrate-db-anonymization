<?php

namespace WPMDB\Anonymization\Tests\Unit\Config;

use WPMDB\Anonymization\Config\Rule;
use WPMDB\Anonymization\Tests\TestCase;

class RuleLikePatternsTest extends TestCase {

	/**
	 * @dataProvider convertible_provider
	 */
	public function test_converts_simple_regexes( $regex, $expected ) {
		$this->assertSame( $expected, Rule::regex_to_like_patterns( $regex ) );
	}

	public function convertible_provider() {
		return array(
			'suffix'                  => array( '/_stripe_customer_id$/', array( '%!_stripe!_customer!_id', "%!_stripe!_customer!_id\n" ) ),
			'prefix'                  => array( '/^billing_/', array( 'billing!_%' ) ),
			'exact'                   => array( '/^tel-1$/', array( 'tel-1', "tel-1\n" ) ),
			'contains'                => array( '/vat/', array( '%vat%' ) ),
			'optional alternatives'   => array( '/_wcpay_customer_id(_live|_test)?$/', array( '%!_wcpay!_customer!_id!_live', "%!_wcpay!_customer!_id!_live\n", '%!_wcpay!_customer!_id!_test', "%!_wcpay!_customer!_id!_test\n", '%!_wcpay!_customer!_id', "%!_wcpay!_customer!_id\n" ) ),
			'required alternatives'   => array( '/^edd_(public|secret)_key$/', array( 'edd!_public!_key', "edd!_public!_key\n", 'edd!_secret!_key', "edd!_secret!_key\n" ) ),
			'escaped punctuation'     => array( '/x\.y$/', array( '%x.y', "%x.y\n" ) ),
			'escaped dollar not end'  => array( '/price\$/', array( '%price$%' ) ),
			'escaped pipe in group'   => array( '/^(foo\|bar|baz)$/', array( 'foo|bar', "foo|bar\n", 'baz', "baz\n" ) ),
			'escaped pipe outside'    => array( '/^a\|b$/', array( 'a|b', "a|b\n" ) ),
			'escaped backslash, pipe' => array( '/^(a\\\\|b)$/', array( 'a\\', "a\\\n", 'b', "b\n" ) ),
		);
	}

	/**
	 * @dataProvider unconvertible_provider
	 */
	public function test_returns_null_when_it_cannot_be_done_safely( $regex ) {
		$this->assertNull( Rule::regex_to_like_patterns( $regex ) );
	}

	public function unconvertible_provider() {
		return array(
			'flag'            => array( '/foo/i' ),
			'wildcard'        => array( '/a.b/' ),
			'class'           => array( '/^\d+(\.\d+)?$/' ),
			'quantifier'      => array( '/^tel-\d+$/' ),
			'two groups'      => array( '/(a|b)(c|d)/' ),
			'other delimiter' => array( '#foo#' ),
			'empty'           => array( '/^$/' ),
			'like characters' => array( '/a!b%c/' ),
			'not a string'    => array( null ),
		);
	}

	public function test_rule_returns_patterns_only_for_regex_only_constraints() {
		$rule = new Rule( 'usermeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_key' => array( 'regex' => '/_stripe_customer_id$/' ) ), 'fake_data_type' => 'ean13' ) );
		$this->assertSame( array( 'meta_key', array( '%!_stripe!_customer!_id', "%!_stripe!_customer!_id\n" ) ), $rule->get_constraint_like_patterns() );

		// A list with a regex is already pushed down as the list.
		$rule = new Rule( 'usermeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_key' => array( 'in' => array( 'a_stripe_customer_id' ), 'regex' => '/_stripe_customer_id$/' ) ), 'fake_data_type' => 'ean13' ) );
		$this->assertNull( $rule->get_constraint_like_patterns() );

		$rule = new Rule( 'usermeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_key' => 'billing_email' ), 'fake_data_type' => 'email' ) );
		$this->assertNull( $rule->get_constraint_like_patterns() );
	}

	public function test_multi_column_rule_returns_patterns_for_the_first_convertible_regex() {
		// The first regex can't be converted, the second can.
		$rule = new Rule( 'postmeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_value' => array( 'regex' => '/^(?!public$)/' ), 'meta_key' => array( 'regex' => '/^_edd_log_(key|token)$/' ) ), 'fake_data_type' => 'md5' ) );
		$this->assertSame( array( 'meta_key', array( '!_edd!_log!_key', "!_edd!_log!_key\n", '!_edd!_log!_token', "!_edd!_log!_token\n" ) ), $rule->get_constraint_like_patterns() );

		$rule = new Rule( 'postmeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_key' => array( 'regex' => '/_stripe_customer_id$/' ), 'meta_value' => array( 'regex' => '/^cus_/' ) ), 'fake_data_type' => 'ean13' ) );
		$this->assertSame( array( 'meta_key', array( '%!_stripe!_customer!_id', "%!_stripe!_customer!_id\n" ) ), $rule->get_constraint_like_patterns() );

		// Another column has exact values, which get_constraint_values() pushes down instead.
		$rule = new Rule( 'postmeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_value' => array( 'regex' => '/^cus_/' ), 'meta_key' => 'customer_email' ), 'fake_data_type' => 'email' ) );
		$this->assertNull( $rule->get_constraint_like_patterns() );

		// No regex can be converted.
		$rule = new Rule( 'postmeta', 'meta_value' );
		$rule->load( array( 'constraint' => array( 'meta_key' => array( 'regex' => '/^tel-\d+$/' ), 'meta_value' => array( 'regex' => '/@/i' ) ), 'fake_data_type' => 'email' ) );
		$this->assertNull( $rule->get_constraint_like_patterns() );
	}
}
