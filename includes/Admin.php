<?php

namespace WPMDB\Anonymization;

class Admin {

	protected $plugin_basename;

	/**
	 * Admin constructor.
	 *
	 * @param $plugin_file_path
	 */
	public function __construct( $plugin_file_path ) {
		$this->plugin_basename = plugin_basename( $plugin_file_path );;
	}

	public function register() {
		add_filter( 'site_option_wpmdb_settings', array( $this, 'hook_disable_allow_push' ) );
		add_action( 'wpmdb_notices', array( $this, 'render_admin_notice' ) );
	}

	public function hook_disable_allow_push( $settings ) {
		$settings['allow_push'] = false;

		return $settings;
	}

	public function render_admin_notice() {
		$deactivate_url = wp_nonce_url( admin_url( 'plugins.php?action=deactivate&amp;plugin=' . $this->plugin_basename ), 'deactivate-plugin_' . $this->plugin_basename );
		?>
		<div class="updated warning error">
			<p>
				<?php
				$deactivate_link = sprintf( '<a style="text-decoration:none;" href="%s">%s</a>', $deactivate_url, __( 'deactivate' ) );
				printf( __( '<strong>Anonymization Addon</strong> &mdash; All pushes to this site are currently disabled because you have the Anonymization addon enabled. We also advise caution when pulling databases into this site to ensure no anonymized data gets migrated. You can %s the addon to remove this restriction.' ), $deactivate_link ); ?>
			</p>
		</div>
		<?php
	}
}