<?php
/**
 * Plugin Name: WP AI Chat Lab
 * Description: WordPress AI knowledge and chat foundation with grounded answers, usage controls, lead capture, and inquiry management.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: coowinit
 * Text Domain: wp-ai-chat-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAIC_VERSION', '1.0.0' );
define( 'WPAIC_PLUGIN_FILE', __FILE__ );
define( 'WPAIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAIC_OPTION_SETTINGS', 'wpaic_settings' );
define( 'WPAIC_OPTION_KNOWLEDGE_SOURCES', 'wpaic_enabled_sources' );
define( 'WPAIC_DB_VERSION', '1.3' );
define( 'WPAIC_OPTION_USAGE_SETTINGS', 'wpaic_usage_settings' );
define( 'WPAIC_OPTION_CHAT_UI_SETTINGS', 'wpaic_chat_ui_settings' );
define( 'WPAIC_OPTION_PUBLIC_GUARD_SETTINGS', 'wpaic_public_guard_settings' );
define( 'WPAIC_OPTION_LEAD_COMMERCIAL_KEYWORDS', 'wpaic_lead_commercial_keywords' );
define( 'WPAIC_OPTION_DB_VERSION', 'wpaic_db_version' );
define( 'WPAIC_OPTION_LAST_FULL_SYNC', 'wpaic_last_full_sync' );
define( 'WPAIC_OPTION_LAST_INCREMENTAL_SYNC', 'wpaic_last_incremental_sync' );

require_once WPAIC_PLUGIN_DIR . 'includes/ai/interface-wpaic-ai-provider.php';
require_once WPAIC_PLUGIN_DIR . 'includes/ai/class-wpaic-deepseek-provider.php';
require_once WPAIC_PLUGIN_DIR . 'includes/ai/class-wpaic-ai-manager.php';
require_once WPAIC_PLUGIN_DIR . 'includes/ai/class-wpaic-provider-failure-lab.php';

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

require_once WPAIC_PLUGIN_DIR . 'includes/usage/class-wpaic-usage-context.php';
require_once WPAIC_PLUGIN_DIR . 'includes/usage/class-wpaic-usage-counter-repository.php';
require_once WPAIC_PLUGIN_DIR . 'includes/usage/class-wpaic-usage-guard.php';
require_once WPAIC_PLUGIN_DIR . 'includes/grounding/class-wpaic-grounded-answer-service.php';

require_once WPAIC_PLUGIN_DIR . 'includes/chat/class-wpaic-chat-context.php';
require_once WPAIC_PLUGIN_DIR . 'includes/chat/class-wpaic-public-request-guard.php';
require_once WPAIC_PLUGIN_DIR . 'includes/chat/class-wpaic-chat-response.php';
require_once WPAIC_PLUGIN_DIR . 'includes/chat/class-wpaic-chat-controller.php';
require_once WPAIC_PLUGIN_DIR . 'public/class-wpaic-chat-widget.php';

require_once WPAIC_PLUGIN_DIR . 'includes/inquiry/class-wpaic-lead-trigger-policy.php';
require_once WPAIC_PLUGIN_DIR . 'includes/inquiry/class-wpaic-inquiry-context.php';
require_once WPAIC_PLUGIN_DIR . 'includes/inquiry/class-wpaic-inquiry-rate-guard.php';
require_once WPAIC_PLUGIN_DIR . 'includes/inquiry/class-wpaic-inquiry-repository.php';
require_once WPAIC_PLUGIN_DIR . 'includes/inquiry/class-wpaic-inquiry-service.php';
require_once WPAIC_PLUGIN_DIR . 'includes/inquiry/class-wpaic-inquiry-controller.php';

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

	if ( false === get_option( WPAIC_OPTION_USAGE_SETTINGS, false ) ) {
		add_option( WPAIC_OPTION_USAGE_SETTINGS, array( 'conversation_limit' => 10, 'visitor_daily_limit' => 20, 'site_daily_limit' => 200 ), '', false );
	}

	if ( false === get_option( WPAIC_OPTION_CHAT_UI_SETTINGS, false ) ) {
		add_option( WPAIC_OPTION_CHAT_UI_SETTINGS, array( 'enabled' => 0 ), '', false );
	}

	if ( false === get_option( WPAIC_OPTION_PUBLIC_GUARD_SETTINGS, false ) ) {
		add_option(
			WPAIC_OPTION_PUBLIC_GUARD_SETTINGS,
			array( 'enabled' => 1, 'visitor_per_minute' => 20, 'ip_per_minute' => 0 ),
			'',
			false
		);
	}

	// v0.9.0 Stage 1 Round 1: defaults are written only when the option does
	// not yet exist. An intentionally empty saved list must remain empty.
	if ( false === get_option( WPAIC_OPTION_LEAD_COMMERCIAL_KEYWORDS, false ) ) {
		add_option(
			WPAIC_OPTION_LEAD_COMMERCIAL_KEYWORDS,
			WPAIC_Lead_Trigger_Policy::get_default_keywords(),
			'',
			false
		);
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

	// v0.7.0 Stage 2: Usage Guard is now connected to the real Provider boundary.
	// The same Guard remains independently testable through the Usage Playground.
	$usage_repository = new WPAIC_Usage_Counter_Repository();
	$usage_guard      = new WPAIC_Usage_Guard( $usage_repository );
	$grounded_answer  = new WPAIC_Grounded_Answer_Service( $local_retriever, $grounding_gate, $evidence_builder, $prompt_builder, $manager, $usage_guard );

	// v0.8.0 Stage 3: public request guard protects the REST boundary itself.
	// It is deliberately separate from the exact Provider-call Usage Guard.
	$public_request_guard = new WPAIC_Public_Request_Guard();
	$provider_failure_lab = new WPAIC_Provider_Failure_Lab();

	// v0.9.0 Stage 1: the deterministic Lead Trigger Policy stays independent
	// from persistence. Round 3 lets the verified Public Chat Boundary ask this
	// same Policy whether a safe, minimal Inquiry CTA should be offered.
	$lead_trigger_policy = new WPAIC_Lead_Trigger_Policy();

	// v0.8.0 Stage 1: public Chat remains only a boundary over the verified
	// pipeline. v0.9.0 Round 3 adds only the optional safe Lead offer metadata.
	new WPAIC_Chat_Controller( $grounded_answer, $public_request_guard, $provider_failure_lab, $lead_trigger_policy );

	// v0.9.0 Stage 1 Round 3: the front-end widget remains a thin client.
	// It renders Lead CTA / Inline Inquiry Form but reuses the same Public Chat
	// and Public Inquiry endpoints; it owns no Retrieval / Grounding / AI logic.
	new WPAIC_Chat_Widget();

	// Round 2 Public Inquiry persistence remains the single save boundary.
	$inquiry_repository  = new WPAIC_Inquiry_Repository();
	$inquiry_service     = new WPAIC_Inquiry_Service( $inquiry_repository );
	$inquiry_rate_guard  = new WPAIC_Inquiry_Rate_Guard();
	new WPAIC_Inquiry_Controller( $inquiry_service, $inquiry_rate_guard );

	if ( is_admin() ) {
		new WPAIC_Admin( $manager, $discovery, $extractor, $store_repository, $lifecycle, $batch_sync, $local_retriever, $grounding_gate, $evidence_builder, $prompt_builder, $grounded_answer, $usage_repository, $usage_guard, $public_request_guard, $provider_failure_lab, $lead_trigger_policy, $inquiry_repository, $inquiry_rate_guard );
	}
}
add_action( 'plugins_loaded', 'wpaic_bootstrap' );
