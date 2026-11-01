<?php

namespace WPMDB\Anonymization;

use Faker\Factory;
use WP_CLI;
use WP_CLI\Utils;
use WPMDB\Anonymization\Config\Config;
use WPMDB\Anonymization\Config\Loader;

/**
 * Anonymize personal data in the current database, in place.
 */
class CLI {

	/**
	 * @var string
	 */
	protected $plugin_file_path;

	/**
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * @param string     $plugin_file_path
	 * @param \wpdb|null $wpdb
	 */
	public function __construct( $plugin_file_path, $wpdb = null ) {
		$this->plugin_file_path = $plugin_file_path;
		$this->wpdb             = $wpdb;
	}

	/**
	 * Anonymize personal data in the current database, in place.
	 *
	 * Applies the same rules used for anonymized WP Migrate exports, pushes and
	 * pulls directly to the tables of this site. Use it on a copy of a database,
	 * for example straight after restoring a backup locally. WP Migrate does not
	 * need to be active.
	 *
	 * This permanently overwrites data. Never run it on a live site.
	 *
	 * ## OPTIONS
	 *
	 * [--tables=<tables>]
	 * : Comma-separated list of config tables to process, without the table prefix (e.g. users,usermeta). Defaults to every table in the anonymization config.
	 *
	 * [--secret=<secret>]
	 * : Secret for deterministic anonymization. Overrides the WPMDB_ANONYMIZATION_SECRET constant and the wpmdb_anonymization_secret filter. Without a secret, fake values are random.
	 *
	 * [--batch-size=<number>]
	 * : Number of rows to fetch per query.
	 * ---
	 * default: 1000
	 * ---
	 *
	 * [--dry-run]
	 * : Report how many rows would change without writing anything.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * [--force]
	 * : Allow running when the environment type is "production".
	 *
	 * ## EXAMPLES
	 *
	 *     # See what would change.
	 *     $ wp migrate-anonymize run --dry-run
	 *
	 *     # Anonymize everything, keeping values consistent across tables.
	 *     $ wp migrate-anonymize run --yes
	 *
	 *     # Only the users tables, with an explicit secret.
	 *     $ wp migrate-anonymize run --tables=users,usermeta --secret="$ANON_SECRET" --yes
	 *
	 * @when after_wp_load
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function run( $args, $assoc_args ) {
		$dry_run    = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$force      = (bool) Utils\get_flag_value( $assoc_args, 'force', false );
		$batch_size = (int) Utils\get_flag_value( $assoc_args, 'batch-size', 1000 );

		if ( $batch_size < 1 ) {
			WP_CLI::error( '--batch-size must be a positive integer.' );
		}

		$secret = Utils\get_flag_value( $assoc_args, 'secret', null );
		if ( true === $secret || ( is_string( $secret ) && '' === trim( $secret ) ) ) {
			WP_CLI::error( '--secret requires a value.' );
		}

		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : '';
		if ( 'production' === $environment && ! $force && ! $dry_run ) {
			WP_CLI::error( 'The environment type is "production". Anonymizing permanently overwrites data, so it only runs on copies of a database. Use --force if this really is a copy.' );
		}

		list( $config, $schema ) = $this->load_config();

		$only = null;
		if ( isset( $assoc_args['tables'] ) ) {
			$only    = array_filter( array_map( 'trim', explode( ',', (string) $assoc_args['tables'] ) ) );
			$unknown = array_diff( $only, array_keys( $schema ) );
			if ( ! empty( $unknown ) ) {
				WP_CLI::error( sprintf( 'Not in the anonymization config: %s. Available tables: %s.', implode( ', ', $unknown ), implode( ', ', array_keys( $schema ) ) ) );
			}
		}

		$targets = $this->resolve_targets( array_keys( $schema ), $only );
		if ( empty( $targets ) ) {
			WP_CLI::error( 'None of the configured tables exist in this database.' );
		}

		$deterministic = Deterministic::from_environment( is_string( $secret ) ? $secret : null );
		if ( $deterministic->is_enabled() ) {
			WP_CLI::log( 'Deterministic mode: the same original value always becomes the same fake value.' );
		} else {
			WP_CLI::warning( 'No secret configured, so fake values are random and will not match across tables. Set WPMDB_ANONYMIZATION_SECRET or pass --secret for consistent values.' );
		}

		if ( $dry_run ) {
			WP_CLI::log( 'Dry run: nothing will be written.' );
		} else {
			WP_CLI::confirm(
				sprintf(
					'This will permanently overwrite personal data in %d table(s) in the database "%s". Continue?',
					count( $targets ),
					defined( 'DB_NAME' ) ? DB_NAME : ''
				),
				$assoc_args
			);
		}

		$anonymizer = new Anonymizer( $config, Factory::create(), $deterministic );

		$summary = array();
		foreach ( $targets as $target ) {
			$summary[] = $this->process_table( $anonymizer, $target['config_table'], $target['table'], array_keys( $schema[ $target['config_table'] ] ), $batch_size, $dry_run );
		}

		Utils\format_items( 'table', $summary, array( 'table', 'scanned', $dry_run ? 'would_change' : 'changed', 'errors' ) );

		if ( $dry_run ) {
			WP_CLI::success( 'Dry run complete. No data was changed.' );

			return;
		}

		// Persistent object caches may still hold the original values.
		wp_cache_flush();

		$errors = array_sum( wp_list_pluck( $summary, 'errors' ) );
		if ( $errors > 0 ) {
			WP_CLI::warning( sprintf( '%d row(s) could not be updated.', $errors ) );
		}

		WP_CLI::success( sprintf( 'Anonymized %d row(s).', array_sum( wp_list_pluck( $summary, 'changed' ) ) ) );
	}

	/**
	 * Anonymize one table, streaming rows in batches by primary key.
	 *
	 * @param Anonymizer $anonymizer
	 * @param string     $config_table Unprefixed table name used in the config.
	 * @param string     $table        Real table name.
	 * @param array      $columns      Configured columns.
	 * @param int        $batch_size
	 * @param bool       $dry_run
	 *
	 * @return array Summary row.
	 */
	protected function process_table( Anonymizer $anonymizer, $config_table, $table, array $columns, $batch_size, $dry_run ) {
		$wpdb    = $this->wpdb();
		$summary = array(
			'table'        => $table,
			'scanned'      => 0,
			'changed'      => 0,
			'would_change' => 0,
			'errors'       => 0,
		);

		$table_columns = $this->table_columns( $table );
		$columns       = array_values( array_intersect( $columns, array_keys( $table_columns ) ) );
		if ( empty( $columns ) ) {
			WP_CLI::warning( sprintf( 'Skipping %s: none of the configured columns exist.', $table ) );

			return $summary;
		}

		$primary_key = $this->primary_key( $table );
		if ( null === $primary_key ) {
			WP_CLI::warning( sprintf( 'Skipping %s: it needs a single-column primary key.', $table ) );

			return $summary;
		}

		$quoted_table = $this->quote_identifier( $table );
		$quoted_pk    = $this->quote_identifier( $primary_key );
		$pk_format    = preg_match( '/int/i', $table_columns[ $primary_key ] ) ? '%d' : '%s';
		$where        = $this->constraint_where( $anonymizer, $config_table, $columns, array_keys( $table_columns ) );

		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$quoted_table}" . ( '' !== $where ? " WHERE {$where}" : '' ) );
		$progress = Utils\make_progress_bar( sprintf( 'Anonymizing %s', $table ), $total );
		$last_key = null;

