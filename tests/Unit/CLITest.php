<?php

namespace WPMDB\Anonymization\Tests\Unit;

use WPMDB\Anonymization\CLI;
use WPMDB\Anonymization\Tests\Doubles\SqliteWpdb;
use WPMDB\Anonymization\Tests\Doubles\TestableCLI;
use WPMDB\Anonymization\Tests\TestCase;

/**
 * The migrate-anonymize command's --prefix and --rules options, run against an
 * in-memory SQLite database with the WP-CLI API stubbed.
 */
class CLITest extends TestCase {

	const EMAIL_PATTERN = '/^[a-z0-9._-]+\.[0-9a-f]{12}@example\.com$/';

	/**
	 * @var SqliteWpdb
	 */
	protected $wpdb;

	/**
	 * @var TestableCLI
	 */
	protected $cli;

	/**
	 * @var array Temporary files to delete.
	 */
	protected $temp_files = array();

	protected function setUp(): void {
		parent::setUp();
		\WP_CLI::reset();

		$this->wpdb = new SqliteWpdb( 'wp_' );
		$this->cli  = new TestableCLI( WPMDB_ANONYMIZATION_PLUGIN_FILE, $this->wpdb );
	}

	protected function tearDown(): void {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		parent::tearDown();
	}

	/**
	 * @return string
	 */
	protected function private_rules_file() {
		return WPMDB_ANONYMIZATION_TESTS_DIR . '/fixtures/rules/private-rules.php';
	}

	/**
	 * Write a temporary rules file returning the given PHP array expression.
	 *
	 * @param string $php_array
	 *
	 * @return string Path.
	 */
	protected function write_rules_file( $php_array ) {
		$file               = tempnam( sys_get_temp_dir(), 'wpmdba-rules' );
		$this->temp_files[] = $file;
		file_put_contents( $file, '<?php return ' . $php_array . ';' );

		return $file;
	}

	/**
	 * Assert that a callback stops the command with an error.
	 *
	 * @param string   $message_fragment
	 * @param callable $callback
	 */
	protected function assert_cli_error( $message_fragment, callable $callback ) {
		try {
			$callback();
		} catch ( \WPMDB_Anonymization_Tests_CLI_Error $e ) {
			$this->assertStringContainsString( $message_fragment, $e->getMessage() );

			return;
		}

		$this->fail( 'Expected a WP-CLI error containing: ' . $message_fragment );
	}

	/**
	 * @param string $table
	 */
	protected function create_users_table( $table ) {
		$this->wpdb->exec( "CREATE TABLE \"{$table}\" ( ID INTEGER PRIMARY KEY, user_login TEXT, user_pass TEXT, user_nicename TEXT, user_email TEXT, user_url TEXT, display_name TEXT )" );
		$this->wpdb->exec( "INSERT INTO \"{$table}\" VALUES ( 1, 'alice', 'x', 'alice', 'alice@real.test', 'https://alice.test', 'Alice Real' ), ( 2, 'bob', 'y', 'bob', 'bob@real.test', '', 'Bob Real' )" );
	}

	/**
	 * @param string $table
	 */
	protected function create_usermeta_table( $table ) {
		$this->wpdb->exec( "CREATE TABLE \"{$table}\" ( umeta_id INTEGER PRIMARY KEY, user_id INTEGER, meta_key TEXT, meta_value TEXT )" );
		$this->wpdb->exec( "INSERT INTO \"{$table}\" VALUES ( 1, 1, 'first_name', 'Alice' ), ( 2, 1, 'private_phone', '+44 1234 567890' ), ( 3, 1, 'locale', 'en_GB' )" );
	}

	/**
	 * A table with a UNIQUE email column, like the one in #29.
	 *
	 * @param string $table
	 * @param int    $rows
	 */
	protected function create_backup_table( $table, $rows ) {
		$this->wpdb->exec( "CREATE TABLE \"{$table}\" ( id INTEGER PRIMARY KEY, email TEXT UNIQUE, full_name TEXT )" );

		$values = array();
		for ( $i = 1; $i <= $rows; $i++ ) {
			$values[] = "( {$i}, 'person{$i}@real.test', 'Real Person {$i}' )";
		}
		$this->wpdb->exec( "INSERT INTO \"{$table}\" VALUES " . implode( ', ', $values ) );
	}

	/**
	 * @param array $assoc_args
	 */
	protected function run_command( array $assoc_args ) {
		$this->cli->run( array(), array_merge( array( 'yes' => true ), $assoc_args ) );
	}

	/**
	 * @return array Table names from the summary.
	 */
	protected function summary_tables() {
		return array_column( \WP_CLI::$items, 'table' );
	}

