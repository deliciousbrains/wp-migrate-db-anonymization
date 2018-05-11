<?php

namespace WPMDB\Anonymization\Config;

class Loader {

	/**
	 * @var string
	 */
	protected $config_files_dir;

	/**
	 * Loader constructor.
	 *
	 * @param $plugin_file_path
	 */
	public function __construct( $plugin_file_path ) {
		$this->config_files_dir = dirname( $plugin_file_path ) . '/config/';
	}

	/**
	 * @param Config $config
	 *
	 * @return mixed|void|Config
	 */
	public function init( Config $config ) {
		$data = $this->load_files();

		$data = apply_filters( 'wpmdb_anonymization_config', $data );

		$config->init( $data );

		return $config;
	}

	protected function load_files() {
		$files = glob( $this->config_files_dir . '*.php' );

		$config_array = array();
		foreach ( $files as $file ) {
			$config       = include $file;
			$config_array = array_merge_recursive( $config_array, $config );
		}

		return $config_array;
	}
}