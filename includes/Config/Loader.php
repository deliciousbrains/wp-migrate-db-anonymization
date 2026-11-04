<?php

namespace WPMDB\Anonymization\Config;

class Loader {

	/**
	 * @var string
	 */
	protected $config_files_dir;

	/**
	 * Cache of table name => whether it exists, for this request.
	 *
	 * @var array
	 */
	protected $table_exists = array();

	/**
	 * Loader constructor.
	 *
	 * @param $plugin_file_path
	 */
	public function __construct( $plugin_file_path ) {
		$this->config_files_dir = dirname( $plugin_file_path ) . '/config/';
	}

	/**
	 * The config files are loaded the first time the config is used during a
	 * migration, not on every request.
	 *
	 * @param Config $config
	 *
	 * @return Config
	 */
	public function init( Config $config ) {
		$config->init_deferred( array( $this, 'load' ) );

		return $config;
	}

	/**
	 * Load the config files that apply to this site and run them through the
	 * `wpmdb_anonymization_config` filter.
	 *
	 * @param \wpdb|null $wpdb
	 *
	 * @return array
	 */
	public function load( $wpdb = null ) {
		if ( null === $wpdb ) {
			$wpdb = $GLOBALS['wpdb'];
		}

		$data = $this->load_files( $wpdb );

		$filtered = apply_filters( 'wpmdb_anonymization_config', $data );

		// Never lose the bundled rules because a filter callback forgot to return the config.
		return is_array( $filtered ) ? $filtered : $data;
	}

	/**
	 * Load every config file whose requirements are met. See requirements().
	 *
	 * @param \wpdb $wpdb
	 *
	 * @return array
	 */
	protected function load_files( $wpdb ) {
		$files        = $this->config_files();
		$requirements = $this->requirements();

		$config_array = array();
		foreach ( $files as $file ) {
			$name     = basename( $file, '.php' );
			$requires = isset( $requirements[ $name ] ) ? $requirements[ $name ] : array();
			$load     = $this->requirements_met( $requires, $wpdb );

			/**
			 * Whether to load a bundled config file, e.g. to force the WooCommerce
			 * rules on, or to turn a domain off.
			 *
			 * @param bool   $load Whether the file's requirements are met.
			 * @param string $name Config file name without extension, e.g. 'woocommerce'.
			 * @param string $file Full path to the config file.
			 */
			if ( ! apply_filters( 'wpmdb_anonymization_load_config_file', $load, $name, $file ) ) {
				continue;
			}

			$config = $this->include_file( $file );
			if ( ! is_array( $config ) ) {
				continue;
			}

			$config_array = $this->merge( $config_array, $config );
		}

		return $config_array;
	}

	/**
	 * Plugin config files are only loaded if the plugin's data is in the
	 * database, whether or not the plugin is active: any of the 'tables' exist
	 * (unprefixed, for any site on multisite) or the 'callback' returns true.
	 * Config files without requirements are always loaded.
	 *
	 * @return array Config file name => requirements.
	 */
	protected function requirements() {
		return array(
			'woocommerce'                        => array(
				'tables'   => array( 'woocommerce_order_items', 'wc_orders', 'wc_customer_lookup' ),
				// Customer addresses in usermeta are kept when WooCommerce is uninstalled.
				'callback' => function ( $wpdb ) {
					return (bool) $wpdb->get_var( "SELECT 1 FROM {$wpdb->usermeta} WHERE meta_key IN ( 'billing_email', 'billing_first_name', 'shipping_first_name' ) LIMIT 1" );
				},
			),
			'easy-digital-downloads'             => array(
				'tables' => array( 'edd_customers', 'edd_orders' ),
			),
			// As easy-digital-downloads. Its rule constrained on usermeta.meta_value makes
			// the WP-CLI command read every usermeta row, so it mustn't load everywhere.
			'credentials-easy-digital-downloads' => array(
				'tables' => array( 'edd_customers', 'edd_orders' ),
			),
			'gravity-forms'                      => array(
				// Gravity Forms 2.x, or 1.x tables left after upgrading.
				'tables' => array( 'gf_entry', 'gf_form', 'rg_lead' ),
			),
			'redirection'                        => array(
				'tables' => array( 'redirection_404', 'redirection_logs' ),
			),
		);
	}

