<?php

namespace WPMDB\Anonymization;

use Faker\Generator;
use WPMDB\Anonymization\Config\Config;

class Migration {

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * @var Generator
	 */
	protected $faker;

	/**
	 * Migration constructor.
	 *
	 * @param Config    $config
	 * @param Generator $faker
	 */
	public function __construct( Config $config, Generator $faker ) {
		$this->config = $config;
		$this->faker  = $faker;
	}

	/**
	 * Register hooks
	 */
	public function register() {
		add_filter( 'wpmdb_after_replace_custom_data', array( $this, 'hook_wpmdb_after_replace_custom_data' ), 10, 3 );
	}

	/**
	 * @param array $data
	 * @param bool  $before_fired
	 * @param       $wpmdb_replace
	 *
	 * @return array
	 */
	public function hook_wpmdb_after_replace_custom_data( $data, $before_fired, $wpmdb_replace ) {
		if ( ! $this->is_allowed_migration_type( $wpmdb_replace->get_intent() ) ) {
			return $data;
		}

		if ( empty( $data ) ) {
			return $data;
		}

		$table = $this->config->clean_table( $wpmdb_replace->get_table() );

		if ( ! $this->config->has_table( $table ) ) {
			return $data;
		}

		$column = $wpmdb_replace->get_column();
		if ( ! $this->config->has_column( $table, $column ) ) {
			return $data;
		}

		$row = $wpmdb_replace->get_row();
		foreach ( $this->config->rules( $table, $column ) as $rule ) {
			if ( ! $rule->valid( $row ) ) {
				continue;
			}

			return $rule->anonymize( $this->faker );
		}

		return $data;
	}

	/**
	 * @param $intent
	 *
	 * @return bool
	 */
	protected function is_allowed_migration_type( $intent ) {
		return in_array( $intent, array( 'savefile', 'pull', 'push' ) );
	}
}