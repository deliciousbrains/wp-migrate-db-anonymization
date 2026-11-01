<?php

namespace WPMDB\Anonymization\Tests\Unit;

use WPMDB\Anonymization\Migration;
use WPMDB\Anonymization\Tests\TestCase;

class MigrationTest extends TestCase {

	protected function config_data() {
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
						'fake_data_type' => 'numberBetween',
						'fake_data_args' => array( 42, 42 ),
					),
				),
			),
		);
	}

	/**
	 * Stand-in for WP Migrate's replace object passed to the filter.
	 *
	 * @return object
	 */
	protected function replace( $intent, $table, $column, $row ) {
		return new class( $intent, $table, $column, $row ) {
			private $intent;
			private $table;
			private $column;
			private $row;

			public function __construct( $intent, $table, $column, $row ) {
				$this->intent = $intent;
				$this->table  = $table;
				$this->column = $column;
				$this->row    = $row;
			}

			public function get_intent() {
				return $this->intent;
			}

			public function get_table() {
				return $this->table;
			}

			public function get_column() {
				return $this->column;
			}

			public function get_row() {
				return $this->row;
			}
		};
	}

	protected function make_migration() {
		return new Migration( $this->make_config( $this->config_data() ), $this->make_faker() );
	}

	public function test_register_hooks_into_wp_migrate() {
		$this->make_migration()->register();

		$this->assertTrue( has_filter( 'wpmdb_after_replace_custom_data' ) );
	}

	/**
	 * @dataProvider intent_provider
	 */
	public function test_anonymizes_for_export_push_and_pull( $intent, $anonymized ) {
		$row     = $this->row( array( 'meta_key' => 'first_name' ) );
		$replace = $this->replace( $intent, 'wp_usermeta', 'meta_value', $row );

		$result = $this->make_migration()->hook_wpmdb_after_replace_custom_data( 'Jane', false, $replace );

		$this->assertSame( $anonymized ? 42 : 'Jane', $result );
	}

	public static function intent_provider() {
		return array(
			'export'          => array( 'savefile', true ),
			'pull'            => array( 'pull', true ),
			'push'            => array( 'push', true ),
			'find & replace'  => array( 'find_replace', false ),
			'import'          => array( 'import', false ),
		);
	}

	public function test_handles_migration_temp_tables() {
		$replace = $this->replace( 'pull', '_mig_wp_usermeta', 'meta_value', $this->row( array( 'meta_key' => 'first_name' ) ) );

		$this->assertSame( 42, $this->make_migration()->hook_wpmdb_after_replace_custom_data( 'Jane', false, $replace ) );
	}

	public function test_empty_data_is_left_alone() {
		$replace = $this->replace( 'pull', 'wp_usermeta', 'meta_value', $this->row( array( 'meta_key' => 'first_name' ) ) );

		$this->assertSame( '', $this->make_migration()->hook_wpmdb_after_replace_custom_data( '', false, $replace ) );
	}

	public function test_unconfigured_table_or_column_is_left_alone() {
		$migration = $this->make_migration();

		$posts = $this->replace( 'pull', 'wp_posts', 'post_title', $this->row( array() ) );
		$this->assertSame( 'Hello', $migration->hook_wpmdb_after_replace_custom_data( 'Hello', false, $posts ) );

		$login = $this->replace( 'pull', 'wp_users', 'user_login', $this->row( array( 'user_login' => 'jane' ) ) );
		$this->assertSame( 'jane', $migration->hook_wpmdb_after_replace_custom_data( 'jane', false, $login ) );
	}

	public function test_row_not_matching_any_rule_is_left_alone() {
		$replace = $this->replace( 'pull', 'wp_usermeta', 'meta_value', $this->row( array( 'meta_key' => 'session_tokens' ) ) );

		$this->assertSame( 'a:0:{}', $this->make_migration()->hook_wpmdb_after_replace_custom_data( 'a:0:{}', false, $replace ) );
	}

	public function test_single_rule_column_is_anonymized() {
		$replace = $this->replace( 'savefile', 'wp_users', 'user_email', $this->row( array( 'user_login' => 'jane' ) ) );

		$result = $this->make_migration()->hook_wpmdb_after_replace_custom_data( 'jane@example.com', false, $replace );

		$this->assertNotSame( 'jane@example.com', $result );
		$this->assertNotFalse( filter_var( $result, FILTER_VALIDATE_EMAIL ) );
	}
}
