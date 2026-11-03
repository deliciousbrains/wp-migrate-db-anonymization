<?php
return array(
	'usermeta' => array(
		'meta_value' => array(
			array(
				'constraint'     => array( 'meta_key' => 'billing_phone' ),
				'fake_data_type' => 'phoneNumber',
			),
		),
	),
	'postmeta' => array(
		'meta_value' => array(
			array(
				'constraint'     => array( 'meta_key' => '_billing_email' ),
				'fake_data_type' => 'email',
			),
		),
	),
);
