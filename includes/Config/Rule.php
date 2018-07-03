<?php


namespace WPMDB\Anonymization\Config;

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


	protected function valid_column_constraint( $row ) {
		if ( ! is_array( $this->constraint ) ) {
			return true;
		}

		$column = key( $this->constraint );
		$value  = current( $this->constraint );

		if ( ! isset( $row->{$column} ) ) {
			return true;
		}

		if ( $row->{$column} === $value ) {
			return true;
		}

		return false;
	}

	public function anonymize( $faker ) {
		if ( empty( $this->fake_data_type ) ) {
			return '';
		}

		$args = array();
		if ( isset( $this->fake_data_args ) && is_array( $this->fake_data_args ) ) {
			$args = $this->fake_data_args;
		}

		$data = call_user_func_array( array( $faker, $this->fake_data_type ), $args );

		if ( ! empty( $this->post_process_function ) && is_callable( $this->post_process_function ) ) {
			$data = call_user_func( $this->post_process_function, $data );
		}

		return $data;
	}
}