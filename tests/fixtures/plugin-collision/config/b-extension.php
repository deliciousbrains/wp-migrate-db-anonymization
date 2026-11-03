<?php
return array(
	'users' => array(
		'user_email' => array(
			'fake_data_type' => 'safeEmail',
			'constraint'     => 'WPMDB\\Anonymization\\Config\\Constraint::is_not_whitelisted_user',
		),
	),
);