	/**
	 * Merge a config file's rules into the config. A column can be defined as a
	 * single rule or a list of rules; if two files define the same column, both
	 * are converted to lists and combined. array_merge_recursive() would instead
	 * merge two single rules into one invalid rule.
	 *
	 * @param array $config_array
	 * @param array $config
	 *
	 * @return array
	 */
	protected function merge( array $config_array, array $config ) {
		foreach ( $config as $table => $columns ) {
			if ( ! is_array( $columns ) ) {
				continue;
			}

			foreach ( $columns as $column => $rules ) {
				if ( ! is_array( $rules ) ) {
					continue;
				}

				if ( ! isset( $config_array[ $table ][ $column ] ) ) {
					$config_array[ $table ][ $column ] = $rules;
					continue;
				}

				$config_array[ $table ][ $column ] = array_merge(
					$this->rules_list( $config_array[ $table ][ $column ] ),
					$this->rules_list( $rules )
				);
			}
		}

		return $config_array;
	}

	/**
	 * @param array $rules A single rule or a list of rules.
	 *
	 * @return array List of rules.
	 */
	protected function rules_list( array $rules ) {
		if ( isset( $rules[0] ) && is_array( $rules[0] ) ) {
			return array_values( $rules );
		}

		return array( $rules );
	}

	/**
	 * Include a config file in its own scope so its variables can't clash with ours.
	 *
	 * @return mixed
	 */
	protected function include_file() {
		return include func_get_arg( 0 );
	}

	/**
	 * The rule files to load: the bundled config/*.php files followed by any
	 * added with the `wpmdb_anonymization_config_files` filter.
	 *
	 * @return array Absolute paths.
	 */
	protected function config_files() {
		$bundled = glob( $this->config_files_dir . '*.php' );
		$bundled = is_array( $bundled ) ? $bundled : array();

		/**
		 * Filter the rule files to load, e.g. to add a file of private rules kept
		 * outside the plugin. Each file returns an array in the same format as the
		 * bundled config/*.php files and is merged with them, so a column defined
		 * in several files gets the rules from all of them.
		 *
		 * Requirements are looked up by file name, so an extra file is always
		 * loaded unless the `wpmdb_anonymization_load_config_file` filter says
		 * otherwise (or it shares a name with a bundled file, e.g. woocommerce.php,
		 * and so its requirements). Paths that aren't files are skipped.
		 *
		 * @param array $files Absolute paths of the rule files, bundled files first.
		 */
		$files = apply_filters( 'wpmdb_anonymization_config_files', $bundled );
		if ( ! is_array( $files ) ) {
			return $bundled;
		}

		$valid = array();
		foreach ( $files as $file ) {
			if ( is_string( $file ) && '' !== $file && is_file( $file ) && ! in_array( $file, $valid, true ) ) {
				$valid[] = $file;
			}
		}

		return $valid;
	}

	/**
	 * @param array $requires
	 * @param \wpdb $wpdb
	 *
	 * @return bool
	 */
	protected function requirements_met( $requires, $wpdb ) {
		if ( empty( $requires['tables'] ) && empty( $requires['callback'] ) ) {
			return true;
		}

		// If the database can't be checked, load the rules rather than risk leaking data.
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_col' ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return true;
		}

		if ( ! empty( $requires['tables'] ) ) {
			foreach ( (array) $requires['tables'] as $table ) {
				if ( $this->table_exists( $table, $wpdb ) ) {
					return true;
				}
			}
		}

		if ( ! empty( $requires['callback'] ) && is_callable( $requires['callback'] ) ) {
			return (bool) call_user_func( $requires['callback'], $wpdb );
		}

		return false;
	}

	/**
	 * Check if a table exists for the main site or, on multisite, any site.
	 *
	 * @param string $table Table name without prefix.
	 * @param \wpdb  $wpdb
	 *
	 * @return bool
	 */
	protected function table_exists( $table, $wpdb ) {
		if ( isset( $this->table_exists[ $table ] ) ) {
			return $this->table_exists[ $table ];
		}

		$prefix     = $wpdb->base_prefix;
		$multisite  = function_exists( 'is_multisite' ) && is_multisite();
		$like       = $wpdb->esc_like( $prefix ) . ( $multisite ? '%' : '' ) . $wpdb->esc_like( $table );
		$pattern    = '/^' . preg_quote( $prefix, '/' ) . '(\d+_)?' . preg_quote( $table, '/' ) . '$/';
		$candidates = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

		$exists = false;
		foreach ( $candidates as $candidate ) {
			if ( preg_match( $pattern, $candidate ) ) {
				$exists = true;
				break;
			}
		}

		$this->table_exists[ $table ] = $exists;

		return $exists;
	}
}