	public function test_regex_constraint_is_pushed_into_sql_as_like() {
		$this->wpdb->exec( 'CREATE TABLE "site_meta" ( meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT )' );
		$this->wpdb->exec( "INSERT INTO \"site_meta\" VALUES ( 1, 'wp__stripe_customer_id', 'cus_real1' ), ( 2, 'wp_3__stripe_customer_id', 'cus_real2' ), ( 3, 'wpXstripeXcustomerXid', 'keep-me' ), ( 4, 'locale', 'en_GB' ), ( 5, 'first_name', 'Alice' )" );

		$file               = tempnam( sys_get_temp_dir(), 'wpmdba-rules' );
		$this->temp_files[] = $file;
		file_put_contents( $file, "<?php return array( 'site_meta' => array( 'meta_value' => array( array( 'constraint' => array( 'meta_key' => array( 'regex' => '/_stripe_customer_id\$/' ) ), 'fake_data_type' => 'ean13' ) ) ) );" );

		$this->run_command( array( 'prefix' => '', 'rules' => $file, 'tables' => 'site_meta' ) );

		$summary = array_values( array_filter( \WP_CLI::$items, function ( $item ) {
			return 'site_meta' === $item['table'];
		} ) );
		$this->assertCount( 1, $summary );
		// Only the two matching keys are read: `_` is escaped, so wpXstripeXcustomerXid isn't fetched.
		$this->assertSame( 2, (int) $summary[0]['scanned'] );
		$this->assertSame( 2, (int) $summary[0]['changed'] );

		$rows = $this->wpdb->rows( 'site_meta' );
		$this->assertNotSame( 'cus_real1', $rows['1']->meta_value );
		$this->assertNotSame( 'cus_real2', $rows['2']->meta_value );
		$this->assertSame( 'keep-me', $rows['3']->meta_value );
		$this->assertSame( 'en_GB', $rows['4']->meta_value );
	}

	public function test_like_push_down_keeps_values_ending_in_a_newline_that_the_regex_matches() {
		$this->wpdb->exec( 'CREATE TABLE "site_meta" ( meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT )' );
		// PCRE's `$` matches before a final newline, so the rule applies to row 2 too.
		$this->wpdb->exec( "INSERT INTO \"site_meta\" VALUES ( 1, 'wp__stripe_customer_id', 'cus_real1' ), ( 2, 'wp__stripe_customer_id' || char(10), 'cus_real2' ), ( 3, 'wp__stripe_customer_id' || char(10) || 'x', 'keep-me' ), ( 4, 'locale', 'en_GB' )" );

		$file = $this->write_rules_file( "array( 'site_meta' => array( 'meta_value' => array( array( 'constraint' => array( 'meta_key' => array( 'regex' => '/_stripe_customer_id\$/' ) ), 'fake_data_type' => 'ean13' ) ) ) )" );

		$this->run_command( array( 'prefix' => '', 'rules' => $file, 'tables' => 'site_meta' ) );

		$this->assertSame( array( 'site_meta' ), $this->summary_tables() );
		$this->assertSame( 2, (int) \WP_CLI::$items[0]['scanned'] );
		$this->assertSame( 2, (int) \WP_CLI::$items[0]['changed'] );

		$rows = $this->wpdb->rows( 'site_meta' );
		$this->assertSame( "wp__stripe_customer_id\n", $rows['2']->meta_key );
		$this->assertNotSame( 'cus_real1', $rows['1']->meta_value );
		$this->assertNotSame( 'cus_real2', $rows['2']->meta_value, 'A meta key ending in a newline is still fetched and anonymized.' );
		$this->assertSame( 'keep-me', $rows['3']->meta_value );
		$this->assertSame( 'en_GB', $rows['4']->meta_value );
	}

