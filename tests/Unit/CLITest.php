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

		$this->assertSame( array( 'shop_users', 'shop_usermeta' ), $this->summary_tables() );

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
