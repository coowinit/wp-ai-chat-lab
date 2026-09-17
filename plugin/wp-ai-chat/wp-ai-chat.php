<?php
/**
 * Plugin Name: WP AI Chat Lab
 * Description: Experimental WordPress AI foundation for controlled, knowledge-grounded chat, including AI providers, WordPress knowledge sources, and a rebuildable Knowledge Store.
 * Version: 0.6.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: coowinit
 * Text Domain: wp-ai-chat-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAIC_VERSION', '0.6.0' );
define( 'WPAIC_PLUGIN_FILE', __FILE__ );
define( 'WPAIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAIC_OPTION_SETTINGS', 'wpaic_settings' );
define( 'WPAIC_OPTION_KNOWLEDGE_SOURCES', 'wpaic_enabled_sources' );
define( 'WPAIC_DB_VERSION', '1.0' );
define( 'WPAIC_OPTION_DB_VERSION', 'wpaic_db_version' );
define( 'WPAIC_OPTION_LAST_FULL_SYNC', 'wpaic_last_full_sync' );
define( 'WPAIC_OPTION_LAST_INCREMENTAL_SYNC', 'wpaic_last_incremental_sync' );

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

require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-db-installer.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-knowledge-store-repository.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-knowledge-lifecycle-manager.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-knowledge-batch-sync.php';
require_once WPAIC_PLUGIN_DIR . 'includes/knowledge/class-wpaic-knowledge-incremental-sync.php';

require_once WPAIC_PLUGIN_DIR . 'includes/retrieval/class-wpaic-retrieval-query-normalizer.php';
require_once WPAIC_PLUGIN_DIR . 'includes/retrieval/class-wpaic-retrieval-candidate-searcher.php';
require_once WPAIC_PLUGIN_DIR . 'includes/retrieval/class-wpaic-retrieval-scorer.php';
require_once WPAIC_PLUGIN_DIR . 'includes/retrieval/class-wpaic-retrieval-strength-evaluator.php';
require_once WPAIC_PLUGIN_DIR . 'includes/retrieval/class-wpaic-local-retriever.php';

require_once WPAIC_PLUGIN_DIR . 'includes/grounding/class-wpaic-grounding-gate.php';
require_once WPAIC_PLUGIN_DIR . 'includes/grounding/class-wpaic-evidence-pack-builder.php';
require_once WPAIC_PLUGIN_DIR . 'includes/grounding/class-wpaic-grounded-prompt-builder.php';
require_once WPAIC_PLUGIN_DIR . 'includes/grounding/class-wpaic-grounded-answer-service.php';

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

	if ( false === get_option( WPAIC_OPTION_LAST_FULL_SYNC, false ) ) {
		add_option( WPAIC_OPTION_LAST_FULL_SYNC, array(), '', false );
	}

	if ( false === get_option( WPAIC_OPTION_LAST_INCREMENTAL_SYNC, false ) ) {
		add_option( WPAIC_OPTION_LAST_INCREMENTAL_SYNC, array(), '', false );
	}
}

/**
 * Plugin activation.
 *
 * @return void
 */
function wpaic_activate() {
	wpaic_ensure_options();
	WPAIC_DB_Installer::install();
}
register_activation_hook( __FILE__, 'wpaic_activate' );

/**
 * Bootstrap the current experiment.
 *
 * @return void
 */
function wpaic_bootstrap() {
	wpaic_ensure_options();
	WPAIC_DB_Installer::maybe_upgrade();

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

	$store_repository = new WPAIC_Knowledge_Store_Repository();
	$lifecycle        = new WPAIC_Knowledge_Lifecycle_Manager( $extractor, $discovery, $store_repository );
	$batch_sync       = new WPAIC_Knowledge_Batch_Sync( $discovery, $store_repository, $lifecycle );
	$incremental_sync = new WPAIC_Knowledge_Incremental_Sync( $discovery, $store_repository, $lifecycle );

	// v0.5.0 Local Retrieval reads only from the persisted Knowledge Store.
	$retrieval_normalizer = new WPAIC_Retrieval_Query_Normalizer();
	$candidate_searcher   = new WPAIC_Retrieval_Candidate_Searcher( $store_repository );
	$retrieval_scorer     = new WPAIC_Retrieval_Scorer();
	$strength_evaluator   = new WPAIC_Retrieval_Strength_Evaluator();
	$local_retriever      = new WPAIC_Local_Retriever( $retrieval_normalizer, $candidate_searcher, $retrieval_scorer, $strength_evaluator );

	// v0.6.0 Stage 1: application-layer Grounding Gate. It consumes only the
	// Retrieval Result contract and never calls the AI Manager or Provider.
	$grounding_gate   = new WPAIC_Grounding_Gate();
	$evidence_builder = new WPAIC_Evidence_Pack_Builder( $store_repository );
	$prompt_builder   = new WPAIC_Grounded_Prompt_Builder();
	$grounded_answer  = new WPAIC_Grounded_Answer_Service( $local_retriever, $grounding_gate, $evidence_builder, $prompt_builder, $manager );

	if ( is_admin() ) {
		new WPAIC_Admin( $manager, $discovery, $extractor, $store_repository, $lifecycle, $batch_sync, $local_retriever, $grounding_gate, $evidence_builder, $prompt_builder, $grounded_answer );
	}
}
add_action( 'plugins_loaded', 'wpaic_bootstrap' );
