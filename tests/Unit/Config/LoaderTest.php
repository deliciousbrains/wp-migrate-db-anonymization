<?php

namespace WPMDB\Anonymization\Tests\Unit\Config;

use WPMDB\Anonymization\Config\Loader;
use WPMDB\Anonymization\Tests\Doubles\FakerSpy;
use WPMDB\Anonymization\Tests\Doubles\InspectableConfig;
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
}
