<?php

namespace WPMDB\Anonymization\Tests\Unit;

use WPMDB\Anonymization\Anonymizer;
use WPMDB\Anonymization\Config\Loader;
use WPMDB\Anonymization\Tests\Doubles\TablesWpdb;
use WPMDB\Anonymization\Tests\TestCase;

/**
 * Behaviour of the Gravity Forms, Redirection and credentials rules.
 */
class FormsLogsCredentialsRulesTest extends TestCase {

	/**
	 * @param string $file Config file name without extension.
	 *
	 * @return array
	 */
	protected function config_file( $file ) {
		return include WPMDB_ANONYMIZATION_PLUGIN_DIR . '/config/' . $file . '.php';
	}

	/**
	 * @param array $data
	 *
	 * @return Anonymizer
	 */
	protected function make_anonymizer( array $data ) {
		return new Anonymizer( $this->make_config( $data ), $this->make_faker() );
	}

	/**
	 * @return array
	 */
	public static function gravity_forms_field_key_provider() {
		return array(
			'field'          => array( '1', true ),
			'multi-digit'    => array( '12', true ),
			'sub-input'      => array( '2.3', true ),
			'add-on status'  => array( 'gravityformsmailchimp_is_fulfilled', false ),
			'partial entry'  => array( 'partial_entry_id', false ),
			'not a field ID' => array( '1.2.3', false ),
			'trailing text'  => array( '1a', false ),
		);
	}

	/**
	 * @dataProvider gravity_forms_field_key_provider
	 */
	public function test_gravity_forms_only_replaces_field_values( $meta_key, $replaced ) {
		$anonymizer = $this->make_anonymizer( $this->config_file( 'gravity-forms' ) );
		$row        = $this->row(
			array(
				'form_id'    => '3',
				'entry_id'   => '10',
				'meta_key'   => $meta_key,
				'meta_value' => 'jane@example.org',
			)
		);

		$value = $anonymizer->anonymize_value( 'gf_entry_meta', 'meta_value', $row, $row->meta_value );

		if ( $replaced ) {
			$this->assertNotSame( 'jane@example.org', $value );
		} else {
			$this->assertSame( 'jane@example.org', $value );
		}
	}

	public function test_gravity_forms_field_rule_can_be_overridden_per_form() {
		// The example from the README.
		add_filter(
			'wpmdb_anonymization_config',
			function ( $config ) {
				array_unshift(
					$config['gf_entry_meta']['meta_value'],
					array(
						'constraint'     => array(
							'form_id'  => '3',
							'meta_key' => '2',
						),
						'fake_data_type' => 'email',
					)
				);

				return $config;
			}
		);

		$loader     = new Loader( WPMDB_ANONYMIZATION_PLUGIN_FILE );
		$anonymizer = $this->make_anonymizer( $loader->load( new TablesWpdb( array( 'gf_entry' ) ) ) );

		$form_3 = $this->row( array( 'form_id' => '3', 'meta_key' => '2', 'meta_value' => 'jane@example.org' ) );
		$form_4 = $this->row( array( 'form_id' => '4', 'meta_key' => '2', 'meta_value' => 'jane@example.org' ) );

		$this->assertStringContainsString( '@', $anonymizer->anonymize_value( 'gf_entry_meta', 'meta_value', $form_3, $form_3->meta_value ) );
		$this->assertStringNotContainsString( '@', $anonymizer->anonymize_value( 'gf_entry_meta', 'meta_value', $form_4, $form_4->meta_value ) );
	}

	public function test_gravity_forms_field_can_be_kept_per_form() {
		// The example from the README.
		add_filter(
			'wpmdb_anonymization_pre_rule_valid',
			function ( $valid, $rule, $row ) {
				if ( isset( $row->entry_id, $row->form_id, $row->meta_key ) && '3' === (string) $row->form_id && '7' === $row->meta_key ) {
					return false;
				}

				return $valid;
			},
			10,
			3
		);

		$anonymizer = $this->make_anonymizer( $this->config_file( 'gravity-forms' ) );
		$kept       = $this->row( array( 'entry_id' => '10', 'form_id' => '3', 'meta_key' => '7', 'meta_value' => 'Large' ) );
		$replaced   = $this->row( array( 'entry_id' => '11', 'form_id' => '4', 'meta_key' => '7', 'meta_value' => 'Large' ) );

		$this->assertSame( 'Large', $anonymizer->anonymize_value( 'gf_entry_meta', 'meta_value', $kept, $kept->meta_value ) );
		$this->assertNotSame( 'Large', $anonymizer->anonymize_value( 'gf_entry_meta', 'meta_value', $replaced, $replaced->meta_value ) );
	}

