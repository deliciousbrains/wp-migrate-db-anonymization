<?php

namespace WPMDB\Anonymization;

use WPMDB\Anonymization\Config\Config;
use WPMDB\Anonymization\Config\Rule;

/**
 * Anonymizes a single value for a table/column/row using the config rules.
 *
 * Shared by the WP Migrate integration (Migration) and the WP-CLI command.
 */
class Anonymizer {

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * @var \Faker\Generator
	 */
	protected $faker;

	/**
	 * @var Deterministic|null
	 */
	protected $deterministic;

	/**
	 * Rule objects per table and column, built once.
	 *
	 * @var array
	 */
	protected $rules = array();

	/**
	 * @param Config             $config
	 * @param \Faker\Generator   $faker
	 * @param Deterministic|null $deterministic Resolved from the environment on first use when null.
	 */
	public function __construct( Config $config, $faker, $deterministic = null ) {
		$this->config        = $config;
		$this->faker         = $faker;
		$this->deterministic = $deterministic;
	}

	/**
	 * Anonymize a value. Returns the value unchanged when it is empty or no
	 * rule applies to the row.
	 *
	 * @param string $table  Unprefixed table name, as used in the config.
	 * @param string $column
	 * @param object $row    The full original row, used by rule constraints.
	 * @param mixed  $value  The original value.
	 *
	 * @return mixed
	 */
	public function anonymize_value( $table, $column, $row, $value ) {
		if ( self::is_empty( $value ) ) {
			return $value;
		}

		$rule = $this->matching_rule( $table, $column, $row );
		if ( null === $rule ) {
			return $value;
		}

		return $this->generate( $rule, $value );
	}

	/**
	 * The first rule for the table/column that is valid for the row.
	 *
	 * @param string $table
	 * @param string $column
	 * @param object $row
	 *
	 * @return Rule|null
	 */
	public function matching_rule( $table, $column, $row ) {
		foreach ( $this->rules( $table, $column ) as $rule ) {
			if ( $rule->valid( $row ) ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Generate the replacement value for an original value using a rule.
	 *
	 * @param Rule  $rule
	 * @param mixed $original
	 *
	 * @return mixed
	 */
	public function generate( Rule $rule, $original ) {
		return self::to_db_value( $rule->anonymize( $this->faker, $original, $this->deterministic() ) );
	}

	/**
	 * @param string $table
	 * @param string $column
	 *
	 * @return Rule[]
	 */
	public function rules( $table, $column ) {
		if ( ! isset( $this->rules[ $table ][ $column ] ) ) {
			$this->rules[ $table ][ $column ] = $this->config->has_column( $table, $column ) ? $this->config->rules( $table, $column ) : array();
		}

		return $this->rules[ $table ][ $column ];
	}

	/**
	 * @return Deterministic
	 */
	public function deterministic() {
		if ( null === $this->deterministic ) {
			// Resolved lazily so filters added after plugins_loaded are respected.
			$this->deterministic = Deterministic::from_environment();
		}

		return $this->deterministic;
	}

	/**
	 * Empty originals stay empty: never invent data where none existed.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public static function is_empty( $value ) {
		return null === $value || '' === $value;
	}

	/**
	 * Convert Faker output to something that can be written to a column.
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public static function to_db_value( $value ) {
		if ( $value instanceof \DateTimeInterface ) {
			return $value->format( 'Y-m-d H:i:s' );
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			return serialize( $value );
		}

		return $value;
	}
}
