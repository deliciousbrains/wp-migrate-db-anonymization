<?php

namespace WPMDB\Anonymization\Tests;

use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WPMDB\Anonymization\Config\Config;

/**
 * Base test case with shared helpers.
 */
abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		wpmdb_anonymization_tests_reset_hooks();
		wpmdb_anonymization_tests_set_environment_type( 'local' );
	}

	protected function tearDown(): void {
		wpmdb_anonymization_tests_reset_hooks();
		wpmdb_anonymization_tests_set_environment_type( 'local' );
		parent::tearDown();
	}

	/**
	 * @param string $base_prefix
	 *
	 * @return \wpdb
	 */
	protected function make_wpdb( $base_prefix = 'wp_' ) {
		return new \wpdb( $base_prefix );
	}

	/**
	 * @param array  $data
	 * @param string $base_prefix
	 *
	 * @return Config
	 */
	protected function make_config( array $data = array(), $base_prefix = 'wp_' ) {
		$config = new Config( $this->make_wpdb( $base_prefix ), new \stdClass() );
		$config->init( $data );

		return $config;
	}

	/**
	 * @param int $seed
	 *
	 * @return Generator
	 */
	protected function make_faker( $seed = 1234 ) {
		$faker = Factory::create();
		$faker->seed( $seed );

		return $faker;
	}

	/**
	 * Build a database row object.
	 *
	 * @param array $columns
	 *
	 * @return object
	 */
	protected function row( array $columns ) {
		return (object) $columns;
	}

	/**
	 * Every config array shipped in config/*.php, keyed by file name.
	 *
	 * @return array
	 */
	protected static function shipped_config_files() {
		$files = glob( WPMDB_ANONYMIZATION_PLUGIN_DIR . '/config/*.php' );
		$data  = array();
		foreach ( $files as $file ) {
			$data[ basename( $file ) ] = include $file;
		}

		return $data;
	}
}
