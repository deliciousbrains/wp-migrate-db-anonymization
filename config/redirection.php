<?php
/**
 * Redirection plugin redirect and 404 logs.
 *
 * The requested URL and domain are kept so the logs are still useful for
 * debugging redirects on the copy. The visitor's IP address, user agent and
 * referrer, and any logged request headers, are replaced.
 */

$log_rules = array(
	'ip'           => array( 'fake_data_type' => 'ipv4' ),
	'agent'        => array( 'fake_data_type' => 'userAgent' ),
	'referrer'     => array( 'fake_data_type' => 'url' ),
	// JSON. With the "Log HTTP header" option on it holds every request header,
	// including forwarded IP addresses; redirect logs can also hold the code
	// that issued the redirect. Replace with an empty JSON object.
	'request_data' => array(
		'fake_data_type' => 'randomElement',
		'fake_data_args' => array( array( '{}' ) ),
	),
);

return array(
	'redirection_404'  => $log_rules,
	'redirection_logs' => $log_rules,
);
