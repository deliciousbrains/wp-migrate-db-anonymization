<?php

namespace WPMDB\Anonymization\Tests\Doubles;

/**
 * wpdb double for the Loader's table checks: `SHOW TABLES LIKE` returns the
 * given tables that match the pattern, and every other query returns nothing.
 */
class TablesWpdb extends \wpdb {

	/**
	 * @var string
	 */
	public $usermeta = 'wp_usermeta';

	/**
	 * Full table names, including the prefix.
	 *
	 * @var string[]
	 */
	protected $tables;

	/**
	 * @param string[] $tables      Table names without the prefix.
	 * @param string   $base_prefix
	 */
	public function __construct( array $tables = array(), $base_prefix = 'wp_' ) {
		parent::__construct( $base_prefix );

		$this->usermeta = $base_prefix . 'usermeta';
		$this->tables   = array_map(
			function ( $table ) use ( $base_prefix ) {
				return $base_prefix . $table;
			},
			$tables
		);
	}

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		return str_replace( '%s', "'" . (string) $args[0] . "'", $query );
	}

	public function get_col( $query ) {
		if ( ! preg_match( "/^SHOW TABLES LIKE '(.*)'$/", $query, $matches ) ) {
			return array();
		}

		$pattern = '/^' . strtr( preg_quote( stripslashes( $matches[1] ), '/' ), array( '%' => '.*' ) ) . '$/';

		return array_values( preg_grep( $pattern, $this->tables ) );
	}

	public function get_var( $query ) {
		return null;
	}
}
