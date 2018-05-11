<?php
spl_autoload_register( function ( $class ) {
	$prefix = 'WPMDB\\Anonymization\\';
	if ( 0 !== strpos( $class, $prefix ) ) {
		return false;
	}

	$class = str_replace( $prefix, '', trim( $class, '\\' ) );

	$filename = str_replace( '\\', DIRECTORY_SEPARATOR, $class ) . '.php';
	$filename = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $filename;
	if ( ! file_exists( $filename ) ) {
		return false;
	}
	include_once $filename;
} );