	public function test_multi_column_constraint_pushes_one_column_into_sql_and_checks_the_other_per_row() {
		$this->wpdb->exec( 'CREATE TABLE "site_meta" ( meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT )' );
		$this->wpdb->exec( "INSERT INTO \"site_meta\" VALUES ( 1, 'customer_email', 'alice@real.test' ), ( 2, 'customer_email', 'n/a' ), ( 3, 'other_email', 'bob@real.test' ), ( 4, 'locale', 'en_GB' ), ( 5, 'customer_email', 'carol@real.test' )" );

		$file = $this->write_rules_file( "array( 'site_meta' => array( 'meta_value' => array( array( 'constraint' => array( 'meta_key' => 'customer_email', 'meta_value' => array( 'regex' => '/@/' ) ), 'fake_data_type' => 'email' ) ) ) )" );

		$this->run_command( array( 'prefix' => '', 'rules' => $file, 'tables' => 'site_meta' ) );

		$this->assertSame( array( 'site_meta' ), $this->summary_tables() );
		// Only the three customer_email rows are read, by meta_key.
		$this->assertSame( 3, (int) \WP_CLI::$items[0]['scanned'] );
		$this->assertSame( 2, (int) \WP_CLI::$items[0]['changed'] );
		$selects = array_filter( $this->wpdb->queries, function ( $sql ) {
			return 0 === strpos( $sql, 'SELECT * FROM `site_meta`' );
		} );
		$this->assertNotEmpty( $selects );
		foreach ( $selects as $sql ) {
			$this->assertStringContainsString( "`meta_key` IN ('customer_email')", $sql );
		}

		$rows = $this->wpdb->rows( 'site_meta' );
		$this->assertMatchesPattern( self::EMAIL_PATTERN, $rows['1']->meta_value );
		$this->assertSame( 'n/a', $rows['2']->meta_value, 'The meta_value regex is still applied to each row.' );
		$this->assertSame( 'bob@real.test', $rows['3']->meta_value );
		$this->assertSame( 'en_GB', $rows['4']->meta_value );
		$this->assertMatchesPattern( self::EMAIL_PATTERN, $rows['5']->meta_value );
	}

	public function test_production_environment_refuses_to_write_without_force() {
		$this->create_users_table( 'wp_users' );
		$users = $this->wpdb->rows( 'wp_users' );
		$this->wpdb->queries = array();
		wpmdb_anonymization_tests_set_environment_type( 'production' );

		$this->assert_cli_error(
			'The environment type is "production"',
			function () {
				$this->run_command( array() );
			}
		);

		$this->assertSame( array(), $this->wpdb->queries, 'Stops before reading the database.' );
		$this->assertEquals( $users, $this->wpdb->rows( 'wp_users' ) );
	}

	public function test_production_environment_writes_with_force() {
		$this->create_users_table( 'wp_users' );
		wpmdb_anonymization_tests_set_environment_type( 'production' );

		$this->run_command( array( 'force' => true ) );

		$this->assertSame( array( 'wp_users' ), $this->summary_tables() );
		$users = $this->wpdb->rows( 'wp_users' );
		$this->assertMatchesPattern( self::EMAIL_PATTERN, $users['1']->user_email );
		$this->assertContains( array( 'success', 'Anonymized 2 row(s).' ), \WP_CLI::$messages );
	}

	public function test_production_environment_allows_dry_run_without_force() {
		$this->create_users_table( 'wp_users' );
		$users = $this->wpdb->rows( 'wp_users' );
		wpmdb_anonymization_tests_set_environment_type( 'production' );

		$this->run_command( array( 'dry-run' => true ) );

		$this->assertSame( array( 'wp_users' ), $this->summary_tables() );
		$this->assertSame( 2, (int) \WP_CLI::$items[0]['would_change'] );
		$this->assertContains( array( 'success', 'Dry run complete. No data was changed.' ), \WP_CLI::$messages );
		$this->assertEquals( $users, $this->wpdb->rows( 'wp_users' ) );
	}

	/**
	 * The messages of one type, in order.
	 *
	 * @param string $type e.g. 'warning'.
	 *
	 * @return array
	 */
	protected function messages( $type ) {
		$messages = array();
		foreach ( \WP_CLI::$messages as $message ) {
			if ( $type === $message[0] ) {
				$messages[] = $message[1];
			}
		}

		return $messages;
	}

	/**
	 * Index of the first message of a type, or -1.
	 *
	 * @param string $type
	 *
	 * @return int
	 */
	protected function message_index( $type ) {
		foreach ( \WP_CLI::$messages as $index => $message ) {
			if ( $type === $message[0] ) {
				return $index;
			}
		}

		return -1;
	}

	public function test_failed_updates_end_with_an_error_after_the_summary() {
		$this->create_users_table( 'wp_users' );
		// The primary key is the last value bound to an UPDATE.
		$this->wpdb->fail_queries = array( '/^UPDATE "wp_users" .* -- .*, 2$/' );

		$this->assert_cli_error(
			'Anonymized 1 row(s), but 1 row(s) could not be updated. The database is NOT fully anonymized',
			function () {
				$this->run_command( array() );
			}
		);

		$this->assertSame( array( 'wp_users' ), $this->summary_tables(), 'The summary is still printed.' );
		$this->assertSame( 1, \WP_CLI::$items[0]['changed'] );
		$this->assertSame( 1, \WP_CLI::$items[0]['errors'] );
		$this->assertContains( 'Could not update wp_users row ID = 2: Simulated database error. Further failures in this table are only counted.', $this->messages( 'warning' ) );
		$this->assertSame( array(), $this->messages( 'success' ) );
		$this->assertGreaterThan( -1, $this->message_index( 'cache_flush' ), 'Caches are flushed for the rows that did change.' );
		$this->assertLessThan( $this->message_index( 'error' ), $this->message_index( 'cache_flush' ) );

		$users = $this->wpdb->rows( 'wp_users' );
		$this->assertMatchesPattern( self::EMAIL_PATTERN, $users['1']->user_email );
		$this->assertSame( 'bob@real.test', $users['2']->user_email );
	}

