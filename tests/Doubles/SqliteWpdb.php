<?php

namespace WPMDB\Anonymization\Tests\Doubles;

/**
 * A wpdb backed by an in-memory SQLite database, so the WP-CLI command can be
 * run end to end without MySQL or WordPress.
 *
 * Only the methods the plugin uses are implemented. The MySQL-only statements
 * the command issues (SHOW TABLES LIKE, SHOW COLUMNS, SHOW KEYS and START
 * TRANSACTION) are translated to their SQLite equivalents.
 */
class SqliteWpdb extends \wpdb {

	/**
	 * @var array
	 */
	public $tables = array( 'posts', 'comments', 'postmeta', 'options' );

	/**
	 * @var array
	 */
	public $global_tables = array( 'users', 'usermeta' );

	/**
	 * @var array
	 */
	public $ms_global_tables = array();

	/**
	 * @var string
	 */
	public $users;

	/**
	 * @var string
	 */
	public $usermeta;

	/**
	 * @var string
	 */
	public $posts;

	/**
	 * @var string
	 */
	public $comments;

	/**
	 * @var string
	 */
	public $postmeta;

	/**
	 * @var string
	 */
	public $options;

	/**
	 * @var bool
	 */
	public $suppress_errors = false;

	/**
	 * @var string
	 */
	public $last_error = '';

	/**
	 * Every statement run, after translation.
	 *
	 * @var array
	 */
	public $queries = array();

	/**
	 * Regular expressions: a statement matching one fails as if the database
	 * returned an error, with last_error set.
	 *
	 * @var array
	 */
	public $fail_queries = array();

	/**
	 * @var \PDO
	 */
	protected $pdo;

	/**
	 * @param string $base_prefix
	 */
	public function __construct( $base_prefix = 'wp_' ) {
		parent::__construct( $base_prefix );

		$this->pdo = new \PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT );
		// mysqli returns every value as a string.
		$this->pdo->setAttribute( \PDO::ATTR_STRINGIFY_FETCHES, true );

