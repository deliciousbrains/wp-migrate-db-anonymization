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
	),
);