	public function test_failed_commit_rolls_back_the_batch_and_fails_the_command() {
		$this->create_backup_table( 'my_backup_table', 5 );
		$before                   = $this->wpdb->rows( 'my_backup_table' );
		$this->wpdb->fail_queries = array( '/^COMMIT$/' );

		$this->assert_cli_error(
			'Anonymized 0 row(s), but 5 row(s) could not be updated. The database is NOT fully anonymized',
			function () {
				$this->run_command( array( 'prefix' => '', 'rules' => $this->private_rules_file(), 'tables' => 'my_backup_table', 'batch-size' => 2 ) );
			}
		);

		$this->assertSame( 5, \WP_CLI::$items[0]['scanned'] );
		$this->assertSame( 0, \WP_CLI::$items[0]['changed'], 'Rows only count as changed once committed.' );
		$this->assertSame( 5, \WP_CLI::$items[0]['errors'] );
		$this->assertSame( 3, count( array_keys( $this->wpdb->queries, 'ROLLBACK', true ) ), 'Every failed batch is rolled back.' );

		$warnings = array_values( array_filter( $this->messages( 'warning' ), function ( $message ) {
			return 0 === strpos( $message, 'Could not commit' );
		} ) );
		$this->assertSame( array( 'Could not commit the batch of my_backup_table rows with id 1 to 2, so its 2 updated row(s) were rolled back: Simulated database error. Further failed batches in this table are only counted.' ), $warnings );
		$this->assertSame( array(), $this->messages( 'success' ) );

		$this->assertEquals( $before, $this->wpdb->rows( 'my_backup_table' ), 'The rolled back rows keep their original values.' );
	}

	public function test_failed_table_check_stops_the_command_before_writing() {
		$this->create_users_table( 'wp_users' );
		$this->create_usermeta_table( 'wp_usermeta' );
		$users = $this->wpdb->rows( 'wp_users' );
		// The translated SHOW TABLES LIKE for wp_usermeta.
		$this->wpdb->fail_queries = array( '/^SELECT name FROM sqlite_master .* LIKE \'wp\\\\_usermeta\' ESCAPE/' );

		$this->assert_cli_error(
			'Could not check whether the table wp_usermeta exists: Simulated database error. Nothing was changed.',
			function () {
				$this->run_command( array() );
			}
		);

		$this->assertSame( -1, $this->message_index( 'confirm' ), 'Stops before asking to overwrite data.' );
		$this->assertSame( array(), \WP_CLI::$items );
		$writes = array_filter( $this->wpdb->queries, function ( $sql ) {
			return 0 === strpos( $sql, 'UPDATE' ) || 'BEGIN' === $sql;
		} );
		$this->assertSame( array(), $writes );
		$this->assertEquals( $users, $this->wpdb->rows( 'wp_users' ) );
	}

	public function test_failed_batch_select_stops_the_table_and_fails_the_command() {
		$this->create_backup_table( 'my_backup_table', 5 );
		$this->wpdb->fail_queries = array( '/^SELECT \* FROM `my_backup_table` WHERE `id` > 2 /' );

		$this->assert_cli_error(
			'Anonymized 2 row(s), but reading stopped early after a database error in my_backup_table. The database is NOT fully anonymized',
			function () {
				$this->run_command( array( 'prefix' => '', 'rules' => $this->private_rules_file(), 'tables' => 'my_backup_table', 'batch-size' => 2 ) );
			}
		);

		$this->assertSame( 2, \WP_CLI::$items[0]['scanned'] );
		$this->assertSame( 2, \WP_CLI::$items[0]['changed'] );
		$this->assertSame( 1, \WP_CLI::$items[0]['errors'] );
		$this->assertContains( 'Stopped processing my_backup_table: database error while reading rows: Simulated database error', $this->messages( 'warning' ) );

		$rows = $this->wpdb->rows( 'my_backup_table' );
		$this->assertMatchesPattern( self::EMAIL_PATTERN, $rows['2']->email );
		$this->assertSame( 'person3@real.test', $rows['3']->email, 'Rows after the failed batch are left as they were, and reported.' );
	}

