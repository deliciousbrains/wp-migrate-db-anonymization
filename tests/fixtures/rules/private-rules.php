<?php
/**
 * Extra rules in the same format as the bundled config files. Used by the
 * config files filter and --rules tests.
 */

return array(
	'my_backup_table' => array(
		'email'     => array(
			'fake_data_type' => 'email',
		),
		'full_name' => array(
			'fake_data_type' => 'name',
		),
	),
	'usermeta'        => array(
		'meta_value' => array(
			array(
				'constraint'     => array( 'meta_key' => 'private_phone' ),
				'fake_data_type' => 'phoneNumber',
			),
		),
	),
);