		do {
			$conditions = array();
			if ( null !== $last_key ) {
				$conditions[] = $wpdb->prepare( "{$quoted_pk} > {$pk_format}", $last_key );
			}
			if ( '' !== $where ) {
				$conditions[] = $where;
			}

			$sql  = "SELECT * FROM {$quoted_table}";
			$sql .= empty( $conditions ) ? '' : ' WHERE ' . implode( ' AND ', $conditions );
			$sql .= " ORDER BY {$quoted_pk} ASC LIMIT " . (int) $batch_size;

			$rows = $wpdb->get_results( $sql );
			if ( empty( $rows ) ) {
				break;
			}

			if ( ! $dry_run ) {
				$wpdb->query( 'START TRANSACTION' );
			}

			foreach ( $rows as $row ) {
				$summary['scanned']++;
				$changes = $this->row_changes( $anonymizer, $config_table, $columns, $row );

				if ( ! empty( $changes ) ) {
					if ( $dry_run ) {
						$summary['would_change']++;
					} elseif ( false === $wpdb->update( $table, $changes, array( $primary_key => $row->{$primary_key} ) ) ) {
						$summary['errors']++;
					} else {
						$summary['changed']++;
					}
				}

				$progress->tick();
			}

			if ( ! $dry_run ) {
				$wpdb->query( 'COMMIT' );
			}

			$last_key = end( $rows )->{$primary_key};
		} while ( count( $rows ) === $batch_size );

		$progress->finish();

