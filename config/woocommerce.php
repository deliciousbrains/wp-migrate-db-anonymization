<?php
/**
 * WooCommerce, including High-Performance Order Storage (HPOS) and
 * WooCommerce Subscriptions, which stores subscriptions as orders.
 *
 * Only personal data is anonymized. Order totals, dates, statuses, product
 * data and IDs are left untouched.
 */

// VAT numbers identify sole traders as well as companies. The keys come from
// the WooCommerce EU VAT Number extension, YITH WooCommerce EU VAT and similar
// plugins, which use the same keys for customer and order meta.
$vat_number_rule = array(
	'constraint'     => array( 'meta_key' => array( 'in' => array( 'billing_vat_number', '_billing_vat_number', 'vat_number', 'billing_yweu_vat', '_billing_yweu_vat', 'yweu_vat_number', 'yweu_billing_vat' ) ) ),
	'fake_data_type' => 'regexify',
	'fake_data_args' => array( '[A-Z]{2}[0-9]{9}' ),
);

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
		// ywev_* keys are YITH WooCommerce EU VAT's evidence of the customer's location.
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_city', '_shipping_city', 'ywev_CITY' ) ) ),
		'fake_data_type' => 'city',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_postcode', '_shipping_postcode' ) ) ),
		'fake_data_type' => 'postcode',
	),
	array(
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_billing_state', '_shipping_state', 'ywev_STATE' ) ) ),
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
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_customer_ip_address', 'ywev_IP_ADDRESS' ) ) ),
		'fake_data_type' => 'ipv4',
	),
	array(
		// Order attribution (WooCommerce 8.5+) also records the customer's user agent.
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_customer_user_agent', '_wc_order_attribution_user_agent' ) ) ),
		'fake_data_type' => 'userAgent',
	),
	array(
		// Stripe and PayPal Express customer, card and payer IDs.
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_stripe_customer_id', '_stripe_card_id', '_wc_paypal_express_customer_id', '_wc_paypal_express_payer_id' ) ) ),
		'fake_data_type' => 'ean13',
	),
	$vat_number_rule,
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
				// Order attribution data is also saved to the customer when they register.
				'constraint'     => array( 'meta_key' => '_wc_order_attribution_user_agent' ),
				'fake_data_type' => 'userAgent',
			),
			array(
				// PayPal Express customer ID and billing agreement.
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'wc_paypal_express_customer_id', '_paypal_billing_agreement' ) ) ),
				'fake_data_type' => 'ean13',
			),
			$vat_number_rule,
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
		'meta_value' => array_merge(
			$order_meta_rules,
			array(
				array(
					// Coupon email restrictions, a serialized array of customer email
					// addresses. Replaced with a single fake address so the coupon stays
					// restricted. Unrestricted coupons store an empty list (a:0:{}),
					// which is left alone so they stay unrestricted.
					'constraint'            => array(
						'meta_key'   => 'customer_email',
						'meta_value' => array( 'regex' => '/^a:[1-9]/' ),
					),
					'fake_data_type'        => 'email',
					'post_process_function' => 'WPMDB\\Anonymization\\Config\\Replacement::serialized_list',
				),
			)
		),
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
			array(
				// PayPal tokens store the payer's PayPal email address.
				'constraint'     => array( 'meta_key' => 'email' ),
				'fake_data_type' => 'email',
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
	'wc_stock_notifications'                       => array(
		// Back in stock notification sign-ups.
		'user_email' => array( 'fake_data_type' => 'email' ),
	),
	'wc_email_unsubscribes'                        => array(
		// Unsalted SHA-256 of the email address, which can be matched by hashing a
		// known address. Replaced with a random hash of the same length.
		'email_hash' => array( 'fake_data_type' => 'sha256' ),
	),
	'comments'                                     => array(
		// Order notes can contain customer details, e.g. email addresses.
		'comment_content' => array(
			'constraint'     => array( 'comment_type' => 'order_note' ),
			'fake_data_type' => 'sentence',
		),
	),
);