	/**
	 * @dataProvider failed_read_provider
	 */
	public function test_failed_reads_before_the_first_batch_stop_the_table( $pattern, $action ) {
		$this->create_users_table( 'wp_users' );
		$this->create_usermeta_table( 'wp_usermeta' );
		$this->wpdb->fail_queries = array( $pattern );

		$this->assert_cli_error(
			'reading stopped early after a database error in wp_users. The database is NOT fully anonymized',
			function () {
				$this->run_command( array() );
			}
		);

		$this->assertContains( 'Stopped processing wp_users: database error while ' . $action . ': Simulated database error', $this->messages( 'warning' ) );
		$this->assertSame( array( 'wp_users', 'wp_usermeta' ), $this->summary_tables(), 'Other tables are still processed.' );
		$this->assertSame( 'alice@real.test', $this->wpdb->rows( 'wp_users' )['1']->user_email );
		$this->assertNotSame( 'Alice', $this->wpdb->rows( 'wp_usermeta' )['1']->meta_value );
	}

	public function failed_read_provider() {
		return array(
			'columns'     => array( '/^SHOW COLUMNS FROM `wp_users`$/', 'reading its columns' ),
			'primary key' => array( '/^SHOW KEYS FROM `wp_users`/', 'reading its primary key' ),
			'count'       => array( '/^SELECT COUNT\(\*\) FROM `wp_users`/', 'counting its rows' ),
			'first batch' => array( '/^SELECT \* FROM `wp_users`/', 'reading rows' ),
		);
	}

	public function test_failed_read_in_a_dry_run_fails_the_command() {
		$this->create_users_table( 'wp_users' );
		$this->wpdb->fail_queries = array( '/^SELECT \* FROM `wp_users`/' );

		$this->assert_cli_error(
			'Dry run incomplete: reading stopped early after a database error in wp_users',
			function () {
				$this->run_command( array( 'dry-run' => true ) );
			}
		);

		$this->assertSame( array( 'wp_users' ), $this->summary_tables() );
	}

	public function test_rules_on_the_primary_key_are_skipped() {
		$this->create_backup_table( 'my_backup_table', 5 );
		$file = $this->write_rules_file( "array( 'my_backup_table' => array( 'id' => array( 'fake_data_type' => 'randomNumber' ), 'email' => array( 'fake_data_type' => 'email' ) ) )" );

		$this->run_command( array( 'prefix' => '', 'rules' => $file, 'tables' => 'my_backup_table', 'batch-size' => 2 ) );

		$warnings = array_values( array_filter( $this->messages( 'warning' ), function ( $message ) {
			return false !== strpos( $message, 'primary key' );
		} ) );
		$this->assertSame( array( 'Not anonymizing my_backup_table.id: it is the primary key, and changing it while rows are read in key order would skip or repeat rows. Empty the table instead if its keys are personal data.' ), $warnings, 'Warned once for the table.' );

		$this->assertSame( 5, \WP_CLI::$items[0]['scanned'] );
		$this->assertSame( 5, \WP_CLI::$items[0]['changed'] );
		$rows = $this->wpdb->rows( 'my_backup_table' );
		$this->assertSame( array( '1', '2', '3', '4', '5' ), array_map( 'strval', array_keys( $rows ) ) );
		foreach ( $rows as $row ) {
			$this->assertMatchesPattern( self::EMAIL_PATTERN, $row->email );
		}
	}

	public function test_table_with_only_a_primary_key_rule_is_skipped() {
		$this->create_backup_table( 'my_backup_table', 3 );
		$before = $this->wpdb->rows( 'my_backup_table' );
		$file   = $this->write_rules_file( "array( 'my_backup_table' => array( 'id' => array( 'fake_data_type' => 'randomNumber' ) ) )" );

		$this->run_command( array( 'prefix' => '', 'rules' => $file, 'tables' => 'my_backup_table' ) );

		$this->assertSame( 0, \WP_CLI::$items[0]['scanned'] );
		$this->assertEquals( $before, $this->wpdb->rows( 'my_backup_table' ) );
		$this->assertContains( 'Anonymized 0 row(s).', $this->messages( 'success' ) );
	}

	/**
	 * The WHERE clause of the queries that read a table's rows.
	 *
	 * @param string $table
	 *
	 * @return string[]
	 */
	protected function select_queries( $table ) {
		return array_values(
			array_filter(
				$this->wpdb->queries,
				function ( $sql ) use ( $table ) {
					return 0 === strpos( $sql, "SELECT * FROM \"{$table}\"" ) || 0 === strpos( $sql, "SELECT * FROM `{$table}`" );
				}
			)
		);
	}

