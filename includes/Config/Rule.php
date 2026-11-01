<?php


namespace WPMDB\Anonymization\Config;

use WPMDB\Anonymization\Deterministic;

class Rule {

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @var string
	 */
	protected $column;

	/**
	 * @var
	 */
	protected $fake_data_type;

	/**
	 * @var array
	 */
	protected $fake_data_args;

	/**
	 * @var
	 */
	protected $post_process_function;

	/**
	 * @var
	 */
	protected $constraint;

	/**
	 * Rule constructor.
	 *
	 * @param string $table
	 * @param string $column
	 */
	public function __construct( $table, $column ) {
		$this->table  = $table;
		$this->column = $column;
	}

	/**
	 * @param array $data
	 */
	public function load( $data = array() ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->{$key} = $value;
			}
		}
	}

	/**
	 * Check a row is valid for the config rules constraint.
	 *
	 * @param object $row
	 *
	 * @return bool
	 */
	public function valid( $row ) {
		$pre = apply_filters( 'wpmdb_anonymization_pre_rule_valid', true, $this, $row );
		if ( ! $pre ) {
			return false;
		}

		if ( empty( $this->constraint ) ) {
			return true;
		}

		if ( ! $this->valid_constraint_callback( $row ) )  {
			return false;
		}

		if ( ! $this->valid_column_constraint( $row ) ) {
			return false;
		}

		return true;
	}

	protected function valid_constraint_callback( $row ) {
		if ( ! is_string( $this->constraint ) ) {
			return true;
		}

		$default_constraint_class = 'WPMDB\\Anonymization\\Config\\Constraint';

		$function = $this->constraint;
		if ( ! is_callable( $function ) ) {
			if ( ! method_exists( $default_constraint_class, $this->constraint ) ) {
				return true;
			}
			$function = array( $default_constraint_class, $this->constraint );
		}

		return call_user_func( $function, $row );
	}


	/**
	 * Check a row against a column constraint, e.g.
	 *
	 *     array( 'meta_key' => '_billing_email' )                         // Exact match
	 *     array( 'meta_key' => array( 'in' => array( 'a', 'b' ) ) )        // One of a list
	 *     array( 'meta_key' => array( 'a', 'b' ) )                         // Shorthand for 'in'
	 *     array( 'meta_key' => array( 'regex' => '/^_billing_/' ) )        // Regular expression
	 *
	 * When several columns are given, all of them must match. A column that is
	 * not present on the row is ignored.
	 *
	 * @param object $row
	 *
	 * @return bool
	 */
	protected function valid_column_constraint( $row ) {
		if ( ! is_array( $this->constraint ) ) {
			return true;
		}

		foreach ( $this->constraint as $column => $expected ) {
			if ( ! isset( $row->{$column} ) ) {
				continue;
			}

			if ( ! Constraint::column_matches( $row->{$column}, $expected ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Generate the fake value for this rule.
	 *
	 * When the original value is passed and a secret is configured, the output
	 * is deterministic: the same original always produces the same fake value.
	 *
	 * @param \Faker\Generator   $faker
	 * @param mixed|null         $original      The value being replaced.
	 * @param Deterministic|null $deterministic Defaults to one built from the environment.
	 *
	 * @return mixed
	 */
	public function anonymize( $faker, $original = null, $deterministic = null ) {
		if ( empty( $this->fake_data_type ) ) {
			return '';
		}

		$args = array();
		if ( isset( $this->fake_data_args ) && is_array( $this->fake_data_args ) ) {
			$args = $this->fake_data_args;
		}

		if ( null !== $original && null === $deterministic ) {
			$deterministic = Deterministic::from_environment();
		}

		if ( $deterministic instanceof Deterministic && $deterministic->applies( $this->fake_data_type, $original ) ) {
			$data = $deterministic->generate( $faker, $this->fake_data_type, $args, $original );
		} else {
			$data = call_user_func_array( array( $faker, $this->fake_data_type ), $args );
		}

		if ( ! empty( $this->post_process_function ) && is_callable( $this->post_process_function ) ) {
			$data = call_user_func( $this->post_process_function, $data );
		}

		return $data;
	}

	/**
	 * The column/value pair this rule is limited to, when the constraint is a
	 * plain equality check (e.g. `array( 'meta_key' => 'billing_email' )`).
	 * Used by the WP-CLI command to push the constraint into SQL.
	 *
	 * Returns null when the rule could match rows with any value in the
	 * column (no constraint, a callback constraint, or a non-equality match).
	 *
	 * @return array|null array( column, value )
	 */
	public function get_equality_constraint() {
		if ( ! is_array( $this->constraint ) || 1 !== count( $this->constraint ) ) {
			return null;
		}

		$value  = reset( $this->constraint );
		$column = key( $this->constraint );

		if ( ! is_string( $column ) || ! ( is_string( $value ) || is_int( $value ) ) ) {
			return null;
		}

		$value = (string) $value;

		// Probe the constraint so pattern-style matching is never treated as equality.
		$matches = function ( $candidate ) use ( $column ) {
			return $this->valid_column_constraint( (object) array( $column => $candidate ) );
		};

		if ( ! $matches( $value ) || $matches( 'x' . $value ) || $matches( $value . 'x' ) ) {
			return null;
		}

		return array( $column, $value );
	}
}