		foreach ( array_merge( $this->tables, $this->global_tables ) as $table ) {
			$this->{$table} = $base_prefix . $table;
		}
	}

	/**
	 * Run raw SQLite statements, e.g. to create fixtures.
	 *
	 * @param string $sql
	 */
	public function exec( $sql ) {
		if ( false === $this->pdo->exec( $sql ) ) {
			throw new \RuntimeException( implode( ' ', $this->pdo->errorInfo() ) );
		}
	}

	/**
	 * @param bool $suppress
	 *
	 * @return bool
	 */
	public function suppress_errors( $suppress = true ) {
		$errors                = $this->suppress_errors;
		$this->suppress_errors = (bool) $suppress;

		return $errors;
	}

	/**
	 * @param string $text
	 *
	 * @return string
	 */
	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Supports %s, %d and %f, with the arguments as an array or variadic.
	 *
	 * @param string $query
	 * @param mixed  ...$args
	 *
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args ) ) {
			$args = $args[0];
		}

		$args = array_values( $args );
		$i    = 0;
		$pdo  = $this->pdo;

		return preg_replace_callback(
			'/%[sdf]/',
			function ( $matches ) use ( &$i, $args, $pdo ) {
				$value = $args[ $i++ ];
				if ( '%d' === $matches[0] ) {
					return (string) (int) $value;
				}
				if ( '%f' === $matches[0] ) {
					return (string) (float) $value;
				}

				return $pdo->quote( (string) $value );
			},
			$query
		);
	}

	/**
	 * @param string $sql
	 *
	 * @return array List of row objects.
	 */
	public function get_results( $sql ) {
		if ( $this->simulate_failure( $sql ) ) {
			return array();
		}

		if ( preg_match( '/^SHOW COLUMNS FROM `((?:[^`]|``)+)`$/', $sql, $matches ) ) {
			$columns = array();
			foreach ( $this->table_info( $matches[1] ) as $column ) {
				$columns[] = (object) array(
					'Field' => $column['name'],
					'Type'  => $column['type'],
				);
			}

			return $columns;
		}

		if ( preg_match( "/^SHOW KEYS FROM `((?:[^`]|``)+)` WHERE Key_name = 'PRIMARY'$/", $sql, $matches ) ) {
			$keys = array();
			foreach ( $this->table_info( $matches[1] ) as $column ) {
				if ( (int) $column['pk'] > 0 ) {
					$keys[] = (object) array( 'Column_name' => $column['name'] );
				}
			}

			return $keys;
		}

		$statement = $this->run( $sql );

		return $statement ? $statement->fetchAll( \PDO::FETCH_OBJ ) : array();
	}

	/**
	 * @param string $sql
	 *
	 * @return array
	 */
	public function get_col( $sql ) {
		$statement = $this->run( $sql );

		return $statement ? $statement->fetchAll( \PDO::FETCH_COLUMN, 0 ) : array();
	}

	/**
	 * @param string $sql
	 *
	 * @return string|null
	 */
	public function get_var( $sql ) {
		$statement = $this->run( $sql );
		if ( ! $statement ) {
			return null;
		}

		$value = $statement->fetchColumn( 0 );

		return false === $value ? null : $value;
	}

	/**
	 * @param string $sql
	 *
	 * @return int|bool
	 */
	public function query( $sql ) {
		$statement = $this->run( $sql );

		return $statement ? $statement->rowCount() : false;
	}

	/**
	 * @param string $table
	 * @param array  $data
	 * @param array  $where
	 *
	 * @return int|false
	 */
	public function update( $table, $data, $where ) {
		$set    = array();
		$values = array();
		foreach ( $data as $column => $value ) {
			$set[]    = $this->quote( $column ) . ' = ?';
			$values[] = $value;
		}

		$conditions = array();
		foreach ( $where as $column => $value ) {
			$conditions[] = $this->quote( $column ) . ' = ?';
			$values[]     = $value;
		}

		$sql             = 'UPDATE ' . $this->quote( $table ) . ' SET ' . implode( ', ', $set ) . ' WHERE ' . implode( ' AND ', $conditions );
		$this->queries[] = $sql;

		if ( $this->simulate_failure( $sql, $values ) ) {
			return false;
		}

		$statement = $this->pdo->prepare( $sql );
		if ( ! $statement || ! $statement->execute( $values ) ) {
			$this->last_error = $statement ? implode( ' ', $statement->errorInfo() ) : implode( ' ', $this->pdo->errorInfo() );

			return false;
		}

		return $statement->rowCount();
	}

	/**
	 * All rows of a table, keyed by the first column.
	 *
	 * @param string $table
	 *
	 * @return array
	 */
	public function rows( $table ) {
		$rows = array();
		foreach ( $this->get_results( 'SELECT * FROM ' . $this->quote( $table ) ) as $row ) {
			$values                       = get_object_vars( $row );
			$rows[ (string) reset( $values ) ] = $row;
		}

		return $rows;
	}

	/**
	 * @param string $sql
	 *
	 * @return \PDOStatement|false
	 */
	protected function run( $sql ) {
		if ( preg_match( '/^SHOW TABLES LIKE (.+)$/s', $sql, $matches ) ) {
			$sql = "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE {$matches[1]} ESCAPE '\\'";
		} elseif ( 'START TRANSACTION' === $sql ) {
			$sql = 'BEGIN';
		}

		$this->queries[] = $sql;

		if ( $this->simulate_failure( $sql ) ) {
			return false;
		}

		$statement = $this->pdo->query( $sql );
		if ( false === $statement ) {
			$this->last_error = implode( ' ', $this->pdo->errorInfo() );
		}

		return $statement;
	}

	/**
	 * Clear last_error, as wpdb does before each query, and fail the statement
	 * if it matches one of $fail_queries. For an UPDATE, the values are
	 * appended to the statement it is matched against.
	 *
	 * @param string $sql
	 * @param array  $values
	 *
	 * @return bool Whether the statement should fail.
	 */
	protected function simulate_failure( $sql, array $values = array() ) {
		$this->last_error = '';

		$subject = empty( $values ) ? $sql : $sql . ' -- ' . implode( ', ', array_map( 'strval', $values ) );
		foreach ( $this->fail_queries as $pattern ) {
			if ( preg_match( $pattern, $subject ) ) {
				$this->last_error = 'Simulated database error';

				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $table Identifier as quoted by the command, `` escaped.
	 *
	 * @return array
	 */
	protected function table_info( $table ) {
		$statement = $this->pdo->query( 'PRAGMA table_info(' . $this->quote( str_replace( '``', '`', $table ) ) . ')' );

		return $statement ? $statement->fetchAll( \PDO::FETCH_ASSOC ) : array();
	}

	/**
	 * @param string $identifier
	 *
	 * @return string
	 */
	protected function quote( $identifier ) {
		return '"' . str_replace( '"', '""', $identifier ) . '"';
	}
}