	public function test_usermeta_is_only_filtered_by_meta_value_on_sites_with_edd_data() {
		$this->create_usermeta_table( 'wp_usermeta' );

		$this->run_command( array( 'tables' => 'usermeta' ) );

		$queries = $this->select_queries( 'wp_usermeta' );
		$this->assertNotEmpty( $queries );
		$this->assertStringContainsString( 'meta_key', $queries[0] );
		$this->assertStringNotContainsString( 'meta_value', $queries[0], 'Without EDD data the usermeta rules only filter on meta_key.' );

		// The EDD API key rule is constrained on meta_value.
		$this->wpdb->exec( 'CREATE TABLE wp_edd_orders ( id INTEGER PRIMARY KEY, email TEXT )' );
		$this->wpdb->queries = array();
		\WP_CLI::reset();

		$this->run_command( array( 'tables' => 'usermeta' ) );

		$queries = $this->select_queries( 'wp_usermeta' );
		$this->assertNotEmpty( $queries );
		$this->assertStringContainsString( 'meta_value', $queries[0] );
	}

	/**
	 * @dataProvider valid_prefix_provider
	 */
	public function test_valid_prefixes( $prefix ) {
		$this->assertTrue( CLI::is_valid_prefix( $prefix ) );
	}

	public function valid_prefix_provider() {
		return array(
			'empty'      => array( '' ),
			'default'    => array( 'wp_' ),
			'mixed case' => array( 'Shop2_' ),
			'digits'     => array( '123' ),
			'underscore' => array( '_' ),
		);
	}

	/**
	 * @dataProvider invalid_prefix_provider
	 */
	public function test_invalid_prefixes( $prefix ) {
		$this->assertFalse( CLI::is_valid_prefix( $prefix ) );
	}

	public function invalid_prefix_provider() {
		return array(
			'hyphen'       => array( 'wp-' ),
			'dot'          => array( 'wp.' ),
			'semicolon'    => array( 'wp_;' ),
			'backtick'     => array( 'wp`' ),
			'space'        => array( 'wp _' ),
			'newline'      => array( "wp_\n" ),
			'sql'          => array( "wp_' OR 1=1 -- " ),
			'non-ascii'    => array( 'wé_' ),
			'flag (true)'  => array( true ),
			'null'         => array( null ),
			'array'        => array( array( 'wp_' ) ),
		);
	}

	public function test_prefix_option() {
		$this->assertNull( $this->cli->call( 'prefix_option', array() ) );
		$this->assertSame( '', $this->cli->call( 'prefix_option', array( 'prefix' => '' ) ) );
		$this->assertSame( 'shop_', $this->cli->call( 'prefix_option', array( 'prefix' => 'shop_' ) ) );
	}

	public function test_prefix_option_without_value_errors() {
		$this->assert_cli_error(
			"--prefix=''",
			function () {
				$this->cli->call( 'prefix_option', array( 'prefix' => true ) );
			}
		);
	}

	public function test_invalid_prefix_errors_before_touching_the_database() {
		$this->assert_cli_error(
			'Invalid --prefix "wp-"',
			function () {
				$this->run_command( array( 'prefix' => 'wp-' ) );
			}
		);

		$this->assertSame( array(), $this->wpdb->queries );
	}

	public function test_targets_without_prefix_use_site_prefixes_and_base_prefix_for_global_tables() {
		$this->create_users_table( 'wp_users' );
		$this->wpdb->exec( 'CREATE TABLE wp_postmeta ( meta_id INTEGER PRIMARY KEY )' );
		$this->wpdb->exec( 'CREATE TABLE wp_2_postmeta ( meta_id INTEGER PRIMARY KEY )' );
		$this->wpdb->exec( 'CREATE TABLE wp_2_users ( ID INTEGER PRIMARY KEY )' );
		$this->cli->site_prefixes = array( 'wp_', 'wp_2_' );

		$targets = $this->cli->call( 'resolve_targets', array( 'users', 'postmeta' ) );

		$this->assertSame( array( 'wp_users', 'wp_postmeta', 'wp_2_postmeta' ), array_column( $targets, 'table' ) );
	}

	public function test_prefix_applies_to_global_tables_and_ignores_multisite() {
		$this->create_users_table( 'wp_users' );
		$this->create_users_table( 'shop_users' );
		$this->create_usermeta_table( 'shop_usermeta' );
		$this->wpdb->exec( 'CREATE TABLE shop_postmeta ( meta_id INTEGER PRIMARY KEY )' );
		$this->wpdb->exec( 'CREATE TABLE shop_2_postmeta ( meta_id INTEGER PRIMARY KEY )' );
		$this->cli->site_prefixes = array( 'wp_', 'wp_2_' );

		$targets = $this->cli->call( 'resolve_targets', array( 'users', 'usermeta', 'postmeta' ), null, 'shop_' );

		$this->assertSame( array( 'shop_users', 'shop_usermeta', 'shop_postmeta' ), array_column( $targets, 'table' ) );
		$this->assertSame( array( 'users', 'usermeta', 'postmeta' ), array_column( $targets, 'config_table' ) );
	}

