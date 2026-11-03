<?php

namespace WPMDB\Anonymization\Tests\Unit\Config;

use WPMDB\Anonymization\Config\Loader;
use WPMDB\Anonymization\Tests\Doubles\TablesWpdb;
use WPMDB\Anonymization\Tests\TestCase;

/**
 * Which shipped config files load, depending on the tables in the database.
 */
class LoaderTableRequirementsTest extends TestCase {

	/**
	 * Load the shipped config against a database with the given tables.
	 *
	 * @param string[] $tables Table names without the prefix.
	 *
	 * @return array
	 */
	protected function load_with_tables( array $tables ) {
		$loader = new Loader( WPMDB_ANONYMIZATION_PLUGIN_FILE );

		return $loader->load( new TablesWpdb( $tables ) );
	}

	/**
	 * @return array
	 */
	public static function gravity_forms_table_provider() {
		return array(
			'entries'             => array( 'gf_entry' ),
			'forms without entry' => array( 'gf_form' ),
			'Gravity Forms 1.x'   => array( 'rg_lead' ),
		);
	}

	/**
	 * @dataProvider gravity_forms_table_provider
	 */
	public function test_gravity_forms_rules_load_when_a_gravity_forms_table_exists( $table ) {
		$config = $this->load_with_tables( array( $table ) );

		$this->assertArrayHasKey( 'gf_entry_meta', $config );
		$this->assertArrayHasKey( 'rg_lead_detail', $config );
	}

	public function test_gravity_forms_rules_do_not_load_without_its_tables() {
		$config = $this->load_with_tables( array( 'posts', 'gf_entry_meta_backup', 'redirection_404' ) );

		$this->assertArrayNotHasKey( 'gf_entry', $config );
		$this->assertArrayNotHasKey( 'gf_entry_meta', $config );
		$this->assertArrayNotHasKey( 'rg_lead', $config );
	}

	/**
	 * @return array
	 */
	public static function redirection_table_provider() {
		return array(
			'404 log'      => array( 'redirection_404' ),
			'redirect log' => array( 'redirection_logs' ),
		);
	}

	/**
	 * @dataProvider redirection_table_provider
	 */
	public function test_redirection_rules_load_when_a_log_table_exists( $table ) {
		$config = $this->load_with_tables( array( $table ) );

		$this->assertArrayHasKey( 'redirection_404', $config );
		$this->assertArrayHasKey( 'redirection_logs', $config );
	}

	public function test_redirection_rules_do_not_load_without_its_tables() {
		$config = $this->load_with_tables( array( 'posts', 'redirection_items', 'gf_entry' ) );

		$this->assertArrayNotHasKey( 'redirection_404', $config );
		$this->assertArrayNotHasKey( 'redirection_logs', $config );
	}

	public function test_credentials_rules_always_load() {
		$config = $this->load_with_tables( array() );

		$this->assertArrayHasKey( 'woocommerce_api_keys', $config );
		$this->assertArrayHasKey( 'wc_webhooks', $config );
		$this->assertArrayHasKey( 'usermeta', $config );
	}

	/**
	 * @return array
	 */
	public static function easy_digital_downloads_table_provider() {
		return array(
			'customers' => array( 'edd_customers' ),
			'orders'    => array( 'edd_orders' ),
		);
	}

	/**
	 * @dataProvider easy_digital_downloads_table_provider
	 */
	public function test_easy_digital_downloads_credentials_load_when_an_edd_table_exists( $table ) {
		$config = $this->load_with_tables( array( $table ) );

		$this->assertArrayHasKey( 'edd_licenses', $config );
		$this->assertArrayHasKey( 'edd_logs_api_requests', $config );
		$this->assertArrayHasKey( 'meta_key', $config['usermeta'] );
	}

	public function test_easy_digital_downloads_credentials_do_not_load_without_its_tables() {
		$config = $this->load_with_tables( array( 'posts', 'woocommerce_order_items' ) );

		$this->assertArrayNotHasKey( 'edd_licenses', $config );
		$this->assertArrayNotHasKey( 'edd_logs_api_requests', $config );
		$this->assertArrayNotHasKey( 'meta_key', $config['usermeta'], 'No usermeta.meta_value constraint, so the WP-CLI command can push the usermeta rules into SQL.' );
	}

	public function test_credentials_rules_can_be_turned_off() {
		// The example from the README.
		add_filter(
			'wpmdb_anonymization_load_config_file',
			function ( $load, $name ) {
				return 0 === strpos( $name, 'credentials' ) ? false : $load;
			},
			10,
			2
		);

		$config = $this->load_with_tables( array( 'edd_orders' ) );

		$this->assertArrayNotHasKey( 'woocommerce_api_keys', $config );
		$this->assertArrayNotHasKey( 'edd_licenses', $config );
		$this->assertArrayNotHasKey( 'meta_key', $config['usermeta'] );
		$this->assertArrayHasKey( 'users', $config, 'Other files still load.' );
		$this->assertArrayHasKey( 'edd_customers', $config, 'Other files still load.' );
	}
}
