<?php

namespace WPMDB\Anonymization;

use Faker\Factory;
use WPMDB\Anonymization\Config\Config;
use WPMDB\Anonymization\Config\Loader;

class Plugin {

	/**
	 * @var Plugin
	 */
	protected static $instance;

	/**
	 * @var string
	 */
	protected $file_path;

	/**
	 * @var string
	 */
	protected $slug;

	/**
	 * Make this class a singleton
	 *
	 * Use this instead of __construct()
	 *
	 * @param string $plugin_file_path
	 * @param string $plugin_version
	 *
	 * @return Plugin
	 */
	public static function get_instance( $plugin_file_path, $plugin_version ) {
		if ( ! isset( static::$instance ) && ! ( self::$instance instanceof Plugin ) ) {
			static::$instance = new Plugin();
			// Initialize the class
			static::$instance->init( $plugin_file_path, $plugin_version );
		}

		return static::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * @param string $plugin_file_path
	 * @param string $plugin_version
	 */
	protected function init( $plugin_file_path, $plugin_version ) {
		$this->file_path = $plugin_file_path;
		$this->slug      = basename( dirname( $plugin_file_path ) );

		$GLOBALS['wpmdb_meta'][ $this->slug ]['version'] = $plugin_version;
		load_plugin_textdomain( $this->slug, false, dirname( plugin_basename( $this->file_path ) ) . '/languages/' );

		( new Admin( $this->file_path ) )->register();

		add_action( 'plugins_loaded', array( $this, 'bootstrap' ), 21 );
	}

	/**
	 * Bootstrap the functionality of the plugin on plugins_loaded so WPMDB already loaded.
	 */
	public function bootstrap() {
		if ( ! class_exists( 'WPMDB_PHP_Checker') && ! class_exists( 'WPMDB_Base' ) && ! class_exists( 'WPMDB_Command' ) ) {
			return;
		}

		global $wpdb;
		$wpmdb = $this->get_wpmdb_instance();

		if ( ! $wpmdb ) {
			return;
		}

		$loader = new Loader( $this->file_path );
		$config = $loader->init( new Config( $wpdb, $wpmdb ) );

		$faker = Factory::create();

		$migration = new Migration( $config, $faker );
		$migration->register();
	}

	/**
	 * Get the global mdb instance
	 *
	 * @return bool|mixed|\WPMDB
	 */
	protected function get_wpmdb_instance() {
		if ( isset( $GLOBALS['wpmdbpro'] ) ) {
			return $GLOBALS['wpmdbpro'];
		}

		if ( isset( $GLOBALS['wpmdb'] ) ) {
			return $GLOBALS['wpmdb'];
		}

		if ( function_exists( 'wp_migrate_db' ) ) {
			return wp_migrate_db();
		}

		return false;
	}

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * class via the `new` operator from outside of this class.
	 */
	protected function __construct() {
	}

	/**
	 * As this class is a singleton it should not be clone-able
	 */
	protected function __clone() {
	}

	/**
	 * As this class is a singleton it should not be able to be unserialized
	 */
	protected function __wakeup() {
	}
}