	public function test_empty_prefix_uses_table_names_as_written() {
		$this->create_users_table( 'wp_users' );
		$this->create_users_table( 'users' );
		$this->create_backup_table( 'my_backup_table', 1 );

		$targets = $this->cli->call( 'resolve_targets', array( 'users', 'usermeta', 'my_backup_table' ), null, '' );

		$this->assertSame( array( 'users', 'my_backup_table' ), array_column( $targets, 'table' ) );
	}

	public function test_rules_option_resolves_paths_and_removes_duplicates() {
		$relative = 'tests/fixtures/rules/private-rules.php';
		$this->assertFileExists( getcwd() . '/' . $relative, 'PHPUnit runs from the plugin directory.' );

		$files = $this->cli->call( 'rule_files_option', array( 'rules' => ' ' . $relative . ' , ' . $this->private_rules_file() . ',' ) );

		$this->assertSame( array( realpath( $this->private_rules_file() ) ), $files );
		$this->assertSame( array(), $this->cli->call( 'rule_files_option', array() ) );
	}

	public function test_rules_option_errors_for_a_missing_file() {
		$this->assert_cli_error(
			'Rules file not found: /no/such/private-rules.php',
			function () {
				$this->cli->call( 'rule_files_option', array( 'rules' => $this->private_rules_file() . ',/no/such/private-rules.php' ) );
			}
		);
	}

	public function test_rules_option_errors_for_a_directory() {
		$this->assert_cli_error(
			'Rules file not found',
			function () {
				$this->cli->call( 'rule_files_option', array( 'rules' => WPMDB_ANONYMIZATION_TESTS_DIR . '/fixtures/rules' ) );
			}
		);
	}

	public function test_rules_option_errors_for_a_file_that_does_not_return_an_array() {
		$this->assert_cli_error(
			'not-an-array.php must return an array of rules',
			function () {
				$this->cli->call( 'rule_files_option', array( 'rules' => WPMDB_ANONYMIZATION_TESTS_DIR . '/fixtures/rules/not-an-array.php' ) );
			}
		);
	}

	public function test_rules_option_errors_for_a_file_that_cannot_be_parsed() {
		$file               = tempnam( sys_get_temp_dir(), 'wpmdba-rules' );
		$this->temp_files[] = $file;
		file_put_contents( $file, '<?php return array( ' );

		$this->assert_cli_error(
			'Could not load rules file',
			function () use ( $file ) {
				$this->cli->call( 'rule_files_option', array( 'rules' => $file ) );
			}
		);
	}

	/**
	 * @dataProvider empty_rules_provider
	 */
	public function test_rules_option_without_a_path_errors( $value ) {
		$this->assert_cli_error(
			'--rules requires',
			function () use ( $value ) {
				$this->cli->call( 'rule_files_option', array( 'rules' => $value ) );
			}
		);
	}

	public function empty_rules_provider() {
		return array(
			'flag'   => array( true ),
			'empty'  => array( '' ),
			'commas' => array( ' , ' ),
		);
	}

	public function test_missing_rules_file_errors_before_touching_the_database() {
		$this->assert_cli_error(
			'Rules file not found',
			function () {
				$this->run_command( array( 'rules' => '/no/such/file.php' ) );
			}
		);

		$this->assertSame( array(), $this->wpdb->queries );
	}

	public function test_run_with_empty_prefix_and_rules_anonymizes_unprefixed_tables() {
		$this->create_users_table( 'wp_users' );
		$this->create_users_table( 'users' );
		$this->create_backup_table( 'my_backup_table', 2000 );
		$wp_users = $this->wpdb->rows( 'wp_users' );

		$this->run_command(
			array(
				'prefix' => '',
				'rules'  => $this->private_rules_file(),
			)
		);

		$this->assertSame( array( 'users', 'my_backup_table' ), $this->summary_tables() );
		$this->assertSame( array( 0, 0 ), array_column( \WP_CLI::$items, 'errors' ), 'The UNIQUE email column accepts every fake value.' );

		$emails = array();
		foreach ( $this->wpdb->rows( 'my_backup_table' ) as $row ) {
			$this->assertMatchesPattern( self::EMAIL_PATTERN, $row->email );
			$this->assertStringNotContainsString( 'Real Person', $row->full_name );
			$emails[ $row->email ] = true;
		}
		$this->assertCount( 2000, $emails );

		$users = $this->wpdb->rows( 'users' );
		$this->assertNotSame( 'alice@real.test', $users['1']->user_email );
		$this->assertEquals( $wp_users, $this->wpdb->rows( 'wp_users' ), 'Tables with the site prefix are untouched.' );
	}