		return $summary;
	}

	/**
	 * Anonymized values for a row, keyed by column, only where they changed.
	 * Constraints are always evaluated against the original row.
	 *
	 * @param Anonymizer $anonymizer
	 * @param string     $config_table
	 * @param array      $columns
	 * @param object     $row
	 *
	 * @return array
	 */
	protected function row_changes( Anonymizer $anonymizer, $config_table, array $columns, $row ) {
		$changes = array();

		foreach ( $columns as $column ) {
			if ( ! property_exists( $row, $column ) || Anonymizer::is_empty( $row->{$column} ) ) {
				continue;
			}

			$value = $anonymizer->anonymize_value( $config_table, $column, $row, $row->{$column} );
			if ( null === $value ) {
				$value = '';
			}

			if ( (string) $value !== (string) $row->{$column} ) {
				$changes[ $column ] = $value;
			}
		}

		return $changes;
	}

	/**
	 * Build a WHERE clause from the rules' equality constraints, so large meta
	 * tables only fetch rows with a configured meta_key instead of every row.
	 *
	 * Returns an empty string (scan every row) unless every rule for every
	 * configured column is limited to exact values of a column in the table.
	 *
	 * @param Anonymizer $anonymizer
	 * @param string     $config_table
	 * @param array      $columns
	 * @param array      $table_columns
	 *
	 * @return string
	 */
	protected function constraint_where( Anonymizer $anonymizer, $config_table, array $columns, array $table_columns ) {
		$values = array();

		foreach ( $columns as $column ) {
			$rules = $anonymizer->rules( $config_table, $column );
			if ( empty( $rules ) ) {
				return '';
			}

			foreach ( $rules as $rule ) {
				$constraint = $rule->get_constraint_values();
				if ( null === $constraint || ! in_array( $constraint[0], $table_columns, true ) ) {
					return '';
				}

				foreach ( $constraint[1] as $value ) {
					$values[ $constraint[0] ][ $value ] = $value;
				}
			}
		}

		$wpdb  = $this->wpdb();
		$parts = array();
		foreach ( $values as $column => $column_values ) {
			$quoted       = $this->quote_identifier( $column );
			$placeholders = implode( ', ', array_fill( 0, count( $column_values ), '%s' ) );
			// A NULL constraint column passes the rule check, so include those rows too.
			$parts[] = $wpdb->prepare( "{$quoted} IN ({$placeholders})", array_values( $column_values ) );
			$parts[] = "{$quoted} IS NULL";
		}

		return '(' . implode( ' OR ', $parts ) . ')';
	}

	/**
	 * Load the config without WP Migrate. The Loader normally defers loading
	 * until a migration needs it, so load it now and hand the result to Config
	 * so the command knows which tables and columns to process.
	 *
	 * @return array array( Config, array $schema )
	 */
	protected function load_config() {
		$loader = new Loader( $this->plugin_file_path );
		$schema = array_filter( $loader->load( $this->wpdb() ), 'is_array' );

		$config = new Config( $this->wpdb(), null );
		$config->init( $schema );

		return array( $config, $schema );
	}

	/**
	 * Real table names for the config tables that exist. Global tables (users,
	 * usermeta) use the base prefix; on multisite, other tables are processed
	 * for every site.
	 *
	 * @param array      $config_tables
	 * @param array|null $only
	 *
	 * @return array
	 */
	protected function resolve_targets( array $config_tables, $only = null ) {
		$wpdb     = $this->wpdb();
		$global   = array_merge( (array) $wpdb->global_tables, (array) $wpdb->ms_global_tables );
		$prefixes = array( $wpdb->prefix );

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$prefixes = array();
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
				$prefixes[] = $wpdb->get_blog_prefix( $blog_id );
			}
		}

		$targets = array();
		foreach ( $config_tables as $config_table ) {
			if ( null !== $only && ! in_array( $config_table, $only, true ) ) {
				continue;
			}

			$table_prefixes = in_array( $config_table, $global, true ) ? array( $wpdb->base_prefix ) : $prefixes;
			foreach ( $table_prefixes as $prefix ) {
				$table = $prefix . $config_table;
				if ( ! $this->table_exists( $table ) ) {
					WP_CLI::debug( sprintf( 'Skipping %s: table does not exist.', $table ), 'migrate-anonymize' );
					continue;
				}

				$targets[] = array(
					'config_table' => $config_table,
					'table'        => $table,
				);
			}
		}

		return $targets;
	}

	/**
	 * @param string $table
	 *
	 * @return bool
	 */
	protected function table_exists( $table ) {
		$wpdb = $this->wpdb();

		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Column names mapped to their types.
	 *
	 * @param string $table
	 *
	 * @return array
	 */
	protected function table_columns( $table ) {
		$columns = array();
		foreach ( (array) $this->wpdb()->get_results( 'SHOW COLUMNS FROM ' . $this->quote_identifier( $table ) ) as $column ) {
			$columns[ $column->Field ] = $column->Type;
		}

		return $columns;
	}

	/**
	 * The single-column primary key, or null.
	 *
	 * @param string $table
	 *
	 * @return string|null
	 */
	protected function primary_key( $table ) {
		$keys = (array) $this->wpdb()->get_results( 'SHOW KEYS FROM ' . $this->quote_identifier( $table ) . " WHERE Key_name = 'PRIMARY'" );

		return 1 === count( $keys ) ? $keys[0]->Column_name : null;
	}

	/**
	 * @param string $identifier
	 *
	 * @return string
	 */
	protected function quote_identifier( $identifier ) {
		return '`' . str_replace( '`', '``', $identifier ) . '`';
	}

	/**
	 * @return \wpdb
	 */
	protected function wpdb() {
		if ( null === $this->wpdb ) {
			$this->wpdb = $GLOBALS['wpdb'];
		}

		return $this->wpdb;
	}
}
