<?php
/**
 * WordPress admin UI for v0.2.0 provider settings and minimal tests.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Admin {

	/** @var WPAIC_AI_Manager */
	protected $manager;

	/** @var string */
	protected $page_hook = '';

	/**
	 * @param WPAIC_AI_Manager $manager AI manager.
	 */
	public function __construct( WPAIC_AI_Manager $manager ) {
		$this->manager = $manager;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wpaic_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_wpaic_test_chat', array( $this, 'ajax_test_chat' ) );
	}

	/**
	 * @return void
	 */
	public function register_menu() {
		$this->page_hook = add_menu_page(
			'WP AI Chat Lab',
			'WP AI Chat Lab',
			'manage_options',
			'wp-ai-chat-lab',
			array( $this, 'render_page' ),
			'dashicons-format-chat',
			81
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

		$existing_key = isset( $current['api_key'] ) && is_string( $current['api_key'] ) ? trim( $current['api_key'] ) : '';
		$submitted_key = isset( $input['api_key'] ) && is_string( $input['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $input['api_key'] ) ) ) : '';
		$clear_key = ! empty( $input['clear_api_key'] );

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
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'wpaic-admin',
			WPAIC_PLUGIN_URL . 'admin/assets/admin.css',
			array(),
			WPAIC_VERSION
		);

		wp_enqueue_script(
			'wpaic-admin',
			WPAIC_PLUGIN_URL . 'admin/assets/admin.js',
			array(),
			WPAIC_VERSION,
			true
		);

		wp_localize_script(
			'wpaic-admin',
			'WPAICAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpaic_ai_test' ),
				'i18n'    => array(
					'working' => '请求中…',
					'error'   => '请求失败，请重试。',
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-ai-chat-lab' ) );
		}

		$provider = $this->manager->get_current_provider();
		if ( is_wp_error( $provider ) ) {
			wp_die( esc_html( $provider->get_error_message() ) );
		}

		$settings = get_option( WPAIC_OPTION_SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();

		// options.php redirects back with settings-updated=true after a successful save.
		// Add the success notice here instead of inside sanitize_settings(), because
		// WordPress may run setting sanitization more than once during an update.
		// Keeping the notice out of the sanitizer prevents duplicate messages.
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
	public function ajax_test_connection() {
		$this->guard_ajax_request();

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
		$this->guard_ajax_request();

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
	 * @return void
	 */
	protected function guard_ajax_request() {
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
