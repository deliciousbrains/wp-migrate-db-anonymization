<?php

namespace WPMDB\Anonymization\Tests\Unit\Config;

use WPMDB\Anonymization\Config\Loader;
use WPMDB\Anonymization\Tests\Doubles\FakerSpy;
use WPMDB\Anonymization\Tests\Doubles\InspectableConfig;
use WPMDB\Anonymization\Tests\Doubles\SqliteWpdb;
use WPMDB\Anonymization\Tests\TestCase;

class LoaderTest extends TestCase {

	/**
	 * The Loader reads config/*.php next to the plugin file, so point it at a
	 * (non-existent) plugin file inside a fixture directory.
	 *
	 * @param string $fixture
	 *
	 * @return Loader
	 */
	protected function make_loader( $fixture ) {
		return new Loader( WPMDB_ANONYMIZATION_TESTS_DIR . '/fixtures/' . $fixture . '/plugin.php' );
	}

	/**
	 * @return InspectableConfig
	 */
	protected function make_inspectable_config() {
		return new InspectableConfig( $this->make_wpdb(), new \stdClass() );
	}

	public function test_init_returns_the_given_config() {
		$config = $this->make_inspectable_config();

		$this->assertSame( $config, $this->make_loader( 'plugin-merge' )->init( $config ) );
	}

	public function test_merges_tables_from_all_config_files() {
		$config = $this->make_loader( 'plugin-merge' )->init( $this->make_inspectable_config() );

		$this->assertTrue( $config->has_table( 'users' ) );
		$this->assertTrue( $config->has_table( 'usermeta' ) );
		$this->assertTrue( $config->has_table( 'postmeta' ) );
		$this->assertTrue( $config->has_column( 'postmeta', 'meta_value' ) );
	}

	public function test_appends_rule_lists_for_the_same_column() {
		$config = $this->make_loader( 'plugin-merge' )->init( $this->make_inspectable_config() );

		$data = $config->data();
		$this->assertCount( 2, $data['usermeta']['meta_value'] );
		$this->assertSame( array( 'meta_key' => 'first_name' ), $data['usermeta']['meta_value'][0]['constraint'] );
		$this->assertSame( array( 'meta_key' => 'billing_phone' ), $data['usermeta']['meta_value'][1]['constraint'] );

		$rules = $config->rules( 'usermeta', 'meta_value' );
		$this->assertCount( 2, $rules );
	}

	public function test_config_filter_receives_merged_data() {
		$received = null;
		add_filter(
			'wpmdb_anonymization_config',
			function ( $data ) use ( &$received ) {
				$received = $data;

				return $data;
			}
		);

		$config = $this->make_loader( 'plugin-merge' )->init( $this->make_inspectable_config() );
		$this->assertNull( $received, 'Config is loaded on first use, not by init().' );

		$config->has_table( 'users' );

		$this->assertIsArray( $received );
		$this->assertSame( array( 'users', 'usermeta', 'postmeta' ), array_keys( $received ) );
	}

	public function test_config_filter_can_add_rules() {
		add_filter(
			'wpmdb_anonymization_config',
			function ( $config ) {
				$config['usermeta']['meta_value'][] = array(
					'constraint'     => array( 'meta_key' => 'dob' ),
					'fake_data_type' => 'dateTimeThisCentury',
				);
				$config['comments']['comment_author_email'] = array(
					'fake_data_type' => 'email',
				);

				return $config;
			}
		);

		$config = $this->make_loader( 'plugin-merge' )->init( $this->make_inspectable_config() );

		$this->assertTrue( $config->has_column( 'comments', 'comment_author_email' ) );

		$rules = $config->rules( 'usermeta', 'meta_value' );
		$this->assertCount( 3, $rules );
		$this->assertTrue( $rules[2]->valid( $this->row( array( 'meta_key' => 'dob' ) ) ) );
		$this->assertSame( 'dateTimeThisCentury', $rules[2]->anonymize( new FakerSpy() ) );
	}

	public function test_config_filter_can_remove_tables() {
		add_filter(
			'wpmdb_anonymization_config',
			function ( $config ) {
				unset( $config['users'] );

				return $config;
			}
		);

		$config = $this->make_loader( 'plugin-merge' )->init( $this->make_inspectable_config() );

		$this->assertFalse( $config->has_table( 'users' ) );
		$this->assertTrue( $config->has_table( 'usermeta' ) );
	}

	public function test_loads_shipped_config_files() {
		$loader = new Loader( WPMDB_ANONYMIZATION_PLUGIN_FILE );
		$config = $loader->init( $this->make_inspectable_config() );

		foreach ( array( 'users', 'usermeta', 'comments', 'postmeta' ) as $table ) {
			$this->assertTrue( $config->has_table( $table ), "Shipped config covers the {$table} table." );
		}

		$expected_usermeta_rules = 0;
		foreach ( self::shipped_config_files() as $file_data ) {
			if ( isset( $file_data['usermeta']['meta_value'] ) ) {
				$expected_usermeta_rules += count( $file_data['usermeta']['meta_value'] );
			}
		}

		$this->assertCount( $expected_usermeta_rules, $config->rules( 'usermeta', 'meta_value' ), 'usermeta rules from every file are combined.' );
	}

