<?php

namespace WPMDB\Anonymization\Config;

class Config {

	/**
	 * Prefix WP Migrate uses for temporary tables during a migration.
	 */
	const TEMP_PREFIX = '_mig_';

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
	 * Cache of raw table name => cleaned table name.
	 *
	 * @var array
	 */
	protected $clean_tables = array();

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

	/**
	 * Get a table's name without the WP Migrate temporary prefix, the table
	 * prefix and, on multisite, the site ID. e.g. `_mig_wp_2_postmeta` => `postmeta`.
	 *
	 * @param string $table
	 *
	 * @return string
	 */
	public function clean_table( $table ) {
		if ( isset( $this->clean_tables[ $table ] ) ) {
			return $this->clean_tables[ $table ];
		}

		$clean = $table;
		if ( 0 === strpos( $clean, self::TEMP_PREFIX ) ) {
			$clean = substr( $clean, strlen( self::TEMP_PREFIX ) );
		}

		$base_prefix = $this->wpdb->base_prefix;
		if ( '' !== $base_prefix && 0 === strpos( $clean, $base_prefix ) ) {
			$clean = substr( $clean, strlen( $base_prefix ) );

			if ( preg_match( '/^(\d+)_(.+)$/', $clean, $matches ) && $this->is_site_id( $matches[1] ) ) {
				$clean = $matches[2];
			}
		}

		$this->clean_tables[ $table ] = $clean;

		return $clean;
	}

	/**
	 * Only treat a numeric table name segment as a site ID on multisite, and
	 * only if a site with that ID exists, so a plugin table such as
	 * `wp_2024_archive` is left alone.
	 *
	 * @param string $id
	 *
	 * @return bool
	 */
	protected function is_site_id( $id ) {
		if ( function_exists( 'is_multisite' ) && ! is_multisite() ) {
			return false;
		}

		if ( function_exists( 'get_site' ) ) {
			return null !== get_site( (int) $id );
		}

		return true;
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