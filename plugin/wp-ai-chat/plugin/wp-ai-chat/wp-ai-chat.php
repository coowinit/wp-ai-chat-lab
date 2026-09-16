<?php
/**
 * Plugin Name: WP AI Chat Lab
 * Description: Experimental WordPress AI foundation for controlled, knowledge-grounded chat, including AI providers and WordPress knowledge sources.
 * Version: 0.3.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: coowinit
 * Text Domain: wp-ai-chat-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAIC_VERSION', '0.3.1' );
define( 'WPAIC_PLUGIN_FILE', __FILE__ );
define( 'WPAIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAIC_OPTION_SETTINGS', 'wpaic_settings' );
define( 'WPAIC_OPTION_KNOWLEDGE_SOURCES', 'wpaic_enabled_sources' );

require_once WPAIC_PLUGIN_DIR . 'includes/ai/interface-wpaic-ai-provider.php';
require_once WPAIC_PLUGIN_DIR . 'includes/ai/class-wpaic-deepseek-provider.php';
require_once WPAIC_PLUGIN_DIR . 'includes/ai/class-wpaic-ai-manager.php';

require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-source-discovery.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-content-normalizer.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-knowledge-source.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-manual-knowledge.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-generic-extractor.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-legacy-profile-provider.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-wem-field-registry-provider.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-structured-field-resolver.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-structured-value-normalizer.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-structured-source-enhancer.php';

require_once WPAIC_PLUGIN_DIR . 'admin/class-wpaic-admin.php';

/**
 * Ensure options exist and remain non-autoloaded. This also handles an active
 * plugin being replaced from v0.2.0 without requiring a deactivate/reactivate
 * cycle.
 *
 * @return void
 */
function wpaic_ensure_options() {
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

	if ( false === get_option( WPAIC_OPTION_KNOWLEDGE_SOURCES, false ) ) {
		add_option( WPAIC_OPTION_KNOWLEDGE_SOURCES, array(), '', false );
	}
}

/**
 * Plugin activation.
 *
 * @return void
 */
function wpaic_activate() {
	wpaic_ensure_options();
}
register_activation_hook( __FILE__, 'wpaic_activate' );

/**
 * Bootstrap the current experiment.
 *
 * @return void
 */
function wpaic_bootstrap() {
	wpaic_ensure_options();

	$manager     = new WPAIC_AI_Manager();
	$manual      = new WPAIC_Manual_Knowledge();
	$discovery   = new WPAIC_Source_Discovery();
	$normalizer  = new WPAIC_Content_Normalizer();
	$extractor   = new WPAIC_Generic_Extractor( $normalizer );

	// v0.3.1 structured-source validation: Legacy remains the fallback provider;
	// WEM is evaluated later so a non-empty WEM value can override the same
	// knowledge_key while an empty WEM field naturally falls back to Legacy.
	$legacy_provider      = new WPAIC_Legacy_Profile_Provider();
	$wem_provider         = new WPAIC_WEM_Field_Registry_Provider();
	$structured_resolver  = new WPAIC_Structured_Field_Resolver( array( $legacy_provider, $wem_provider ) );
	$structured_normalizer = new WPAIC_Structured_Value_Normalizer( $normalizer );
	$structured_enhancer  = new WPAIC_Structured_Source_Enhancer( $structured_resolver, $structured_normalizer );

	if ( is_admin() ) {
		new WPAIC_Admin( $manager, $discovery, $extractor );
	}
}
add_action( 'plugins_loaded', 'wpaic_bootstrap' );
