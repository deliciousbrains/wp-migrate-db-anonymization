<?php
return array(
	'users'    => array(
		'user_login'    => array(
			'fake_data_type' => 'userName',
			'constraint'     => 'WPMDB\\Anonymization\\Config\\Constraint::is_not_whitelisted_user',
		),
		'user_pass'     => array(
			'fake_data_type'        => 'password',
			'post_process_function' => 'WPMDB\\Anonymization\\Config\\Replacement::password',
			'constraint'            => 'WPMDB\\Anonymization\\Config\\Constraint::is_not_whitelisted_user',
		),
		'user_nicename' => array(
			'fake_data_type' => 'userName',
			'constraint'     => 'WPMDB\\Anonymization\\Config\\Constraint::is_not_whitelisted_user',
		),
		'user_email'    => array(
			'fake_data_type' => 'email',
			'constraint'     => 'WPMDB\\Anonymization\\Config\\Constraint::is_not_whitelisted_user',
		),
		'user_url'      => array(
			'fake_data_type' => 'url',
			'constraint'     => 'WPMDB\\Anonymization\\Config\\Constraint::is_not_whitelisted_user',
		),
		'display_name'  => array(
			'fake_data_type' => 'name',
			'constraint'     => 'WPMDB\\Anonymization\\Config\\Constraint::is_not_whitelisted_user',
		),
	),
	'usermeta' => array(
		'meta_value' => array(
			array(
				'constraint'     => array( 'meta_key' => 'first_name' ),
				'fake_data_type' => 'firstName',
			),
			array(
				'constraint'     => array( 'meta_key' => 'last_name' ),
				'fake_data_type' => 'lastName',
			),
			array(
				'constraint'     => array( 'meta_key' => 'nickname' ),
				'fake_data_type' => 'firstName',
			),
			array(
				'constraint'     => array( 'meta_key' => 'description' ),
				'fake_data_type' => 'sentence',
			),
			array(
				// Login sessions and the dashboard events location both store IP addresses,
				// and a pending email change stores the new address. Replace with an empty
				// serialized array.
				'constraint'     => array( 'meta_key' => array( 'in' => array( 'session_tokens', 'community-events-location', '_new_email' ) ) ),
				'fake_data_type' => 'randomElement',
				'fake_data_args' => array( array( 'a:0:{}' ) ),
			),
			array(
				// Registration IP, written by several membership and registration plugins.
				'constraint'     => array( 'meta_key' => 'signup_ip' ),
				'fake_data_type' => 'ipv4',
			),
		),
	),
	'comments' => array(
		'comment_author'       => array(
			'fake_data_type' => 'userName',
		),
		'comment_author_email' => array(
			'fake_data_type' => 'email',
		),
		'comment_author_url'   => array(
			'fake_data_type' => 'url',
		),
		'comment_author_IP'    => array(
			'fake_data_type' => 'ipv4',
		),
		'comment_agent'        => array(
			'fake_data_type' => 'userAgent',
		),
	),
);
