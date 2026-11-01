<?php
/**
 * WooCommerce, including High-Performance Order Storage (HPOS) and
 * WooCommerce Subscriptions, which stores subscriptions as orders.
 *
 * Only personal data is anonymized. Order totals, dates, statuses, product
 * data and IDs are left untouched.
 */

// Order address and customer meta, stored in postmeta (legacy order storage)
// or wc_orders_meta (HPOS) depending on the store's order storage setting.
$order_meta_rules = array(
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_first_name', '_shipping_first_name', 'Payer first name' ) ) ),
		'fake_data_type' => 'firstName',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_last_name', '_shipping_last_name', 'Payer last name' ) ) ),
		'fake_data_type' => 'lastName',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_company', '_shipping_company' ) ) ),
		'fake_data_type' => 'company',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_address_1', '_shipping_address_1' ) ) ),
		'fake_data_type' => 'streetAddress',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_address_2', '_shipping_address_2' ) ) ),
		'fake_data_type' => 'secondaryAddress',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_city', '_shipping_city' ) ) ),
		'fake_data_type' => 'city',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_postcode', '_shipping_postcode' ) ) ),
		'fake_data_type' => 'postcode',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_state', '_shipping_state' ) ) ),
		'fake_data_type' => 'state',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_country', '_shipping_country' ) ) ),
		'fake_data_type' => 'countryCode',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_email', 'Payer PayPal address' ) ) ),
		'fake_data_type' => 'email',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_phone', '_shipping_phone' ) ) ),
		'fake_data_type' => 'phoneNumber',
	),
	array(
		// Concatenated address used for admin order search.
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_address_index', '_shipping_address_index' ) ) ),
		'fake_data_type' => 'address',
	),
	array(
		'constraint'     => array( 'meta_key' => '_customer_ip_address' ),
		'fake_data_type' => 'ipv4',
	),
	array(
		'constraint'     => array( 'meta_key' => '_customer_user_agent' ),
		'fake_data_type' => 'userAgent',
	),
	array(
		'constraint'     => array( 'meta_key' => '_stripe_customer_id' ),
		'fake_data_type' => 'ean13',
	),
);

// HPOS order addresses, one row each for the billing and shipping address.
$address_rules = array(
	'first_name' => array( 'fake_data_type' => 'firstName' ),
	'last_name'  => array( 'fake_data_type' => 'lastName' ),
	'company'    => array( 'fake_data_type' => 'company' ),
	'address_1'  => array( 'fake_data_type' => 'streetAddress' ),
	'address_2'  => array( 'fake_data_type' => 'secondaryAddress' ),
	'city'       => array( 'fake_data_type' => 'city' ),
	'state'      => array( 'fake_data_type' => 'state' ),
	'postcode'   => array( 'fake_data_type' => 'postcode' ),
	'country'    => array( 'fake_data_type' => 'countryCode' ),
	'email'      => array( 'fake_data_type' => 'email' ),
	'phone'      => array( 'fake_data_type' => 'phoneNumber' ),
);

// Session data includes the customer's addresses. Replace with an empty session.
$empty_serialized_array = array(
	'fake_data_type' => 'randomElement',
	'fake_data_args' => array( array( 'a:0:{}' ) ),
);