	public function test_gravity_forms_draft_submission_becomes_an_empty_json_object() {
		$anonymizer = $this->make_anonymizer( $this->config_file( 'gravity-forms' ) );
		$row        = $this->row( array( 'submission' => '{"submitted_values":{"1":"Jane"}}' ) );

		foreach ( array( 'gf_draft_submissions', 'rg_incomplete_submissions' ) as $table ) {
			$this->assertSame( '{}', $anonymizer->anonymize_value( $table, 'submission', $row, $row->submission ) );
		}
	}

	public function test_redirection_keeps_the_url_and_empties_request_data() {
		$data       = $this->config_file( 'redirection' );
		$anonymizer = $this->make_anonymizer( $data );
		$row        = $this->row(
			array(
				'url'          => '/old-page',
				'request_data' => '{"headers":{"X-Forwarded-For":"203.0.113.9"}}',
			)
		);

		foreach ( array( 'redirection_404', 'redirection_logs' ) as $table ) {
			$this->assertArrayNotHasKey( 'url', $data[ $table ] );
			$this->assertArrayNotHasKey( 'domain', $data[ $table ] );
			$this->assertSame( '{}', $anonymizer->anonymize_value( $table, 'request_data', $row, $row->request_data ) );
		}
	}

	/**
	 * A 32-character hex value shaped like an EDD key, built at runtime so it
	 * isn't a hard-coded credential.
	 *
	 * @return string
	 */
	private static function sample_key() {
		return str_repeat( implode( '', range( 0, 9 ) ) . 'abcdef', 2 );
	}

	public function test_edd_api_keys_stored_as_meta_keys_are_replaced() {
		$anonymizer = $this->make_anonymizer( $this->config_file( 'credentials' ) );
		$stored_key = self::sample_key();
		$row        = $this->row(
			array(
				'meta_key'   => $stored_key,
				'meta_value' => 'edd_user_secret_key',
			)
		);

		$key = $anonymizer->anonymize_value( 'usermeta', 'meta_key', $row, $row->meta_key );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $key );
		$this->assertNotSame( $stored_key, $key );
		$this->assertSame( 'edd_user_secret_key', $anonymizer->anonymize_value( 'usermeta', 'meta_value', $row, $row->meta_value ), 'The value marks the row as a secret key, so it is kept.' );

