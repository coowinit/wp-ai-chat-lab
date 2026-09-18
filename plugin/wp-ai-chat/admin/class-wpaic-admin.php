<?php
/**
 * WordPress admin UI for AI provider tests, Knowledge Sources, and Knowledge Store diagnostics.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Admin {

	/** @var WPAIC_AI_Manager */
	protected $manager;

	/** @var WPAIC_Source_Discovery */
	protected $discovery;

	/** @var WPAIC_Generic_Extractor */
	protected $extractor;

	/** @var WPAIC_Knowledge_Store_Repository */
	protected $store_repository;

	/** @var WPAIC_Knowledge_Lifecycle_Manager */
	protected $lifecycle;

	/** @var WPAIC_Knowledge_Batch_Sync */
	protected $batch_sync;

	/** @var WPAIC_Local_Retriever */
	protected $local_retriever;

	/** @var WPAIC_Grounding_Gate */
	protected $grounding_gate;

	/** @var WPAIC_Evidence_Pack_Builder */
	protected $evidence_builder;

	/** @var WPAIC_Grounded_Prompt_Builder */
	protected $prompt_builder;

	/** @var WPAIC_Grounded_Answer_Service */
	protected $grounded_answer;

	/** @var WPAIC_Usage_Counter_Repository */
	protected $usage_repository;

	/** @var WPAIC_Usage_Guard */
	protected $usage_guard;

	/** @var string */
	protected $ai_page_hook = '';

	/** @var string */
	protected $knowledge_page_hook = '';

	/** @var string */
	protected $store_page_hook = '';

	/** @var string */
	protected $retrieval_page_hook = '';

	/** @var string */
	protected $grounded_page_hook = '';

	/** @var string */
	protected $usage_page_hook = '';

	/** @var string */
	protected $chat_boundary_page_hook = '';

	/**
	 * @param WPAIC_AI_Manager                    $manager          AI manager.
	 * @param WPAIC_Source_Discovery              $discovery        Source discovery.
	 * @param WPAIC_Generic_Extractor             $extractor        Generic source extractor.
	 * @param WPAIC_Knowledge_Store_Repository    $store_repository Knowledge Store repository.
	 * @param WPAIC_Knowledge_Lifecycle_Manager   $lifecycle        Lifecycle orchestrator.
	 * @param WPAIC_Knowledge_Batch_Sync          $batch_sync       Batch full-sync coordinator.
	 * @param WPAIC_Local_Retriever                $local_retriever  Local retrieval coordinator.
	 * @param WPAIC_Grounding_Gate                 $grounding_gate   Application-layer grounding gate.
	 * @param WPAIC_Evidence_Pack_Builder          $evidence_builder Evidence Pack builder.
	 * @param WPAIC_Grounded_Prompt_Builder        $prompt_builder   Grounded Prompt builder.
	 * @param WPAIC_Grounded_Answer_Service        $grounded_answer  Grounded Answer orchestrator.
	 * @param WPAIC_Usage_Counter_Repository       $usage_repository Usage counter repository.
	 * @param WPAIC_Usage_Guard                    $usage_guard      Usage policy guard.
	 */
	public function __construct( WPAIC_AI_Manager $manager, WPAIC_Source_Discovery $discovery, WPAIC_Generic_Extractor $extractor, WPAIC_Knowledge_Store_Repository $store_repository, WPAIC_Knowledge_Lifecycle_Manager $lifecycle, WPAIC_Knowledge_Batch_Sync $batch_sync, WPAIC_Local_Retriever $local_retriever, WPAIC_Grounding_Gate $grounding_gate, WPAIC_Evidence_Pack_Builder $evidence_builder, WPAIC_Grounded_Prompt_Builder $prompt_builder, WPAIC_Grounded_Answer_Service $grounded_answer, WPAIC_Usage_Counter_Repository $usage_repository, WPAIC_Usage_Guard $usage_guard ) {
		$this->manager          = $manager;
		$this->discovery        = $discovery;
		$this->extractor        = $extractor;
		$this->store_repository = $store_repository;
		$this->lifecycle        = $lifecycle;
		$this->batch_sync       = $batch_sync;
		$this->local_retriever  = $local_retriever;
		$this->grounding_gate   = $grounding_gate;
		$this->evidence_builder = $evidence_builder;
		$this->prompt_builder   = $prompt_builder;
		$this->grounded_answer  = $grounded_answer;
		$this->usage_repository = $usage_repository;
		$this->usage_guard      = $usage_guard;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wpaic_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_wpaic_test_chat', array( $this, 'ajax_test_chat' ) );
		add_action( 'wp_ajax_wpaic_preview_source', array( $this, 'ajax_preview_source' ) );
		add_action( 'wp_ajax_wpaic_sync_store_source', array( $this, 'ajax_sync_store_source' ) );
		add_action( 'wp_ajax_wpaic_start_full_sync', array( $this, 'ajax_start_full_sync' ) );
		add_action( 'wp_ajax_wpaic_run_full_sync_batch', array( $this, 'ajax_run_full_sync_batch' ) );
		add_action( 'wp_ajax_wpaic_retrieval_search', array( $this, 'ajax_retrieval_search' ) );
		add_action( 'wp_ajax_wpaic_grounding_gate_test', array( $this, 'ajax_grounding_gate_test' ) );
		add_action( 'wp_ajax_wpaic_usage_guard_check', array( $this, 'ajax_usage_guard_check' ) );
		add_action( 'wp_ajax_wpaic_usage_guard_simulate', array( $this, 'ajax_usage_guard_simulate' ) );
		add_action( 'wp_ajax_wpaic_usage_guard_reset', array( $this, 'ajax_usage_guard_reset' ) );
	}

	/**
	 * @return void
	 */
	public function register_menu() {
		$this->ai_page_hook = add_menu_page(
			'WP AI Chat Lab',
			'WP AI Chat Lab',
			'manage_options',
			'wp-ai-chat-lab',
			array( $this, 'render_ai_page' ),
			'dashicons-format-chat',
			81
		);

		// Replace the automatically generated first submenu label with a clearer
		// description while keeping the top-level page URL unchanged.
		add_submenu_page(
			'wp-ai-chat-lab',
			'AI 设置',
			'AI 设置',
			'manage_options',
			'wp-ai-chat-lab',
			array( $this, 'render_ai_page' )
		);

		$this->knowledge_page_hook = add_submenu_page(
			'wp-ai-chat-lab',
			'知识来源',
			'知识来源',
			'manage_options',
			'wp-ai-chat-lab-knowledge',
			array( $this, 'render_knowledge_page' )
		);

		$this->store_page_hook = add_submenu_page(
			'wp-ai-chat-lab',
			'知识存储',
			'知识存储',
			'manage_options',
			'wp-ai-chat-lab-store',
			array( $this, 'render_store_page' )
		);

		$this->retrieval_page_hook = add_submenu_page(
			'wp-ai-chat-lab',
			'本地检索',
			'本地检索',
			'manage_options',
			'wp-ai-chat-lab-retrieval',
			array( $this, 'render_retrieval_page' )
		);

		$this->grounded_page_hook = add_submenu_page(
			'wp-ai-chat-lab',
			'Grounded AI',
			'Grounded AI',
			'manage_options',
			'wp-ai-chat-lab-grounded',
			array( $this, 'render_grounded_page' )
		);

		$this->usage_page_hook = add_submenu_page(
			'wp-ai-chat-lab',
			'Usage Guard',
			'Usage Guard',
			'manage_options',
			'wp-ai-chat-lab-usage',
			array( $this, 'render_usage_page' )
		);


		$this->chat_boundary_page_hook = add_submenu_page(
			'wp-ai-chat-lab',
			'Chat Boundary',
			'Chat Boundary',
			'manage_options',
			'wp-ai-chat-lab-chat-boundary',
			array( $this, 'render_chat_boundary_page' )
		);
	}

	/**
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'wpaic_settings_group',
			WPAIC_OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'provider' => 'deepseek',
					'model'    => WPAIC_DeepSeek_Provider::DEFAULT_MODEL,
					'api_key'  => '',
				),
			)
		);



		register_setting(
			'wpaic_usage_settings_group',
			WPAIC_OPTION_USAGE_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_usage_settings' ),
				'default'           => array(
					'conversation_limit'   => 10,
					'visitor_daily_limit'  => 20,
					'site_daily_limit'     => 200,
				),
			)
		);

		register_setting(
			'wpaic_knowledge_settings_group',
			WPAIC_OPTION_KNOWLEDGE_SOURCES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_enabled_sources' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Preserve an existing stored key when the password field is intentionally
	 * left blank. This avoids rendering the real secret back into HTML.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string,string>
	 */
	public function sanitize_settings( $input ) {
		$current = get_option( WPAIC_OPTION_SETTINGS, array() );
		$current = is_array( $current ) ? $current : array();
		$input   = is_array( $input ) ? $input : array();

		$provider = new WPAIC_DeepSeek_Provider();
		$models   = $provider->get_models();
		$model    = isset( $input['model'] ) && is_string( $input['model'] ) ? sanitize_key( $input['model'] ) : WPAIC_DeepSeek_Provider::DEFAULT_MODEL;

		if ( ! isset( $models[ $model ] ) ) {
			$model = WPAIC_DeepSeek_Provider::DEFAULT_MODEL;
		}

		$existing_key  = isset( $current['api_key'] ) && is_string( $current['api_key'] ) ? trim( $current['api_key'] ) : '';
		$submitted_key = isset( $input['api_key'] ) && is_string( $input['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $input['api_key'] ) ) ) : '';
		$clear_key     = ! empty( $input['clear_api_key'] );

		if ( defined( 'WPAIC_DEEPSEEK_API_KEY' ) ) {
			$stored_key = $existing_key;
		} elseif ( $clear_key ) {
			$stored_key = '';
		} elseif ( '' !== $submitted_key ) {
			$stored_key = $submitted_key;
		} else {
			$stored_key = $existing_key;
		}

		return array(
			'provider' => 'deepseek',
			'model'    => $model,
			'api_key'  => $stored_key,
		);
	}

	/**
	 * Sanitize Usage Guard limits. Zero disables that scope.
	 *
	 * @param mixed $input Submitted limits.
	 * @return array<string,int>
	 */
	public function sanitize_usage_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'conversation_limit'  => isset( $input['conversation_limit'] ) ? min( 100000, absint( $input['conversation_limit'] ) ) : 10,
			'visitor_daily_limit' => isset( $input['visitor_daily_limit'] ) ? min( 100000, absint( $input['visitor_daily_limit'] ) ) : 20,
			'site_daily_limit'    => isset( $input['site_daily_limit'] ) ? min( 1000000, absint( $input['site_daily_limit'] ) ) : 200,
		);
	}

	/**
	 * Only persist discoverable post types. An empty list is valid and means
	 * the administrator has intentionally disabled every generic source.
	 *
	 * @param mixed $input Submitted post type names.
	 * @return array<int,string>
	 */
	public function sanitize_enabled_sources( $input ) {
		$input     = is_array( $input ) ? $input : array();
		$available = $this->discovery->get_post_types();
		$clean     = array();

		foreach ( $input as $post_type ) {
			$post_type = sanitize_key( $post_type );
			if ( '' !== $post_type && isset( $available[ $post_type ] ) ) {
				$clean[] = $post_type;
			}
		}

		$clean = array_values( array_unique( $clean ) );
		sort( $clean );

		return $clean;
	}

	/**
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( $this->ai_page_hook, $this->knowledge_page_hook, $this->store_page_hook, $this->retrieval_page_hook, $this->grounded_page_hook, $this->usage_page_hook, $this->chat_boundary_page_hook ), true ) ) {
			return;
		}

		$css_file = WPAIC_PLUGIN_DIR . 'admin/assets/admin.css';
		$js_file  = WPAIC_PLUGIN_DIR . 'admin/assets/admin.js';

		// Stage builds can change admin assets without changing the semantic
		// plugin version. Include a content hash in the asset version so an
		// upgraded build cannot accidentally reuse an older cached admin.js.
		$css_hash    = is_readable( $css_file ) ? md5_file( $css_file ) : false;
		$js_hash     = is_readable( $js_file ) ? md5_file( $js_file ) : false;
		$css_version = $css_hash ? WPAIC_VERSION . '.' . substr( $css_hash, 0, 8 ) : WPAIC_VERSION;
		$js_version  = $js_hash ? WPAIC_VERSION . '.' . substr( $js_hash, 0, 8 ) : WPAIC_VERSION;

		wp_enqueue_style(
			'wpaic-admin',
			WPAIC_PLUGIN_URL . 'admin/assets/admin.css',
			array(),
			$css_version
		);

		wp_enqueue_script(
			'wpaic-admin',
			WPAIC_PLUGIN_URL . 'admin/assets/admin.js',
			array(),
			$js_version,
			true
		);

		wp_localize_script(
			'wpaic-admin',
			'WPAICAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'chatEndpoint'   => WPAIC_Chat_Controller::get_endpoint_url(),
				'nonce'          => wp_create_nonce( 'wpaic_ai_test' ),
				'knowledgeNonce' => wp_create_nonce( 'wpaic_knowledge_preview' ),
				'storeNonce'     => wp_create_nonce( 'wpaic_store_sync' ),
				'retrievalNonce' => wp_create_nonce( 'wpaic_retrieval_search' ),
				'groundingNonce' => wp_create_nonce( 'wpaic_grounding_gate_test' ),
				'usageNonce'     => wp_create_nonce( 'wpaic_usage_guard_test' ),
				'i18n'           => array(
					'working' => '请求中…',
					'error'   => '请求失败，请重试。',
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public function render_ai_page() {
		$this->guard_admin_page();

		$provider = $this->manager->get_current_provider();
		if ( is_wp_error( $provider ) ) {
			wp_die( esc_html( $provider->get_error_message() ) );
		}

		$settings = get_option( WPAIC_OPTION_SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();

		$settings_updated = isset( $_GET['settings-updated'] )
			? sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) )
			: '';

		if ( 'true' === $settings_updated ) {
			add_settings_error(
				'wpaic_settings_messages',
				'wpaic_settings_saved',
				'AI 设置已保存。',
				'updated'
			);
		}

		include WPAIC_PLUGIN_DIR . 'admin/views/page-ai-settings.php';
	}

	/**
	 * @return void
	 */
	public function render_knowledge_page() {
		$this->guard_admin_page();

		$source_types     = $this->discovery->get_post_types();
		$enabled_sources  = $this->get_enabled_sources();
		$source_examples  = array();

		foreach ( $source_types as $post_type => $object ) {
			$ids = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);

			if ( ! empty( $ids ) ) {
				$source_examples[ $post_type ] = array(
					'id'    => (int) $ids[0],
					'title' => get_the_title( $ids[0] ),
				);
			}
		}

		$manual_count = wp_count_posts( WPAIC_Manual_Knowledge::POST_TYPE );
		$manual_count = $manual_count && isset( $manual_count->publish ) ? (int) $manual_count->publish : 0;
		$manual_ids   = get_posts(
			array(
				'post_type'      => WPAIC_Manual_Knowledge::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		$manual_example = ! empty( $manual_ids ) ? array(
			'id'    => (int) $manual_ids[0],
			'title' => get_the_title( $manual_ids[0] ),
		) : array();

		$settings_updated = isset( $_GET['settings-updated'] )
			? sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) )
			: '';

		if ( 'true' === $settings_updated ) {
			add_settings_error(
				'wpaic_knowledge_messages',
				'wpaic_knowledge_saved',
				'知识来源设置已保存。',
				'updated'
			);
		}

		include WPAIC_PLUGIN_DIR . 'admin/views/page-knowledge-sources.php';
	}

	/**
	 * Render the Stage 2 Knowledge Store lifecycle diagnostics page.
	 *
	 * @return void
	 */
	public function render_store_page() {
		$this->guard_admin_page();

		$store_summary = array(
			'active'   => $this->store_repository->count_by_status( 'active' ),
			'inactive' => $this->store_repository->count_by_status( 'inactive' ),
			'total'    => $this->store_repository->count_by_status(),
		);

		$store_per_page    = 20;
		$store_total_pages = max( 1, (int) ceil( $store_summary['total'] / $store_per_page ) );
		$store_page        = isset( $_GET['store_paged'] ) ? max( 1, absint( wp_unslash( $_GET['store_paged'] ) ) ) : 1;
		$store_page        = min( $store_page, $store_total_pages );
		$store_offset      = ( $store_page - 1 ) * $store_per_page;
		$store_rows        = $this->store_repository->list_rows( $store_per_page, $store_offset );

		$last_full_sync = get_option( WPAIC_OPTION_LAST_FULL_SYNC, array() );
		$last_full_sync = is_array( $last_full_sync ) ? $last_full_sync : array();

		$last_incremental_sync = get_option( WPAIC_OPTION_LAST_INCREMENTAL_SYNC, array() );
		$last_incremental_sync = is_array( $last_incremental_sync ) ? $last_incremental_sync : array();

		include WPAIC_PLUGIN_DIR . 'admin/views/page-knowledge-store.php';
	}

	/**
	 * Render the v0.5.0 Stage 1 Local Retrieval Playground.
	 *
	 * @return void
	 */
	public function render_retrieval_page() {
		$this->guard_admin_page();

		$active_count = $this->store_repository->count_by_status( 'active' );
		include WPAIC_PLUGIN_DIR . 'admin/views/page-local-retrieval.php';
	}

	/**
	 * Render the v0.6.0 Stage 1 Grounding Gate Playground.
	 *
	 * @return void
	 */
	public function render_grounded_page() {
		$this->guard_admin_page();

		$active_count = $this->store_repository->count_by_status( 'active' );
		$usage_limits = $this->usage_guard->get_limits();
		include WPAIC_PLUGIN_DIR . 'admin/views/page-grounded-ai.php';
	}

	/** Render v0.7.0 Stage 1 Usage Guard Playground. @return void */
	public function render_usage_page() {
		$this->guard_admin_page();
		$usage_settings = get_option( WPAIC_OPTION_USAGE_SETTINGS, array() );
		$usage_settings = is_array( $usage_settings ) ? $usage_settings : array();
		$db_version = (string) get_option( WPAIC_OPTION_DB_VERSION, '' );
		$usage_table = WPAIC_DB_Installer::get_usage_table_name();
		$wp_timezone = wp_timezone_string();
		$wp_local_day = current_time( 'Y-m-d' );
		$wp_local_time = current_time( 'mysql' );
		include WPAIC_PLUGIN_DIR . 'admin/views/page-usage-guard.php';
	}


	/** Render v0.8.0 Stage 1 Public Chat Boundary Playground. @return void */
	public function render_chat_boundary_page() {
		$this->guard_admin_page();
		$chat_endpoint = WPAIC_Chat_Controller::get_endpoint_url();
		$usage_limits  = $this->usage_guard->get_limits();
		$usage_status  = $this->usage_repository->get_operational_status();
		$wp_timezone   = wp_timezone_string();
		include WPAIC_PLUGIN_DIR . 'admin/views/page-chat-boundary.php';
	}

	/**
	 * @return void
	 */
	public function ajax_test_connection() {
		$this->guard_ai_ajax_request();

		$result = $this->manager->test_connection();
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		wp_send_json_success( $this->format_result_for_admin( $result ) );
	}

	/**
	 * @return void
	 */
	public function ajax_test_chat() {
		$this->guard_ai_ajax_request();

		$question = isset( $_POST['question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['question'] ) ) : '';
		$question = trim( $question );

		if ( '' === $question ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_test_empty_question',
					'message' => '请输入测试问题。',
				),
				400
			);
		}

		if ( $this->string_length( $question ) > 1000 ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_test_question_too_long',
					'message' => '测试问题最多 1000 个字符，请缩短后重试。',
				),
				400
			);
		}

		$result = $this->manager->chat(
			array(
				array(
					'role'    => 'system',
					'content' => 'This is a minimal WordPress AI provider test. Answer the user directly and concisely.',
				),
				array(
					'role'    => 'user',
					'content' => $question,
				),
			),
			array(
				'max_tokens'  => 256,
				'temperature' => 0.2,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		wp_send_json_success( $this->format_result_for_admin( $result ) );
	}

	/**
	 * Preview one source on demand. This does not call DeepSeek and does not
	 * persist a Knowledge Source record.
	 *
	 * @return void
	 */
	public function ajax_preview_source() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_forbidden',
					'message' => '你没有执行此操作的权限。',
				),
				403
			);
		}

		check_ajax_referer( 'wpaic_knowledge_preview', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_preview_missing_post_id',
					'message' => '请输入有效的 WordPress 内容 ID。',
				),
				400
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_preview_not_found',
					'message' => '未找到对应内容。',
				),
				404
			);
		}

		$is_manual = WPAIC_Manual_Knowledge::POST_TYPE === $post->post_type;
		$enabled   = $this->get_enabled_sources();

		if ( ! $is_manual && ! in_array( $post->post_type, $enabled, true ) ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_preview_source_disabled',
					'message' => '该内容类型尚未启用为 AI 知识来源，请先在上方启用并保存。',
				),
				400
			);
		}

		if ( ! $is_manual && ! $this->discovery->is_discoverable( $post->post_type ) ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_preview_source_not_discoverable',
					'message' => '该内容类型不属于可用的 Generic Knowledge Source。',
				),
				400
			);
		}

		$result = $this->extractor->extract( $post_id, true );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		$result['eligible'] = 'publish' === $result['status'];
		wp_send_json_success( $result );
	}

	/**
	 * Run one Stage 2 Knowledge Store lifecycle sync.
	 *
	 * @return void
	 */
	public function ajax_sync_store_source() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_forbidden',
					'message' => '你没有执行此操作的权限。',
				),
				403
			);
		}

		check_ajax_referer( 'wpaic_store_sync', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$result  = $this->lifecycle->sync_post( $post_id );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Start or resume a visible Stage 3 Full Sync.
	 *
	 * @return void
	 */
	public function ajax_start_full_sync() {
		$this->guard_store_ajax_request();

		$result = $this->batch_sync->start();
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Process one Full Sync AJAX batch.
	 *
	 * @return void
	 */
	public function ajax_run_full_sync_batch() {
		$this->guard_store_ajax_request();

		$token  = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$result = $this->batch_sync->run_batch( $token );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Run one read-only Local Retrieval request.
	 *
	 * @return void
	 */
	public function ajax_retrieval_search() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_forbidden',
					'message' => '你没有执行此操作的权限。',
				),
				403
			);
		}

		check_ajax_referer( 'wpaic_retrieval_search', 'nonce' );

		$question = isset( $_POST['question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['question'] ) ) : '';
		$question = trim( $question );
		if ( '' === $question ) {
			wp_send_json_error( array( 'code' => 'wpaic_retrieval_empty_query', 'message' => '请输入要检索的问题。' ), 400 );
		}

		if ( $this->string_length( $question ) > 1000 ) {
			wp_send_json_error( array( 'code' => 'wpaic_retrieval_query_too_long', 'message' => '检索问题最多 1000 个字符，请缩短后重试。' ), 400 );
		}

		$candidate_limit = isset( $_POST['candidate_limit'] ) ? absint( $_POST['candidate_limit'] ) : 100;
		$top_k           = isset( $_POST['top_k'] ) ? absint( $_POST['top_k'] ) : 5;
		$result = $this->local_retriever->retrieve(
			$question,
			array(
				'candidate_limit' => $candidate_limit,
				'top_k'           => $top_k,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		wp_send_json_success( $result );
	}


	/**
	 * Run the v0.6.0 Stage 3 single-turn Grounded Answer pipeline.
	 *
	 * Denied Gate paths return deterministic application output and never call
	 * the Provider. Only allow_answer is permitted to cross the AI boundary.
	 *
	 * @return void
	 */
	public function ajax_grounding_gate_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_forbidden',
					'message' => '你没有执行此操作的权限。',
				),
				403
			);
		}

		check_ajax_referer( 'wpaic_grounding_gate_test', 'nonce' );

		$question = isset( $_POST['question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['question'] ) ) : '';
		$question = trim( $question );
		if ( '' === $question ) {
			wp_send_json_error( array( 'code' => 'wpaic_grounding_empty_query', 'message' => '请输入要测试的问题。' ), 400 );
		}

		if ( $this->string_length( $question ) > 1000 ) {
			wp_send_json_error( array( 'code' => 'wpaic_grounding_query_too_long', 'message' => '测试问题最多 1000 个字符，请缩短后重试。' ), 400 );
		}

		$result = $this->grounded_answer->answer(
			$question,
			array(
				'candidate_limit' => isset( $_POST['candidate_limit'] ) ? absint( $_POST['candidate_limit'] ) : 100,
				'top_k'           => isset( $_POST['top_k'] ) ? absint( $_POST['top_k'] ) : 5,
				'usage_context'   => array(
					'conversation_key' => isset( $_POST['conversation_key'] ) ? wp_unslash( $_POST['conversation_key'] ) : '',
					'visitor_key'      => isset( $_POST['visitor_key'] ) ? wp_unslash( $_POST['visitor_key'] ) : '',
					'site_key'         => isset( $_POST['site_key'] ) ? wp_unslash( $_POST['site_key'] ) : 'site',
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		wp_send_json_success( $result );
	}

	/** Run a read-only Usage Guard check. @return void */
	public function ajax_usage_guard_check() {
		$this->guard_usage_ajax_request();
		$context = $this->usage_context_from_request();
		$result  = $this->usage_guard->evaluate( $context );
		$result['operational'] = $this->usage_operational_context();
		wp_send_json_success( $result );
	}

	/** Simulate one Provider Call reservation without calling AI. @return void */
	public function ajax_usage_guard_simulate() {
		$this->guard_usage_ajax_request();
		$context = $this->usage_context_from_request();
		$result  = $this->usage_guard->reserve( $context );
		if ( is_wp_error( $result ) ) { $this->send_error( $result ); }
		$result['simulated_provider_call'] = ! empty( $result['reserved'] );
		$result['ai_called'] = false;
		$result['token_usage'] = 0;
		$result['operational'] = $this->usage_operational_context();
		wp_send_json_success( $result );
	}

	/** Reset only selected rows for the exact Stage 3 lab context. @return void */
	public function ajax_usage_guard_reset() {
		$this->guard_usage_ajax_request();
		$context = $this->usage_context_from_request();
		$scopes  = array();
		foreach ( array( 'conversation', 'visitor', 'site' ) as $scope ) {
			$field = 'reset_' . $scope;
			if ( isset( $_POST[ $field ] ) && '1' === sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) ) {
				$scopes[] = $scope;
			}
		}

		$result = $this->usage_repository->reset_context_counters( $context, $scopes );
		if ( is_wp_error( $result ) ) { $this->send_error( $result ); }
		$result['decision']    = 'reset';
		$result['reason_code'] = 'lab_counters_reset';
		$result['scopes']      = $this->usage_guard->get_scope_states( $context );
		$result['operational'] = $this->usage_operational_context();
		wp_send_json_success( $result );
	}

	/** @return array<string,mixed> */
	protected function usage_operational_context() {
		return array(
			'wordpress_timezone'  => wp_timezone_string(),
			'wordpress_local_day' => current_time( 'Y-m-d' ),
			'wordpress_local_time'=> current_time( 'mysql' ),
			'scope_precedence'    => array( 'conversation', 'visitor', 'site' ),
		);
	}

	/** @return WPAIC_Usage_Context */
	protected function usage_context_from_request() {
		return new WPAIC_Usage_Context( array(
			'conversation_key' => isset( $_POST['conversation_key'] ) ? wp_unslash( $_POST['conversation_key'] ) : '',
			'visitor_key'      => isset( $_POST['visitor_key'] ) ? wp_unslash( $_POST['visitor_key'] ) : '',
			'site_key'         => isset( $_POST['site_key'] ) ? wp_unslash( $_POST['site_key'] ) : 'site',
		) );
	}

	/** @return void */
	protected function guard_usage_ajax_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'code' => 'wpaic_forbidden', 'message' => '你没有执行此操作的权限。' ), 403 );
		}
		check_ajax_referer( 'wpaic_usage_guard_test', 'nonce' );
	}

	/**
	 * @return array<int,string>
	 */
	protected function get_enabled_sources() {
		$enabled = get_option( WPAIC_OPTION_KNOWLEDGE_SOURCES, array() );
		$enabled = is_array( $enabled ) ? array_map( 'sanitize_key', $enabled ) : array();

		return array_values( array_unique( array_filter( $enabled ) ) );
	}

	/**
	 * @return void
	 */
	protected function guard_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-ai-chat-lab' ) );
		}
	}

	/**
	 * Guard Knowledge Store AJAX operations.
	 *
	 * @return void
	 */
	protected function guard_store_ajax_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_forbidden',
					'message' => '你没有执行此操作的权限。',
				),
				403
			);
		}

		check_ajax_referer( 'wpaic_store_sync', 'nonce' );
	}

	/**
	 * @return void
	 */
	protected function guard_ai_ajax_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'code'    => 'wpaic_forbidden',
					'message' => '你没有执行此操作的权限。',
				),
				403
			);
		}

		check_ajax_referer( 'wpaic_ai_test', 'nonce' );
	}

	/**
	 * @param WP_Error $error Error.
	 * @return void
	 */
	protected function send_error( WP_Error $error ) {
		$data        = $error->get_error_data();
		$status_code = is_array( $data ) && ! empty( $data['status_code'] ) ? (int) $data['status_code'] : 400;
		$status_code = ( $status_code >= 400 && $status_code <= 599 ) ? $status_code : 400;

		wp_send_json_error(
			array(
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			),
			$status_code
		);
	}

	/**
	 * @param array<string,mixed> $result Provider result.
	 * @return array<string,mixed>
	 */
	protected function format_result_for_admin( array $result ) {
		$usage = isset( $result['usage'] ) && is_array( $result['usage'] ) ? $result['usage'] : array();

		return array(
			'content'           => isset( $result['content'] ) ? (string) $result['content'] : '',
			'provider'          => isset( $result['provider'] ) ? (string) $result['provider'] : '',
			'request_model'     => isset( $result['request_model'] ) ? (string) $result['request_model'] : '',
			'response_model'    => isset( $result['response_model'] ) ? (string) $result['response_model'] : '',
			'prompt_tokens'     => isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : 0,
			'completion_tokens' => isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : 0,
			'total_tokens'      => isset( $usage['total_tokens'] ) ? (int) $usage['total_tokens'] : 0,
			'finish_reason'     => isset( $result['finish_reason'] ) ? (string) $result['finish_reason'] : '',
			'elapsed_ms'        => isset( $result['elapsed_ms'] ) ? (int) $result['elapsed_ms'] : 0,
			'status_code'       => isset( $result['status_code'] ) ? (int) $result['status_code'] : 0,
		);
	}

	/**
	 * @param string $value Value.
	 * @return int
	 */
	protected function string_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
