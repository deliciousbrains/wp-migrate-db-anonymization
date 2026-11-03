<?php
/**
 * API keys, tokens and secrets.
 *
 * Not personal data, but they grant access to the live site or its customers'
 * orders, downloads and licenses, so they shouldn't leave it. Each one is
 * replaced with a random value of the same shape, e.g. 32 hex characters for
 * an MD5-style key, so code that checks the format keeps working. The
 * replacements aren't usable credentials: generate new keys on the copy if you
 * need working ones.
 *
 * Always loaded. Each rule only does anything if its table or meta key exists.
 * Easy Digital Downloads credentials are in credentials-easy-digital-downloads.php,
 * which only loads on sites with EDD data. To keep the original credentials,
 * turn both files off with the `wpmdb_anonymization_load_config_file` filter
 * (see the README).
 *
 * Meta table rules are limited to a list of meta keys, never a regular
 * expression, so the WP-CLI command can fetch just those rows.
 */

$empty_serialized_array = array(
	'fake_data_type' => 'randomElement',
	'fake_data_args' => array( array( 'a:0:{}' ) ),
);

// Order meta, in postmeta or, with WooCommerce HPOS, wc_orders_meta.
$order_token_rules = array(
	array(
		// WooCommerce Print Invoice & Delivery Note guest access token (32 hex
		// characters) and Metorik abandoned cart token (MD5).
		'constraint'     => array( 'meta_key' => array( 'in' => array( '_guest_access_token', '_metorik_cart_token' ) ) ),
		'fake_data_type' => 'md5',
	),
	array(
		// WooCommerce Print Invoice & Delivery Note: 24 hex character token that
		// makes the URL of each order's PDF documents unguessable.
		'constraint'     => array(
			'meta_key' => array(
				'in' => array(
					'_wcdn_invoice_pdf_token',
					'_wcdn_deliverynote_pdf_token',
					'_wcdn_receipt_pdf_token',
					'_wcdn_creditnote_pdf_token',
					'_wcdn_packingslip_pdf_token',
				),
			),
		),
		'fake_data_type' => 'regexify',
		'fake_data_args' => array( '[a-f0-9]{24}' ),
	),
);

return array(
	'usermeta'             => array(
		'meta_value' => array(
			array(
				// Application passwords (WordPress core) are an array of hashed
				// passwords, with the IP address each was last used from. Two Factor
				// backup codes are an array of hashed codes.
				'constraint' => array( 'meta_key' => array( 'in' => array( '_application_passwords', '_two_factor_backup_codes' ) ) ),
			) + $empty_serialized_array,
			array(
				// Two Factor email login token (wp_hash() of the code) and Metorik
				// abandoned cart token.
				'constraint'     => array( 'meta_key' => array( 'in' => array( '_two_factor_email_token', '_metorik_cart_token' ) ) ),
				'fake_data_type' => 'md5',
			),
			array(
				// Two Factor TOTP secret: 160 bits, base32 encoded.
				'constraint'     => array( 'meta_key' => '_two_factor_totp_key' ),
				'fake_data_type' => 'regexify',
				'fake_data_args' => array( '[A-Z2-7]{32}' ),
			),
		),
	),
	'postmeta'             => array(
		'meta_value' => $order_token_rules,
	),
	'wc_orders_meta'       => array(
		'meta_value' => $order_token_rules,
	),
	'woocommerce_api_keys' => array(
		// HMAC-SHA256 of the key.
		'consumer_key'    => array( 'fake_data_type' => 'sha256' ),
		'consumer_secret' => array(
			'fake_data_type' => 'regexify',
			'fake_data_args' => array( 'cs_[a-f0-9]{40}' ),
		),
		// Last 7 characters of the key, shown in the admin.
		'truncated_key'   => array(
			'fake_data_type' => 'regexify',
			'fake_data_args' => array( '[a-f0-9]{7}' ),
		),
		// Serialized array of recently used OAuth 1.0a nonces.
		'nonces'          => $empty_serialized_array,
	),
	'wc_webhooks'          => array(
		// Used to sign webhook payloads.
		'secret'       => array(
			'fake_data_type' => 'regexify',
			'fake_data_args' => array( '[A-Za-z0-9]{50}' ),
		),
		// Endpoints often include a token. Replacing them also stops the copy
		// sending webhooks to live services.
		'delivery_url' => array( 'fake_data_type' => 'url' ),
	),
);
