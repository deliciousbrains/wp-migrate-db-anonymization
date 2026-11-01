<?php
/**
 * Easy Digital Downloads 3.x custom tables, plus legacy EDD 2.x payment meta
 * and customer meta that may remain after the 3.0 migration.
 *
 * Only personal data is anonymized. Order totals, dates, statuses, product
 * data and IDs are left untouched.
 */

$address_rules = array(
	'name'        => array( 'fake_data_type' => 'name' ),
	'address'     => array( 'fake_data_type' => 'streetAddress' ),
	'address2'    => array( 'fake_data_type' => 'secondaryAddress' ),
	'city'        => array( 'fake_data_type' => 'city' ),
	'region'      => array( 'fake_data_type' => 'state' ),
	'postal_code' => array( 'fake_data_type' => 'postcode' ),
	'country'     => array( 'fake_data_type' => 'countryCode' ),
);

$gateway_customer_id_rule = array(
	// Stripe and PayPal customer IDs, live and test mode.
	'constraint'     => array( 'meta_key' => array( 'regex' => '/^(_edd_stripe_customer_id|_edd_paypal_vault_customer_id)(_test)?$/' ) ),
	'fake_data_type' => 'ean13',
);

return array(
	'edd_customers'                => array(
		'email' => array( 'fake_data_type' => 'email' ),
		'name'  => array( 'fake_data_type' => 'name' ),
	),
	'edd_customer_email_addresses' => array(
		'email' => array( 'fake_data_type' => 'email' ),
	),
	'edd_customer_addresses'       => $address_rules,
	'edd_customermeta'             => array(
		'meta_value' => array(
			array(
				'constraint'     => array( 'meta_key' => 'company_name' ),
				'fake_data_type' => 'company',
			),
			array(
				'constraint'     => array( 'meta_key' => 'phone' ),
				'fake_data_type' => 'phoneNumber',
			),
			array(
				'constraint'     => array( 'meta_key' => 'last_login_ip' ),
				'fake_data_type' => 'ipv4',
			),
			array(
				// EDD 2.x, moved to edd_customer_email_addresses in 3.0.
				'constraint'     => array( 'meta_key' => 'additional_email' ),
				'fake_data_type' => 'email',
			),
			$gateway_customer_id_rule,
		),
	),
	'edd_orders'                   => array(
		'email' => array( 'fake_data_type' => 'email' ),
		'ip'    => array( 'fake_data_type' => 'ipv4' ),
	),
	'edd_order_addresses'          => $address_rules,
	'edd_ordermeta'                => array(
		'meta_value' => array(
			array(
				'constraint'     => array( 'meta_key' => 'company_name' ),
				'fake_data_type' => 'company',
			),
			array(
				'constraint'     => array( 'meta_key' => '_edd_phone' ),
				'fake_data_type' => 'phoneNumber',
			),
			$gateway_customer_id_rule,
		),
	),
	'edd_notes'                    => array(
		// Order and customer notes can contain customer details, e.g. email addresses.
		'content' => array( 'fake_data_type' => 'sentence' ),
	),
	'edd_payment_tokens'           => array(
		'token_id'            => array( 'fake_data_type' => 'md5' ),
		'gateway_customer_id' => array( 'fake_data_type' => 'ean13' ),
	),
	'edd_logs_api_requests'        => array(
		'ip' => array( 'fake_data_type' => 'ipv4' ),
	),
	'edd_logs_file_downloads'      => array(
		'ip'         => array( 'fake_data_type' => 'ipv4' ),
		'user_agent' => array( 'fake_data_type' => 'userAgent' ),
	),
	'edd_logs_emails'              => array(
		'email' => array( 'fake_data_type' => 'email' ),
	),
	'usermeta'                     => array(
		'meta_value' => array(
			$gateway_customer_id_rule,
		),
	),
	'postmeta'                     => array(
		'meta_value' => array(
			array(
				// EDD 2.x payments, stored as posts.
				'constraint'     => array( 'meta_key' => '_edd_payment_user_email' ),
				'fake_data_type' => 'email',
			),
			array(
				'constraint'     => array( 'meta_key' => '_edd_payment_user_ip' ),
				'fake_data_type' => 'ipv4',
			),
		),
	),
);
