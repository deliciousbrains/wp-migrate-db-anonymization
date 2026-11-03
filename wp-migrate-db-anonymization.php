<?php
/*
Plugin Name: WP Migrate DB Anonymization
Plugin URI: https://deliciousbrains.com/wp-migrate-db-pro/
Description: An extension to WP Migrate DB and WP Migrate DB Pro that anonymizes user data.
Author: Delicious Brains
Version: 0.3.4
Author URI: https://deliciousbrains.com
Network: True
Requires at least: 5.9
Requires PHP: 7.4
Text Domain: wp-migrate-db-anonymization
*/

// Copyright (c) 2018 Delicious Brains. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bootstrap our autoloader. On PHP older than the minimum (7.4, as in
// WPMDB_Anonymization_Compatibility), or if the vendor directory is missing,
// load the compatibility checker on its own so it can report the problem:
// the dependencies use syntax that older PHP versions can't parse.
if ( version_compare( PHP_VERSION, '7.4', '>=' ) && file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/includes/Compatibility.php';
}

/**
 * The main function responsible for returning the one true plugin
 * instance to functions everywhere.
 *
 * @return \WPMDB\Anonymization\Plugin
 */
function wpmdb_anonymize() {
	$version = '0.3.4';

	return \WPMDB\Anonymization\Plugin::get_instance( __FILE__, $version );
}

/**
 * Load the plugin if it is compatible with the site.
 */
function wpmdb_anonymize_init() {
	$plugin_check = new WPMDB_Anonymization_Compatibility( __FILE__ );
	if ( ! $plugin_check->is_compatible() ) {
		// Plugin does not meet requirements, display notice and bail
		$plugin_check->register_notice();

		return;
	}

	// Start it up
	wpmdb_anonymize();
}

// Initialize the plugin
wpmdb_anonymize_init();