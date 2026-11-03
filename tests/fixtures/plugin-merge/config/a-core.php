<?php
return array(
	'users'    => array(
		'user_email' => array(
			'fake_data_type' => 'email',
		),
	),
	'usermeta' => array(
		'meta_value' => array(
			array(
				'constraint'     => array( 'meta_key' => 'first_name' ),
				'fake_data_type' => 'firstName',
			),
		),
	),
);
