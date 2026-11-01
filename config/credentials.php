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
 * To keep the original credentials, turn the file off with the
 * `wpmdb_anonymization_load_config_file` filter (see the README).
 *
 * Meta table rules are limited to a list of meta keys (or, for EDD API keys,
 * meta values), never a regular expression, so the WP-CLI command can fetch
 * just those rows.
 */

$empty_serialized_array = array(
	'fake_data_type' => 'randomElement',
	'fake_data_args' => array( array( 'a:0:{}' ) ),
);

$edd_api_key_names = array( 'edd_user_public_key', 'edd_user_secret_key' );

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
	'usermeta'              => array(
		'meta_value' => array(
			array(
				// Application passwords (WordPress core) are an array of hashed
				// passwords, with the IP address each was last used from. Two Factor
				// backup codes are an array of hashed codes.
				'constraint' => array( 'meta_key' => array( 'in' => array( '_application_passwords', '_two_factor_backup_codes' ) ) ),
			) + $empty_serialized_array,
			array(
				// Two Factor email login token (wp_hash() of the code), Metorik
				// abandoned cart token, and Easy Digital Downloads API keys stored by
				// EDD before 2.4 (see meta_key below for later versions).
				'constraint'     => array( 'meta_key' => array( 'in' => array_merge( array( '_two_factor_email_token', '_metorik_cart_token' ), $edd_api_key_names ) ) ),
				'fake_data_type' => 'md5',
			),
			array(
				// Two Factor TOTP secret: 160 bits, base32 encoded.
				'constraint'     => array( 'meta_key' => '_two_factor_totp_key' ),
				'fake_data_type' => 'regexify',
				'fake_data_args' => array( '[A-Z2-7]{32}' ),
			),
		),
		'meta_key'   => array(
			array(
				// Easy Digital Downloads stores a user's API public key and secret key
				// as the meta key, with 'edd_user_public_key' or 'edd_user_secret_key'
				// as the value. Both keys are MD5 hashes.
				'constraint'     => array( 'meta_value' => array( 'in' => $edd_api_key_names ) ),
				'fake_data_type' => 'md5',
			),
		),
	),
	'postmeta'              => array(
		'meta_value' => array_merge(
			$order_token_rules,
			array(
				array(
					// EDD 2.x API request logs (stored as posts): the API key and token
					// used. EDD Software Licensing before 3.6 (licenses stored as posts):
					// the license key, and on renewal payments the key that was renewed.
					'constraint'     => array( 'meta_key' => array( 'in' => array( '_edd_log_key', '_edd_log_token', '_edd_sl_key', '_edd_sl_renewal_key' ) ) ),
					'fake_data_type' => 'md5',
				),
			)
		),
	),
	'wc_orders_meta'        => array(
		'meta_value' => $order_token_rules,
	),
	'woocommerce_api_keys'  => array(
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
	'wc_webhooks'           => array(
		// Used to sign webhook payloads.
		'secret'       => array(
			'fake_data_type' => 'regexify',
			'fake_data_args' => array( '[A-Za-z0-9]{50}' ),
		),
		// Endpoints often include a token. Replacing them also stops the copy
		// sending webhooks to live services.
		'delivery_url' => array( 'fake_data_type' => 'url' ),
	),
	'edd_logs_api_requests' => array(
		// The API key and token used, or 'public' for requests made without a
		// key, which is kept.
		'api_key' => array(
			'constraint'     => array( 'api_key' => array( 'regex' => '/^(?!public$)/' ) ),
			'fake_data_type' => 'md5',
		),
		'token'   => array(
			'constraint'     => array( 'token' => array( 'regex' => '/^(?!public$)/' ) ),
			'fake_data_type' => 'md5',
		),
		// Query string of the request, including the key, token and any customer
		// email address or purchase key. Keep just the parameter EDD always sends.
		'request' => array(
			'fake_data_type' => 'randomElement',
			'fake_data_args' => array( array( 'edd-api=' ) ),
		),
	),
	'edd_licenses'          => array(
		// EDD Software Licensing 3.6+. An MD5 hash by default.
		'license_key' => array( 'fake_data_type' => 'md5' ),
	),
);