	public function test_single_rule_column_defined_in_two_files_merges_into_valid_rules() {
		$config = $this->make_loader( 'plugin-collision' )->init( $this->make_inspectable_config() );

		foreach ( $config->rules( 'users', 'user_email' ) as $rule ) {
			$this->assertIsString( $rule->anonymize( $this->make_faker() ) );
		}
	}

	/**
	 * @param array $files
	 */
	protected function add_config_files( array $files ) {
		add_filter(
			'wpmdb_anonymization_config_files',
			function ( $config_files ) use ( $files ) {
				return array_merge( $config_files, $files );
			}
		);
	}

	/**
	 * @return string
	 */
	protected function private_rules_file() {
		return WPMDB_ANONYMIZATION_TESTS_DIR . '/fixtures/rules/private-rules.php';
	}

	public function test_config_files_filter_receives_bundled_files() {
		$received = null;
		add_filter(
			'wpmdb_anonymization_config_files',
			function ( $files ) use ( &$received ) {
				$received = $files;

				return $files;
			}
		);

		$this->make_loader( 'plugin-merge' )->load( $this->make_wpdb() );

		$dir = WPMDB_ANONYMIZATION_TESTS_DIR . '/fixtures/plugin-merge/config/';
		$this->assertSame( array( $dir . 'a-core.php', $dir . 'b-extension.php' ), $received );
	}

	public function test_config_files_filter_adds_rules_merged_with_bundled_rules() {
		$this->add_config_files( array( $this->private_rules_file() ) );

		$config = $this->make_loader( 'plugin-merge' )->init( $this->make_inspectable_config() );

		$this->assertTrue( $config->has_column( 'my_backup_table', 'email' ) );
		$this->assertTrue( $config->has_column( 'users', 'user_email' ), 'Bundled rules are kept.' );

		$data = $config->data();
		$this->assertCount( 3, $data['usermeta']['meta_value'], 'Rules for the same column from both files apply.' );
		$this->assertSame( array( 'meta_key' => 'first_name' ), $data['usermeta']['meta_value'][0]['constraint'] );
		$this->assertSame( array( 'meta_key' => 'billing_phone' ), $data['usermeta']['meta_value'][1]['constraint'] );
		$this->assertSame( array( 'meta_key' => 'private_phone' ), $data['usermeta']['meta_value'][2]['constraint'] );
	}

	public function test_extra_files_load_without_requirements_while_unmet_bundled_files_do_not() {
		$this->add_config_files( array( $this->private_rules_file() ) );

		// An empty database: no WooCommerce or Easy Digital Downloads data.
		$data = ( new Loader( WPMDB_ANONYMIZATION_PLUGIN_FILE ) )->load( new SqliteWpdb() );

		$this->assertArrayHasKey( 'my_backup_table', $data );
		$this->assertArrayHasKey( 'users', $data );
		$this->assertArrayNotHasKey( 'wc_orders', $data );
		$this->assertArrayNotHasKey( 'edd_customers', $data );

		$shipped        = self::shipped_config_files();
		$expected_rules = count( $shipped['wordpress.php']['usermeta']['meta_value'] ) + 1;
		$this->assertCount( $expected_rules, $data['usermeta']['meta_value'] );
	}

	public function test_load_config_file_filter_can_skip_an_extra_file() {
		$this->add_config_files( array( $this->private_rules_file() ) );
		$received = array();
		add_filter(
			'wpmdb_anonymization_load_config_file',
			function ( $load, $name, $file ) use ( &$received ) {
				$received[ $name ] = $file;

				return 'private-rules' === $name ? false : $load;
			},
			10,
			3
		);

		$data = $this->make_loader( 'plugin-merge' )->load( $this->make_wpdb() );

		$this->assertSame( $this->private_rules_file(), $received['private-rules'] );
		$this->assertArrayNotHasKey( 'my_backup_table', $data );
	}

	public function test_config_files_filter_skips_missing_duplicate_and_invalid_entries() {
		$this->add_config_files(
			array(
				$this->private_rules_file(),
				$this->private_rules_file(),
				WPMDB_ANONYMIZATION_TESTS_DIR . '/fixtures/rules/does-not-exist.php',
				WPMDB_ANONYMIZATION_TESTS_DIR . '/fixtures/rules',
				'',
				42,
				null,
			)
		);

		$data = $this->make_loader( 'plugin-merge' )->load( $this->make_wpdb() );

		$this->assertCount( 3, $data['usermeta']['meta_value'], 'A file added twice is only loaded once.' );
		$this->assertArrayHasKey( 'my_backup_table', $data );
	}

	public function test_extra_file_that_does_not_return_an_array_is_ignored() {
		$this->add_config_files( array( WPMDB_ANONYMIZATION_TESTS_DIR . '/fixtures/rules/not-an-array.php' ) );

		$data = $this->make_loader( 'plugin-merge' )->load( $this->make_wpdb() );

		$this->assertSame( array( 'users', 'usermeta', 'postmeta' ), array_keys( $data ) );
	}

	public function test_non_array_config_files_filter_result_keeps_bundled_files() {
		add_filter( 'wpmdb_anonymization_config_files', '__return_false' );

		$data = $this->make_loader( 'plugin-merge' )->load( $this->make_wpdb() );

		$this->assertSame( array( 'users', 'usermeta', 'postmeta' ), array_keys( $data ) );
	}
}