return array(
	'usermeta'                                     => array(
		'meta_value' => array(
			array(
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_first_name', 'shipping_first_name', 'Payer first name' ) ) ),
				'fake_data_type' => 'firstName',
			),
			array(
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_last_name', 'shipping_last_name', 'Payer last name' ) ) ),
				'fake_data_type' => 'lastName',
			),
			array(
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_company', 'shipping_company' ) ) ),
				'fake_data_type' => 'company',
			),
			array(
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_address_1', 'shipping_address_1' ) ) ),
				'fake_data_type' => 'streetAddress',
			),
			array(
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_address_2', 'shipping_address_2' ) ) ),
				'fake_data_type' => 'secondaryAddress',
			),
			array(
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_city', 'shipping_city' ) ) ),
				'fake_data_type' => 'city',
			),
			array(
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_postcode', 'shipping_postcode' ) ) ),
				'fake_data_type' => 'postcode',
			),
			array(
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_state', 'shipping_state' ) ) ),
				'fake_data_type' => 'state',
			),
			array(
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_country', 'shipping_country' ) ) ),
				'fake_data_type' => 'countryCode',
			),
			array(
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_email', 'Payer PayPal address' ) ) ),
				'fake_data_type' => 'email',
			),
			array(
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_phone', 'shipping_phone' ) ) ),
				'fake_data_type' => 'phoneNumber',
			),
			array(
				'constraint'     => array( 'meta_key' => '_customer_ip_address' ),
				'fake_data_type' => 'ipv4',
			),
			array(
				// Stripe stores this as a user option, e.g. `wp__stripe_customer_id`.
				'constraint'     => array( 'meta_key' => array( 'regex' => '/_stripe_customer_id$/' ) ),
				'fake_data_type' => 'ean13',
			),
			array(
				// WooPayments user options, e.g. `wp__wcpay_customer_id_live`.
				'constraint'     => array( 'meta_key' => array( 'regex' => '/_wcpay_customer_id(_live|_test)?$/' ) ),
				'fake_data_type' => 'ean13',
			),
		),
	),
	'postmeta'                                     => array(
		'meta_value' => $order_meta_rules,
	),
	'wc_orders'                                    => array(
		'billing_email' => array( 'fake_data_type' => 'email' ),
		'ip_address'    => array( 'fake_data_type' => 'ipv4' ),
		'user_agent'    => array( 'fake_data_type' => 'userAgent' ),
		'customer_note' => array( 'fake_data_type' => 'sentence' ),
	),
	'wc_order_addresses'                           => $address_rules,
	'wc_orders_meta'                               => array(
		'meta_value' => $order_meta_rules,
	),
	'wc_customer_lookup'                           => array(
		'username'       => array( 'fake_data_type' => 'userName' ),
		'first_name'     => array( 'fake_data_type' => 'firstName' ),
		'last_name'      => array( 'fake_data_type' => 'lastName' ),
		'email'          => array( 'fake_data_type' => 'email' ),
		'postcode'       => array( 'fake_data_type' => 'postcode' ),
		'city'           => array( 'fake_data_type' => 'city' ),
		'state'          => array( 'fake_data_type' => 'state' ),
		'country'        => array( 'fake_data_type' => 'countryCode' ),
		'billing_phone'  => array( 'fake_data_type' => 'phoneNumber' ),
		'shipping_phone' => array( 'fake_data_type' => 'phoneNumber' ),
	),
	'woocommerce_payment_tokens'                   => array(
		'token' => array( 'fake_data_type' => 'md5' ),
	),
	'woocommerce_payment_tokenmeta'                => array(
		'meta_value' => array(
			array(
				'constraint'     => array( 'meta_key' => 'last4' ),
				'fake_data_type' => 'numerify',
				'fake_data_args' => array( '####' ),
			),
			array(
				'constraint'     => array( 'meta_key' => 'expiry_month' ),
				'fake_data_type' => 'month',
			),
			array(
				'constraint'     => array( 'meta_key' => 'expiry_year' ),
				'fake_data_type' => 'numerify',
				'fake_data_args' => array( '203#' ),
			),
		),
	),
	'woocommerce_downloadable_product_permissions' => array(
		'user_email' => array( 'fake_data_type' => 'email' ),
	),
	'wc_download_log'                              => array(
		'user_ip_address' => array( 'fake_data_type' => 'ipv4' ),
	),
	'woocommerce_sessions'                         => array(
		'session_value' => $empty_serialized_array,
	),
	'comments'                                     => array(
		// Order notes can contain customer details, e.g. email addresses.
		'comment_content' => array(
			'constraint'     => array( 'comment_type' => 'order_note' ),
			'fake_data_type' => 'sentence',
		),
	),
);
