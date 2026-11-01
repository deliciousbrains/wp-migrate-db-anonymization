<?php
/**
 * PHPUnit bootstrap.
 *
 * The test suite runs without a WordPress install. The handful of WordPress
 * functions and classes the plugin touches are replaced by lightweight stubs
 * in tests/stubs/, which any test can rely on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

define( 'WPMDB_ANONYMIZATION_TESTS_DIR', __DIR__ );
define( 'WPMDB_ANONYMIZATION_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'WPMDB_ANONYMIZATION_PLUGIN_FILE', WPMDB_ANONYMIZATION_PLUGIN_DIR . '/wp-migrate-db-anonymization.php' );

require_once WPMDB_ANONYMIZATION_PLUGIN_DIR . '/vendor/autoload.php';

/*
 * The abandoned fzaninotto/faker package triggers PHP 8.2+ deprecations from
 * inside vendor code. Mask E_DEPRECATED only while that package is installed so
 * deprecations in plugin code fail the suite again once it is replaced by
 * fakerphp/faker.
 */
if ( is_dir( WPMDB_ANONYMIZATION_PLUGIN_DIR . '/vendor/fzaninotto/faker' ) ) {
	error_reporting( E_ALL & ~E_DEPRECATED );
}

require_once __DIR__ . '/stubs/wordpress-functions.php';
require_once __DIR__ . '/stubs/class-wpdb.php';
require_once __DIR__ . '/stubs/class-wp-user.php';
