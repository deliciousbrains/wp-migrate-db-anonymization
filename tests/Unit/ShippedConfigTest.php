<?php

namespace WPMDB\Anonymization\Tests\Unit;

use WPMDB\Anonymization\Config\Rule;
use WPMDB\Anonymization\Tests\TestCase;

/**
 * Sanity checks for every rule shipped in config/*.php.
 *
 * New config files are picked up automatically.
 */
class ShippedConfigTest extends TestCase {

	/**
	 * Keys a rule may define, matching the properties Rule::load() accepts.
	 *
	 * @var string[]
	 */
	protected static $rule_keys = array( 'fake_data_type', 'fake_data_args', 'post_process_function', 'constraint' );

	/**
	 * One data set per rule: file, table, column, rule index and rule data.
	 *
	 * @return array
	 */
	public static function rule_provider() {
		$sets = array();
		foreach ( self::shipped_config_files() as $file => $tables ) {
			foreach ( $tables as $table => $columns ) {
				foreach ( $columns as $column => $rules ) {
					if ( ! isset( $rules[0] ) || ! is_array( $rules[0] ) ) {
						$rules = array( $rules );
					}

					foreach ( $rules as $index => $rule ) {
						$sets[ "{$file} {$table}.{$column}[{$index}]" ] = array( $table, $column, $rule );
					}
				}
			}
		}

		return $sets;
	}

	public function test_config_files_exist_and_return_arrays() {
		$files = self::shipped_config_files();

		$this->assertNotEmpty( $files );
		foreach ( $files as $file => $data ) {
			$this->assertIsArray( $data, "{$file} returns an array." );
			$this->assertNotEmpty( $data, "{$file} is not empty." );
		}
	}

	/**
	 * @dataProvider rule_provider
	 */
	public function test_rule_only_uses_known_keys( $table, $column, array $rule ) {
		$this->assertSame( array(), array_diff( array_keys( $rule ), self::$rule_keys ) );
	}

	/**
	 * @dataProvider rule_provider
	 */
	public function test_fake_data_type_is_a_faker_formatter( $table, $column, array $rule ) {
		$this->assertArrayHasKey( 'fake_data_type', $rule );
		$this->assertIsString( $rule['fake_data_type'] );

		$args = isset( $rule['fake_data_args'] ) && is_array( $rule['fake_data_args'] ) ? $rule['fake_data_args'] : array();

		// Throws InvalidArgumentException if the formatter does not exist.
		$value = call_user_func_array( array( $this->make_faker(), $rule['fake_data_type'] ), $args );

		$this->assertNotNull( $value );
		$this->assertNotSame( '', $value );
	}

	/**
	 * @dataProvider rule_provider
	 */
	public function test_callbacks_are_callable( $table, $column, array $rule ) {
		if ( isset( $rule['post_process_function'] ) ) {
			$this->assertTrue( is_callable( $rule['post_process_function'] ), 'post_process_function is callable.' );
		}

		if ( isset( $rule['constraint'] ) && is_string( $rule['constraint'] ) ) {
			$this->assertTrue( is_callable( $rule['constraint'] ), 'String constraint is callable.' );
		}

		if ( isset( $rule['constraint'] ) && is_array( $rule['constraint'] ) ) {
			$this->assertCount( 1, $rule['constraint'], 'Column constraints compare a single column.' );
		}

		$this->addToAssertionCount( 1 );
	}

	/**
	 * @dataProvider rule_provider
	 */
	public function test_rule_anonymizes_with_real_faker( $table, $column, array $rule ) {
		$object = new Rule( $table, $column );
		$object->load( $rule );

		$value = $object->anonymize( $this->make_faker() );

		$this->assertNotSame( '', $value );
		$this->assertTrue( is_scalar( $value ) || $value instanceof \DateTimeInterface, 'Anonymized value can be stored in a column.' );
	}
}
