<?php

namespace WPMDB\Anonymization\Tests\Unit\Config;

use WPMDB\Anonymization\Config\Rule;
use WPMDB\Anonymization\Tests\Doubles\FakerSpy;
use WPMDB\Anonymization\Tests\TestCase;

class ConfigTest extends TestCase {

	protected function sample_data() {
		return array(
			'users'    => array(
				'user_email' => array(
					'fake_data_type' => 'email',
				),
			),
			'usermeta' => array(
				'meta_value' => array(
					array(
						'constraint'     => array( 'meta_key' => 'first_name' ),
						'fake_data_type' => 'firstName',
					),
					array(
						'constraint'     => array( 'meta_key' => 'last_name' ),
						'fake_data_type' => 'lastName',
					),
				),
			),
		);
	}

	public function test_has_table_reflects_initialised_tables() {
		$config = $this->make_config( $this->sample_data() );

		$this->assertTrue( $config->has_table( 'users' ) );
		$this->assertTrue( $config->has_table( 'usermeta' ) );
		$this->assertFalse( $config->has_table( 'posts' ) );
		$this->assertFalse( $config->has_table( 'wp_users' ), 'Table names are matched without the prefix.' );
	}

	public function test_init_replaces_previous_data() {
		$config = $this->make_config( $this->sample_data() );
		$config->init( array( 'comments' => array( 'comment_author' => array( 'fake_data_type' => 'userName' ) ) ) );

		$this->assertFalse( $config->has_table( 'users' ) );
		$this->assertTrue( $config->has_table( 'comments' ) );
	}

	public function test_has_column() {
		$config = $this->make_config( $this->sample_data() );

		$this->assertTrue( $config->has_column( 'users', 'user_email' ) );
		$this->assertTrue( $config->has_column( 'usermeta', 'meta_value' ) );
		$this->assertFalse( $config->has_column( 'users', 'user_login' ) );
		$this->assertFalse( $config->has_column( 'posts', 'post_title' ), 'Unknown table has no columns.' );
	}

	public function test_rules_wraps_a_single_rule_in_an_array() {
		$config = $this->make_config( $this->sample_data() );

		$rules = $config->rules( 'users', 'user_email' );

		$this->assertIsArray( $rules );
		$this->assertCount( 1, $rules );
		$this->assertInstanceOf( Rule::class, $rules[0] );
	}

	public function test_rules_returns_a_rule_per_entry_in_order() {
		$config = $this->make_config( $this->sample_data() );

		$rules = $config->rules( 'usermeta', 'meta_value' );

		$this->assertCount( 2, $rules );
		$this->assertContainsOnlyInstancesOf( Rule::class, $rules );

		$first_name_row = $this->row( array( 'meta_key' => 'first_name' ) );
		$this->assertTrue( $rules[0]->valid( $first_name_row ) );
		$this->assertFalse( $rules[1]->valid( $first_name_row ) );
	}

	public function test_rules_load_rule_data() {
		$config = $this->make_config( $this->sample_data() );

		$rules = $config->rules( 'usermeta', 'meta_value' );
		$faker = new FakerSpy();

		$this->assertSame( 'firstName', $rules[0]->anonymize( $faker ) );
		$this->assertSame( 'lastName', $rules[1]->anonymize( $faker ) );
	}

	/**
	 * @dataProvider clean_table_provider
	 */
	public function test_clean_table( $base_prefix, $table, $expected ) {
		$config = $this->make_config( array(), $base_prefix );

		$this->assertSame( $expected, $config->clean_table( $table ) );
	}

	public static function clean_table_provider() {
		return array(
			'default prefix'          => array( 'wp_', 'wp_users', 'users' ),
			'custom prefix'           => array( 'abc_', 'abc_usermeta', 'usermeta' ),
			'migration temp table'    => array( 'wp_', '_mig_wp_users', 'users' ),
			'already clean'           => array( 'wp_', 'comments', 'comments' ),
			'other prefix untouched'  => array( 'wp_', 'abc_users', 'abc_users' ),
		);
	}

	public function test_clean_table_strips_multisite_subsite_prefix() {
		$config = $this->make_config( array(), 'wp_' );

		$this->assertSame( 'comments', $config->clean_table( 'wp_2_comments' ) );
		$this->assertSame( 'postmeta', $config->clean_table( '_mig_wp_2_postmeta' ) );
	}
}
