<?php
/**
 * Plugin Name: WP AI Chat Lab
 * Description: Experimental WordPress AI provider foundation for building a controlled, knowledge-grounded AI chat system.
 * Version: 0.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: coowinit
 * Text Domain: wp-ai-chat-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAIC_VERSION', '0.2.0' );
define( 'WPAIC_PLUGIN_FILE', __FILE__ );
define( 'WPAIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAIC_OPTION_SETTINGS', 'wpaic_settings' );

require_once WPAIC_PLUGIN_DIR . 'includes/ai/interface-wpaic-ai-provider.php';
require_once WPAIC_PLUGIN_DIR . 'includes/ai/class-wpaic-deepseek-provider.php';
require_once WPAIC_PLUGIN_DIR . 'includes/ai/class-wpaic-ai-manager.php';
require_once WPAIC_PLUGIN_DIR . 'admin/class-wpaic-admin.php';

/**
 * Keep the settings option non-autoloaded from the beginning.
 */
function wpaic_activate() {
	if ( false === get_option( WPAIC_OPTION_SETTINGS, false ) ) {
		add_option(
			WPAIC_OPTION_SETTINGS,
			array(
				'provider' => 'deepseek',
				'model'    => WPAIC_DeepSeek_Provider::DEFAULT_MODEL,
				'api_key'  => '',
			),
			'',
			false
		);
	}
}
register_activation_hook( __FILE__, 'wpaic_activate' );

/**
 * Bootstrap the current experiment.
 */
function wpaic_bootstrap() {
	$manager = new WPAIC_AI_Manager();

	if ( is_admin() ) {
		new WPAIC_Admin( $manager );
	}
}
add_action( 'plugins_loaded', 'wpaic_bootstrap' );
