<?php
/*
Plugin Name: WP Migrate Anonymization
Plugin URI: https://deliciousbrains.com/wp-migrate-db-pro/
Description: An extension to WP Migrate and WP Migrate Pro that anonymizes user data.
Author: Delicious Brains
Version: 0.3.4
Author URI: https://deliciousbrains.com
Network: True
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

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bootstrap autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * The main function responsible for returning the one true Mergebot
 * instance to functions everywhere.
 */
function wpmdb_anonymize() {
	$version = '0.3.4';
	return WPMDB\Anonymization\Plugin::get_instance( __FILE__, $version );
}

// Initialize the plugin.
wpmdb_anonymize();