	public function test_run_with_prefix_processes_global_tables_and_merges_extra_rules_with_bundled_rules() {
		$this->create_users_table( 'wp_users' );
		$this->create_usermeta_table( 'wp_usermeta' );
		$this->create_users_table( 'shop_users' );
		$this->create_usermeta_table( 'shop_usermeta' );
		$wp_users    = $this->wpdb->rows( 'wp_users' );
		$wp_usermeta = $this->wpdb->rows( 'wp_usermeta' );

		$this->run_command(
			array(
				'prefix' => 'shop_',
				'rules'  => $this->private_rules_file(),
			)
		);

		// Order follows the rule files (credentials.php also has usermeta rules), so compare contents.
		$this->assertEqualsCanonicalizing( array( 'shop_users', 'shop_usermeta' ), $this->summary_tables() );

		$users = $this->wpdb->rows( 'shop_users' );
		$this->assertMatchesPattern( self::EMAIL_PATTERN, $users['1']->user_email );

		$meta = $this->wpdb->rows( 'shop_usermeta' );
		$this->assertNotSame( 'Alice', $meta['1']->meta_value, 'Bundled first_name rule applied.' );
		$this->assertNotSame( '+44 1234 567890', $meta['2']->meta_value, 'Extra private_phone rule applied.' );
		$this->assertSame( 'en_GB', $meta['3']->meta_value, 'Meta without a rule is untouched.' );

		$this->assertEquals( $wp_users, $this->wpdb->rows( 'wp_users' ) );
		$this->assertEquals( $wp_usermeta, $this->wpdb->rows( 'wp_usermeta' ) );
		$this->assertContains( array( 'log', 'Table prefix: shop_' ), \WP_CLI::$messages );
	}

	public function test_prefix_is_used_to_check_which_plugin_rules_to_load() {
		// WooCommerce data only exists under the other prefix.
		$this->create_users_table( 'shop_users' );
		$this->wpdb->exec( 'CREATE TABLE shop_wc_orders ( id INTEGER PRIMARY KEY, billing_email TEXT, ip_address TEXT, user_agent TEXT, customer_note TEXT )' );
		$this->wpdb->exec( "INSERT INTO shop_wc_orders VALUES ( 1, 'alice@real.test', '192.0.2.1', 'Agent', 'Note' )" );

		$this->run_command( array( 'prefix' => 'shop_' ) );

		$this->assertContains( 'shop_wc_orders', $this->summary_tables() );
		$orders = $this->wpdb->rows( 'shop_wc_orders' );
		$this->assertNotSame( 'alice@real.test', $orders['1']->billing_email );
	}

	public function test_tables_option_accepts_tables_from_a_rules_file() {
		$this->create_users_table( 'users' );
		$this->create_backup_table( 'my_backup_table', 3 );
		$users = $this->wpdb->rows( 'users' );

		$this->run_command(
			array(
				'prefix' => '',
				'rules'  => $this->private_rules_file(),
				'tables' => 'my_backup_table',
			)
		);

		$this->assertSame( array( 'my_backup_table' ), $this->summary_tables() );
		$this->assertEquals( $users, $this->wpdb->rows( 'users' ) );
	}

	public function test_run_without_prefix_or_rules_is_unchanged() {
		$this->create_users_table( 'wp_users' );
		$this->create_backup_table( 'wp_my_backup_table', 3 );
		$backup = $this->wpdb->rows( 'wp_my_backup_table' );

		$this->run_command( array() );

		$this->assertSame( array( 'wp_users' ), $this->summary_tables() );
		$this->assertEquals( $backup, $this->wpdb->rows( 'wp_my_backup_table' ), 'No rules for it without --rules.' );
	}

	public function test_rules_without_prefix_use_the_site_prefix() {
		$this->create_backup_table( 'my_backup_table', 3 );
		$this->create_backup_table( 'wp_my_backup_table', 3 );
		$unprefixed = $this->wpdb->rows( 'my_backup_table' );

		$this->run_command( array( 'rules' => $this->private_rules_file() ) );

		$this->assertSame( array( 'wp_my_backup_table' ), $this->summary_tables() );
		$this->assertEquals( $unprefixed, $this->wpdb->rows( 'my_backup_table' ) );
	}

	/**
	 * @param string $pattern
	 * @param string $value
	 */
	protected function assertMatchesPattern( $pattern, $value ) {
		$this->assertSame( 1, preg_match( $pattern, $value ), "$value does not match $pattern" );
	}
}
