<?php

namespace WPMDB\Anonymization;

class Admin {

	/**
	 * Query arg and nonce action used to dismiss the environment notice.
	 */
	const DISMISS_ACTION = 'wpmdb-anonymization-dismiss-notice';

	/**
	 * User meta key storing the environment type the notice was dismissed for.
	 */
	const DISMISSED_META_KEY = 'wpmdb_anonymization_dismissed_environment_notice';

	/**
	 * @var string
	 */
	protected $plugin_basename;

	/**
	 * Admin constructor.
	 *
	 * @param $plugin_file_path
	 */
	public function __construct( $plugin_file_path ) {
		$this->plugin_basename = plugin_basename( $plugin_file_path );
	}

	public function register() {
		add_filter( 'site_option_wpmdb_settings', array( $this, 'hook_disable_allow_push' ) );
		add_action( 'wpmdb_notices', array( $this, 'render_admin_notice' ) );
		add_action( 'wpmdb_notices', array( $this, 'render_environment_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_plugins_screen_environment_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'render_plugins_screen_environment_notice' ) );
		add_action( 'admin_init', array( $this, 'handle_dismiss_environment_notice' ) );
	}

	public function hook_disable_allow_push( $settings ) {
		$settings['allow_push'] = false;

		return $settings;
	}

	public function render_admin_notice() {
		$deactivate_url = wp_nonce_url( admin_url( 'plugins.php?action=deactivate&plugin=' . $this->plugin_basename ), 'deactivate-plugin_' . $this->plugin_basename );
		?>
		<div class="updated warning error">
			<p>
				<?php
				$deactivate_link = sprintf( '<a style="text-decoration:none;" href="%s">%s</a>', esc_url( $deactivate_url ), esc_html__( 'deactivate', 'wp-migrate-db-anonymization' ) );
				/* translators: %s: Deactivate link. */
				printf( __( '<strong>Anonymization Addon</strong> &mdash; All pushes to this site are currently disabled because you have the Anonymization addon enabled. We also advise caution when pulling databases into this site to ensure no anonymized data gets migrated. You can %s the addon to remove this restriction.', 'wp-migrate-db-anonymization' ), $deactivate_link ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Show the environment notice on the Plugins screen.
	 *
	 * The WP Migrate screen is handled by the `wpmdb_notices` action.
	 */
	public function render_plugins_screen_environment_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
			return;
		}

		$this->render_environment_notice();
	}

	/**
	 * Warn that the addon belongs on the live (source) site and that data
	 * leaving this site via WP Migrate will be anonymized.
	 */
	public function render_environment_notice() {
		if ( ! $this->should_show_environment_notice() ) {
			return;
		}

		$environment = $this->get_environment_type();
		$dismiss_url = wp_nonce_url( add_query_arg( self::DISMISS_ACTION, '1' ), self::DISMISS_ACTION );

		if ( in_array( $environment, array( 'local', 'development' ), true ) ) {
			/* translators: %s: Environment type, e.g. "local" or "development". */
			$message = sprintf( __( '<strong>Anonymization Addon</strong> &mdash; This site\'s environment type is <code>%s</code>. The addon is normally only active on the live (source) site, so that user and customer data is anonymized when that site\'s database is exported, pulled or pushed. While it is active here, any data exported, pulled or pushed from this site will be anonymized and pushes to this site are disabled. If this is not your live site, you may want to deactivate the addon.', 'wp-migrate-db-anonymization' ), esc_html( $environment ) );
		} else {
			$message = __( '<strong>Anonymization Addon</strong> &mdash; This addon should be active on the live (source) site. While it is active, user and customer data will be anonymized whenever this site\'s database is exported, pulled or pushed with WP Migrate.', 'wp-migrate-db-anonymization' );
		}
		?>
		<div class="notice notice-warning wpmdb-anonymization-environment-notice">
			<p><?php echo wp_kses( $message, array( 'strong' => array(), 'code' => array() ) ); ?></p>
			<p><a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss this notice', 'wp-migrate-db-anonymization' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Persist dismissal of the environment notice for the current user.
	 *
	 * The dismissal is tied to the environment type, so the notice will show
	 * again if the database is moved to a site with a different environment.
	 */
	public function handle_dismiss_environment_notice() {
		if ( empty( $_GET[ self::DISMISS_ACTION ] ) ) {
			return;
		}

		check_admin_referer( self::DISMISS_ACTION );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		update_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, $this->get_environment_type() );

		wp_safe_redirect( remove_query_arg( array( self::DISMISS_ACTION, '_wpnonce' ) ) );
		exit;
	}

	/**
	 * @return bool
	 */
	protected function should_show_environment_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}

		$dismissed_for = get_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, true );

		return $dismissed_for !== $this->get_environment_type();
	}

	/**
	 * @return string
	 */
	protected function get_environment_type() {
		if ( ! function_exists( 'wp_get_environment_type' ) ) {
			return 'production';
		}

		return wp_get_environment_type();
	}
}