		$other = $this->row( array( 'meta_key' => 'first_name', 'meta_value' => 'Jane' ) );
		$this->assertSame( 'first_name', $anonymizer->anonymize_value( 'usermeta', 'meta_key', $other, $other->meta_key ) );
	}

	public function test_edd_api_keys_from_before_2_4_are_replaced() {
		$anonymizer = $this->make_anonymizer( $this->config_file( 'credentials' ) );
		$row        = $this->row(
			array(
				'meta_key'   => 'edd_user_public_key',
				'meta_value' => self::sample_key(),
			)
		);

		$this->assertSame( 'edd_user_public_key', $anonymizer->anonymize_value( 'usermeta', 'meta_key', $row, $row->meta_key ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $anonymizer->anonymize_value( 'usermeta', 'meta_value', $row, $row->meta_value ) );
	}

	public function test_edd_api_request_log_keeps_public_requests() {
		$anonymizer = $this->make_anonymizer( $this->config_file( 'credentials' ) );
		$public     = $this->row( array( 'api_key' => 'public', 'token' => 'public' ) );
		$keyed      = $this->row( array( 'api_key' => self::sample_key(), 'token' => strrev( self::sample_key() ) ) );

		foreach ( array( 'api_key', 'token' ) as $column ) {
			$this->assertSame( 'public', $anonymizer->anonymize_value( 'edd_logs_api_requests', $column, $public, 'public' ) );

			$value = $anonymizer->anonymize_value( 'edd_logs_api_requests', $column, $keyed, $keyed->{$column} );
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $value );
			$this->assertNotSame( $keyed->{$column}, $value );
		}
	}

	/**
	 * Table, column, meta key (or null) and the pattern the replacement must match.
	 *
	 * @return array
	 */
	public static function credential_shape_provider() {
		return array(
			'WooCommerce consumer key'    => array( 'woocommerce_api_keys', 'consumer_key', null, '/^[a-f0-9]{64}$/' ),
			'WooCommerce consumer secret' => array( 'woocommerce_api_keys', 'consumer_secret', null, '/^cs_[a-f0-9]{40}$/' ),
			'WooCommerce truncated key'   => array( 'woocommerce_api_keys', 'truncated_key', null, '/^[a-f0-9]{7}$/' ),
			'WooCommerce API nonces'      => array( 'woocommerce_api_keys', 'nonces', null, '/^a:0:\{\}$/' ),
			'WooCommerce webhook secret'  => array( 'wc_webhooks', 'secret', null, '/^[A-Za-z0-9]{50}$/' ),
			'Application passwords'       => array( 'usermeta', 'meta_value', '_application_passwords', '/^a:0:\{\}$/' ),
			'Two Factor TOTP secret'      => array( 'usermeta', 'meta_value', '_two_factor_totp_key', '/^[A-Z2-7]{32}$/' ),
			'Two Factor email token'      => array( 'usermeta', 'meta_value', '_two_factor_email_token', '/^[a-f0-9]{32}$/' ),
			'Metorik user cart token'     => array( 'usermeta', 'meta_value', '_metorik_cart_token', '/^[a-f0-9]{32}$/' ),
			'Guest access token'          => array( 'postmeta', 'meta_value', '_guest_access_token', '/^[a-f0-9]{32}$/' ),
			'Invoice PDF token'           => array( 'postmeta', 'meta_value', '_wcdn_invoice_pdf_token', '/^[a-f0-9]{24}$/' ),
			'HPOS invoice PDF token'      => array( 'wc_orders_meta', 'meta_value', '_wcdn_invoice_pdf_token', '/^[a-f0-9]{24}$/' ),
			'HPOS Metorik cart token'     => array( 'wc_orders_meta', 'meta_value', '_metorik_cart_token', '/^[a-f0-9]{32}$/' ),
			'EDD 2.x log token'           => array( 'postmeta', 'meta_value', '_edd_log_token', '/^[a-f0-9]{32}$/' ),
			'EDD SL 2.x license key'      => array( 'postmeta', 'meta_value', '_edd_sl_key', '/^[a-f0-9]{32}$/' ),
			'EDD SL license key'          => array( 'edd_licenses', 'license_key', null, '/^[a-f0-9]{32}$/' ),
		);
	}

	/**
	 * @dataProvider credential_shape_provider
	 */
	public function test_credentials_are_replaced_with_values_of_the_same_shape( $table, $column, $meta_key, $pattern ) {
		$anonymizer = $this->make_anonymizer( $this->config_file( 'credentials' ) );
		$original   = 'original-secret-value';
		$row        = $this->row( null === $meta_key ? array( $column => $original ) : array( 'meta_key' => $meta_key, $column => $original ) );

		$this->assertMatchesRegularExpression( $pattern, (string) $anonymizer->anonymize_value( $table, $column, $row, $original ) );
	}

	public function test_credentials_meta_rules_can_be_pushed_into_sql() {
		$meta_tables = array( 'usermeta', 'postmeta', 'wc_orders_meta' );
		$data        = $this->config_file( 'credentials' );

		foreach ( $meta_tables as $table ) {
			foreach ( $data[ $table ] as $column => $rules ) {
				foreach ( $this->make_config( $data )->rules( $table, $column ) as $index => $rule ) {
					$this->assertNotNull( $rule->get_constraint_values(), "credentials.php {$table}.{$column}[{$index}] is limited to a list of values, not a regular expression." );
				}
			}
		}
	}
}
