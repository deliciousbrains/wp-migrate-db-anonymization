<?php
/**
 * PHPUnit bootstrap.
 *
 * The test suite runs without a WordPress install. The handful of WordPress
 * functions and classes the plugin touches, and the WP-CLI API used by the
 * migrate-anonymize command, are replaced by lightweight stubs
 * in tests/stubs/, which any test can rely on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

define( 'WPMDB_ANONYMIZATION_TESTS_DIR', __DIR__ );
define( 'WPMDB_ANONYMIZATION_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'WPMDB_ANONYMIZATION_PLUGIN_FILE', WPMDB_ANONYMIZATION_PLUGIN_DIR . '/wp-migrate-db-anonymization.php' );

require_once WPMDB_ANONYMIZATION_PLUGIN_DIR . '/vendor/autoload.php';

require_once __DIR__ . '/stubs/wordpress-functions.php';
require_once __DIR__ . '/stubs/class-wpdb.php';
require_once __DIR__ . '/stubs/class-wp-user.php';
require_once __DIR__ . '/stubs/wp-cli.php';
