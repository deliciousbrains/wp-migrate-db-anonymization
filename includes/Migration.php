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
	 * @var Anonymizer
	 */
	protected $anonymizer;

	/**
	 * Migration constructor.
	 *
	 * @param Config          $config
	 * @param Generator       $faker
	 * @param Anonymizer|null $anonymizer
	 */
	public function __construct( Config $config, Generator $faker, $anonymizer = null ) {
		$this->config     = $config;
		$this->faker      = $faker;
		$this->anonymizer = $anonymizer instanceof Anonymizer ? $anonymizer : new Anonymizer( $config, $faker );
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

		$row  = $wpmdb_replace->get_row();
		$rule = $this->anonymizer->matching_rule( $table, $column, $row );
		if ( null === $rule ) {
			return $data;
		}

		return $this->anonymizer->generate( $rule, $this->original_value( $data, $row, $column ) );
	}

	/**
	 * The value to derive deterministic output from. Prefer the raw column value
	 * from the source row (before WP Migrate's find & replace) so the result
	 * matches the WP-CLI command run on the same data; fall back to $data.
	 *
	 * @param mixed  $data
	 * @param object $row
	 * @param string $column
	 *
	 * @return mixed
	 */
	protected function original_value( $data, $row, $column ) {
		if ( is_object( $row ) && isset( $row->{$column} ) && is_scalar( $row->{$column} ) && '' !== $row->{$column} ) {
			return $row->{$column};
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