<?php

/**
 * The plugin compatibility class.
 *
 * This is used to determine if the plugin can be run on the site.
 *
 * This file is loaded before the rest of the plugin and on sites that may not
 * meet the plugin's requirements, so it deliberately avoids newer PHP syntax.
 *
 * @since 0.1
 */
class WPMDB_Anonymization_Compatibility {

	/**
	 * @var string
	 */
	protected $plugin_file_path;

	/**
	 * @var string
	 */
	protected $plugin_basename;

	/**
	 * @var string
	 */
	public $minimum_php_version = '7.4';

	/**
	 * WPMDB_Anonymization_Compatibility constructor.
	 *
	 * @param string $plugin_file_path
	 */
	public function __construct( $plugin_file_path ) {
		$this->plugin_file_path = $plugin_file_path;
		$this->plugin_basename  = plugin_basename( $plugin_file_path );
	}

	/**
	 * Register notice of missing requirements
	 */
	public function register_notice() {
		add_action( 'admin_notices', array( $this, 'display_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'display_notice' ) );
	}

	/**
	 * Is the plugin compatible?
	 *
	 * @return bool
	 */
	public function is_compatible() {
		$missing_requirements = $this->get_missing_requirements();

		return empty( $missing_requirements );
	}

	/**
	 * Get the missing plugin requirements
	 *
	 * @return array
	 */
	protected function get_missing_requirements() {
		$missing_requirements = array();

		if ( version_compare( PHP_VERSION, $this->minimum_php_version, '<' ) ) {
			/* translators: %s: Minimum required PHP version. */
			$missing_requirements[] = sprintf( __( 'PHP version %s+', 'wp-migrate-db-anonymization' ), $this->minimum_php_version );
		}

		if ( ! file_exists( dirname( $this->plugin_file_path ) . '/vendor/autoload.php' ) ) {
			$missing_requirements[] = __( 'its <code>vendor</code> directory, which is missing', 'wp-migrate-db-anonymization' );
		}

		return $missing_requirements;
	}

	/**
	 * Display the admin notice with missing requirements
	 */
	public function display_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$requirements = $this->get_missing_requirements();

		if ( empty( $requirements ) ) {
			return;
		}

		$deactivate_url = wp_nonce_url( admin_url( 'plugins.php?action=deactivate&plugin=' . $this->plugin_basename ), 'deactivate-plugin_' . $this->plugin_basename );
		$requirements   = implode( ', ', $requirements );

		$this->render_notice( $deactivate_url, $requirements );
	}

	/**
	 * Render the notice
	 *
	 * @param string $deactivate_url
	 * @param string $requirements
	 */
	protected function render_notice( $deactivate_url, $requirements ) { ?>
		<div class="notice notice-error <?php echo esc_attr( $this->plugin_basename ); ?>-requirements-notice">
			<p>
				<?php
				$deactivate_link = sprintf( '<a style="text-decoration:none;" href="%s">%s</a>', esc_url( $deactivate_url ), esc_html__( 'deactivate', 'wp-migrate-db-anonymization' ) );
				/* translators: 1: List of missing requirements, 2: Deactivate link. */
				printf( __( 'WP Migrate DB Anonymization plugin disabled as it requires %1$s. You can %2$s the plugin to remove this notice.', 'wp-migrate-db-anonymization' ), $requirements, $deactivate_link ); ?>
			</p>
		</div>
		<?php
	}
}
