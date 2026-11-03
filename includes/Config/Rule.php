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
	 * When the original value is passed without a secret, email addresses and
	 * user names get a random fragment so they stay unique. Without the
	 * original value, Faker's output is returned as is.
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
		} elseif ( null !== $original && $deterministic instanceof Deterministic && $deterministic->is_unique_type( $this->fake_data_type ) ) {
			$data = $deterministic->generate_random_unique( $faker, $this->fake_data_type );
		} else {
			$data = call_user_func_array( array( $faker, $this->fake_data_type ), $args );
		}

		if ( ! empty( $this->post_process_function ) && is_callable( $this->post_process_function ) ) {
			$data = call_user_func( $this->post_process_function, $data );
		}

		return $data;
	}

	/**
	 * The column and fixed set of values this rule is limited to, when the
	 * constraint is an exact match (`array( 'meta_key' => 'billing_email' )`)
	 * or a list (`array( 'meta_key' => array( 'in' => array( ... ) ) )` or a
	 * plain array of values). Used by the WP-CLI command to push the
	 * constraint into SQL.
	 *
	 * A list combined with a regex still returns the list: rows must be in it
	 * to match, and the regex is applied afterwards by valid().
	 *
	 * When the constraint has several columns, valid() requires all of them to
	 * match, so the values of any one column cover every row the rule can
	 * match. The first column, in definition order, with exact or list values
	 * is returned, and valid() still checks the other columns on each row.
	 *
	 * Returns null when the rule could match rows with any value in every
	 * column (no constraint, a callback constraint, or only regexes).
	 *
	 * @return array|null array( column, array values )
	 */
	public function get_constraint_values() {
		if ( ! is_array( $this->constraint ) ) {
			return null;
		}

		foreach ( $this->constraint as $column => $expected ) {
			if ( ! is_string( $column ) ) {
				continue;
			}

			$values = self::column_constraint_values( $expected );
			if ( null !== $values ) {
				return array( $column, $values );
			}
		}

		return null;
	}

	/**
	 * The column and SQL LIKE patterns covering this rule's regex constraint,
	 * when the regex is simple enough to convert (see regex_to_like_patterns()).
	 * Used by the WP-CLI command so a regex-only rule (e.g. a meta key with a
	 * site prefix) doesn't force a full table scan.
	 *
	 * The patterns match at least every value the regex matches; valid() still
	 * applies the regex to each row.
	 *
	 * When the constraint has several columns, the patterns are for the first
	 * column with a convertible regex. Returns null if any column has exact or
	 * list values: get_constraint_values() covers the rule then.
	 *
	 * @return array|null array( column, array patterns )
	 */
	public function get_constraint_like_patterns() {
		if ( ! is_array( $this->constraint ) || null !== $this->get_constraint_values() ) {
			return null;
		}

		foreach ( $this->constraint as $column => $expected ) {
			if ( ! is_string( $column ) || ! is_array( $expected ) || ! isset( $expected['regex'] ) || array_key_exists( 'in', $expected ) ) {
				continue;
			}

			$patterns = self::regex_to_like_patterns( $expected['regex'] );
			if ( null !== $patterns ) {
				return array( $column, $patterns );
			}
		}

		return null;
	}

	/**
	 * The fixed values one column's constraint allows, or null if it allows
	 * any value (a regex on its own) or is empty.
	 *
	 * @param mixed $expected
	 *
	 * @return array|null
	 */
	protected static function column_constraint_values( $expected ) {
		if ( is_string( $expected ) || is_int( $expected ) ) {
			$values = array( $expected );
		} elseif ( is_array( $expected ) && array_key_exists( 'in', $expected ) ) {
			$values = (array) $expected['in'];
		} elseif ( is_array( $expected ) && ! array_key_exists( 'regex', $expected ) ) {
			$values = $expected;
		} else {
			return null;
		}

		$values = array_values( array_unique( array_map( 'strval', array_filter( $values, 'is_scalar' ) ) ) );

		return empty( $values ) ? null : $values;
	}

	/**
	 * Convert a simple regular expression to SQL LIKE patterns that match a
	 * superset of the values it matches, or null if it can't be done safely.
	 *
	 * Supported: optional `^` and `$` anchors, literal letters, digits, `_`, `-`
	 * and escaped punctuation, and at most one group of literal alternatives,
	 * optionally made optional with `?`, e.g. `/_wcpay_customer_id(_live|_test)?$/`.
	 * An escaped `\|` in the group is a literal pipe, not a separator.
	 * Any flag (e.g. `i`) or other syntax returns null, so the caller scans the table.
	 *
	 * As `$` also matches before a final newline (there is no `D` modifier), an
	 * end-anchored regex gives each pattern twice: as is and with a trailing "\n".
	 *
	 * @param string $regex e.g. '/_stripe_customer_id$/'
	 *
	 * @return array|null LIKE patterns, with `!`, `%` and `_` escaped by `!` (use `ESCAPE '!'`).
	 */
	public static function regex_to_like_patterns( $regex ) {
		if ( ! is_string( $regex ) || ! preg_match( '#^/(.*)/$#s', $regex, $m ) ) {
			return null;
		}

		$body  = $m[1];
		$start = '' !== $body && '^' === $body[0];
		if ( $start ) {
			$body = substr( $body, 1 );
		}
		$end = '' !== $body && '$' === substr( $body, -1 ) && '\\$' !== substr( $body, -2 );
		if ( $end ) {
			$body = substr( $body, 0, -1 );
		}

		$literal = '(?:[A-Za-z0-9_\-]|\\\\[^A-Za-z0-9])*';
		if ( ! preg_match( '#^(' . $literal . ')(?:\(((?:' . $literal . ')(?:\|' . $literal . ')*)\)(\?)?(' . $literal . '))?$#', $body, $parts ) ) {
			return null;
		}

		$unescape = function ( $text ) {
			return preg_replace( '#\\\\(.)#', '$1', $text );
		};

		$before = $unescape( $parts[1] );
		$after  = isset( $parts[4] ) ? $unescape( $parts[4] ) : '';
		$middle = array( '' );
		if ( isset( $parts[2] ) && '' !== $parts[2] ) {
			$middle = array_map( $unescape, self::split_alternatives( $parts[2] ) );
			if ( ! empty( $parts[3] ) ) {
				$middle[] = '';
			}
		}

		$patterns = array();
		foreach ( array_unique( $middle ) as $alternative ) {
			$text = $before . $alternative . $after;
			if ( '' === $text ) {
				return null;
			}
			// `!` is the LIKE escape character: it means the same in MySQL and SQLite.
			$escaped    = str_replace( array( '!', '%', '_' ), array( '!!', '!%', '!_' ), $text );
			$patterns[] = ( $start ? '' : '%' ) . $escaped . ( $end ? '' : '%' );
			if ( $end ) {
				// Without the D modifier, PCRE's `$` also matches before a final newline.
				$patterns[] = ( $start ? '' : '%' ) . $escaped . "\n";
			}
		}

		return $patterns;
	}

	/**
	 * Split a group's alternatives on `|`, except an escaped `\|`, which is a
	 * literal pipe. Escapes are kept for the caller to remove.
	 *
	 * @param string $group e.g. 'foo\|bar|baz'
	 *
	 * @return array e.g. array( 'foo\|bar', 'baz' )
	 */
	protected static function split_alternatives( $group ) {
		$alternatives = array( '' );
		$current      = 0;
		$length       = strlen( $group );

		for ( $i = 0; $i < $length; $i++ ) {
			if ( '\\' === $group[ $i ] && $i + 1 < $length ) {
				$alternatives[ $current ] .= $group[ $i ] . $group[ ++$i ];
			} elseif ( '|' === $group[ $i ] ) {
				$alternatives[ ++$current ] = '';
			} else {
				$alternatives[ $current ] .= $group[ $i ];
			}
		}

		return $alternatives;
	}
}
