<?php

namespace WPMDB\Anonymization\Config;

class Config {

	/**
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * @var \WPMDB_Base
	 */
	protected $wpmdb;

	/**
	 * @var array
	 */
	protected $data;

	/**
	 * @var array
	 */
	protected $tables;

	/**
	 * Config constructor.
	 *
	 * @param \wpdb $wpdb
	 * @param       $wpmdb
	 */
	public function __construct( \wpdb $wpdb, $wpmdb ) {
		$this->wpdb  = $wpdb;
		$this->wpmdb = $wpmdb;
	}

	public function init( array $config ) {
		$this->data   = $config;
		$this->tables = array_keys( $config );
	}

	public function clean_table( $table ) {
		return str_replace( array( '_mig_', $this->wpdb->base_prefix ), '', $table );
	}

	public function has_table( $table ) {
		return in_array( $table, $this->tables );
	}

	public function has_column( $table, $column ) {
		if ( ! $this->has_table( $table ) ) {
			return false;
		}

		return isset( $this->data[ $table ][ $column ] );
	}

	public function rules( $table, $column ) {
		$rules = $this->data[ $table ][ $column ];
		if ( ! isset( $rules[0] ) || ! is_array( $rules[0] ) ) {
			// Ensure we always return an array of rules
			$rules = array( $rules );
		}

		foreach ( $rules as $key => $data ) {
			$rule = new Rule( $table, $column );
			$rule->load( $data );
			$rules[ $key ] = $rule;
		}

		return $rules;
	}


}