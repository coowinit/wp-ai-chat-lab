<?php
/**
 * DeepSeek implementation of the AI provider contract.
 *
 * This class intentionally contains transport and response normalization only.
 * Knowledge, retrieval, grounding, usage limits, conversations and business
 * prompts belong to later layers.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_DeepSeek_Provider implements WPAIC_AI_Provider_Interface {

	const PROVIDER_ID  = 'deepseek';
	const API_URL      = 'https://api.deepseek.com/chat/completions';
	const DEFAULT_MODEL = 'deepseek-flash';
	const DEFAULT_TIMEOUT = 60;

	/**
	 * @return string
	 */
	public function get_id() {
		return self::PROVIDER_ID;
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return 'DeepSeek';
	}

	/**
	 * v0.2.0 intentionally exposes only the current recommended Flash alias.
	 *
	 * @return array<string,string>
	 */
	public function get_models() {
		return array(
			self::DEFAULT_MODEL => 'DeepSeek V4.1 Flash',
		);
	}

	/**
	 * Return the complete settings array.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_settings() {
		$settings = get_option( WPAIC_OPTION_SETTINGS, array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Return the active API key without exposing it to the admin UI.
	 *
	 * wp-config.php takes precedence over the database option.
	 *
	 * @return string
	 */
	public function get_api_key() {
		if ( defined( 'WPAIC_DEEPSEEK_API_KEY' ) && is_string( WPAIC_DEEPSEEK_API_KEY ) ) {
			return trim( WPAIC_DEEPSEEK_API_KEY );
		}

		$settings = $this->get_settings();
		$key      = isset( $settings['api_key'] ) && is_string( $settings['api_key'] ) ? $settings['api_key'] : '';

		return trim( $key );
	}

	/**
	 * Describe where the current API key is coming from.
	 *
	 * @return string constant|option|none
	 */
	public function get_api_key_source() {
		if ( defined( 'WPAIC_DEEPSEEK_API_KEY' ) && '' !== $this->get_api_key() ) {
			return 'constant';
		}

		$settings = $this->get_settings();
		if ( ! empty( $settings['api_key'] ) && is_string( $settings['api_key'] ) ) {
			return 'option';
		}

		return 'none';
	}

	/**
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->get_api_key();
	}

	/**
	 * Return a validated model id.
	 *
	 * @return string
	 */
	public function get_model() {
		$settings = $this->get_settings();
		$model    = isset( $settings['model'] ) && is_string( $settings['model'] ) ? $settings['model'] : self::DEFAULT_MODEL;
		$models   = $this->get_models();

		return isset( $models[ $model ] ) ? $model : self::DEFAULT_MODEL;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function test_connection() {
		return $this->chat(
			array(
				array(
					'role'    => 'system',
					'content' => 'You are a connection test. Follow the user instruction exactly.',
				),
				array(
					'role'    => 'user',
					'content' => 'Reply exactly with WPAIC_OK.',
				),
			),
			array(
				'max_tokens'  => 16,
				'temperature' => 0.0,
			)
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $messages Chat messages.
	 * @param array<string,mixed>            $options  Supported provider options.
	 * @return array<string,mixed>|WP_Error
	 */
	public function chat( array $messages, array $options = array() ) {
		$api_key = $this->get_api_key();

		if ( '' === $api_key ) {
			return new WP_Error( 'wpaic_ai_missing_key', '尚未配置 DeepSeek API Key。' );
		}

		if ( empty( $messages ) ) {
			return new WP_Error( 'wpaic_ai_empty_messages', 'AI 请求消息不能为空。' );
		}

		$validated_messages = $this->validate_messages( $messages );
		if ( is_wp_error( $validated_messages ) ) {
			return $validated_messages;
		}

		$model  = $this->resolve_model( $options );
		$models = $this->get_models();

		if ( ! isset( $models[ $model ] ) ) {
			return new WP_Error( 'wpaic_ai_invalid_model', '当前插件版本不支持所选 DeepSeek 模型。' );
		}

		$body = array(
			'model'    => $model,
			'messages' => $validated_messages,
			'stream'   => false,
			// DeepSeek currently enables thinking by default. The provider foundation
			// deliberately disables it for predictable, low-cost transport tests.
			'thinking' => array(
				'type' => 'disabled',
			),
		);

		if ( isset( $options['max_tokens'] ) ) {
			$body['max_tokens'] = max( 1, min( 2048, absint( $options['max_tokens'] ) ) );
		}

		if ( isset( $options['temperature'] ) && is_numeric( $options['temperature'] ) ) {
			$body['temperature'] = max( 0, min( 2, (float) $options['temperature'] ) );
		}

		$timeout = (int) apply_filters( 'wpaic_deepseek_timeout', self::DEFAULT_TIMEOUT );
		$timeout = max( 10, min( 120, $timeout ) );

		$started_at = microtime( true );
		$response   = wp_remote_post(
			self::API_URL,
			array(
				'timeout'     => $timeout,
				'redirection' => 2,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'        => wp_json_encode( $body ),
				'data_format' => 'body',
			)
		);
		$elapsed_ms = (int) round( ( microtime( true ) - $started_at ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return $this->normalize_transport_error( $response );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return $this->normalize_http_error( $status_code, $decoded );
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'wpaic_ai_invalid_response', 'DeepSeek API 返回了无法解析的 JSON 响应。' );
		}

		$content = '';
		if ( isset( $decoded['choices'][0]['message']['content'] ) && is_string( $decoded['choices'][0]['message']['content'] ) ) {
			$content = trim( $decoded['choices'][0]['message']['content'] );
		}

		if ( '' === $content ) {
			return new WP_Error( 'wpaic_ai_empty_response', 'DeepSeek API 请求成功，但没有返回可读取的文本内容。' );
		}

		$usage = isset( $decoded['usage'] ) && is_array( $decoded['usage'] ) ? $decoded['usage'] : array();

		return array(
			'content'         => $content,
			'provider'        => self::PROVIDER_ID,
			'request_model'   => $model,
			'response_model'  => isset( $decoded['model'] ) && is_string( $decoded['model'] ) ? $decoded['model'] : $model,
			'usage'           => array(
				'prompt_tokens'     => isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : 0,
				'completion_tokens' => isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : 0,
				'total_tokens'      => isset( $usage['total_tokens'] ) ? (int) $usage['total_tokens'] : 0,
				'raw'               => $usage,
			),
			'finish_reason'   => isset( $decoded['choices'][0]['finish_reason'] ) && is_string( $decoded['choices'][0]['finish_reason'] ) ? $decoded['choices'][0]['finish_reason'] : '',
			'elapsed_ms'      => $elapsed_ms,
			'status_code'     => $status_code,
		);
	}

	/**
	 * @param array<string,mixed> $options Options.
	 * @return string
	 */
	protected function resolve_model( array $options ) {
		if ( isset( $options['model'] ) && is_string( $options['model'] ) && '' !== trim( $options['model'] ) ) {
			return sanitize_key( $options['model'] );
		}

		return $this->get_model();
	}

	/**
	 * Validate and normalize OpenAI-style text messages.
	 *
	 * @param array<int,array<string,mixed>> $messages Messages.
	 * @return array<int,array<string,string>>|WP_Error
	 */
	protected function validate_messages( array $messages ) {
		$allowed_roles = array( 'system', 'user', 'assistant' );
		$normalized    = array();

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				return new WP_Error( 'wpaic_ai_invalid_messages', 'AI 请求消息格式无效。' );
			}

			$role    = isset( $message['role'] ) && is_string( $message['role'] ) ? $message['role'] : '';
			$content = isset( $message['content'] ) && is_string( $message['content'] ) ? trim( $message['content'] ) : '';

			if ( ! in_array( $role, $allowed_roles, true ) || '' === $content ) {
				return new WP_Error( 'wpaic_ai_invalid_messages', 'AI 请求中的 role 或 content 无效。' );
			}

			$normalized[] = array(
				'role'    => $role,
				'content' => $content,
			);
		}

		return $normalized;
	}

	/**
	 * @param WP_Error $error WordPress HTTP error.
	 * @return WP_Error
	 */
	protected function normalize_transport_error( WP_Error $error ) {
		$message = $error->get_error_message();
		$lower   = strtolower( $message );

		if ( false !== strpos( $lower, 'timed out' ) || false !== strpos( $lower, 'timeout' ) || false !== strpos( $lower, 'curl error 28' ) ) {
			return new WP_Error( 'wpaic_ai_timeout', '连接 DeepSeek API 超时，请稍后重试。' );
		}

		return new WP_Error( 'wpaic_ai_transport_error', '无法连接 DeepSeek API：' . $message );
	}

	/**
	 * @param int               $status_code HTTP status code.
	 * @param array<mixed>|null $decoded     Decoded response body.
	 * @return WP_Error
	 */
	protected function normalize_http_error( $status_code, $decoded ) {
		$message = $this->extract_api_error_message( $decoded );

		if ( 401 === $status_code || 403 === $status_code ) {
			return new WP_Error( 'wpaic_ai_auth_error', 'DeepSeek API Key 无效或没有访问权限。', array( 'status_code' => $status_code ) );
		}

		if ( 429 === $status_code ) {
			return new WP_Error( 'wpaic_ai_rate_limited', 'DeepSeek API 当前请求过多或触发限流，请稍后重试。', array( 'status_code' => $status_code ) );
		}

		if ( $status_code >= 500 ) {
			return new WP_Error( 'wpaic_ai_provider_error', $message ? 'DeepSeek 服务暂时不可用：' . $message : 'DeepSeek 服务暂时不可用，请稍后重试。', array( 'status_code' => $status_code ) );
		}

		if ( false !== stripos( $message, 'model' ) ) {
			return new WP_Error( 'wpaic_ai_invalid_model', 'DeepSeek 模型配置无效：' . $message, array( 'status_code' => $status_code ) );
		}

		return new WP_Error(
			'wpaic_ai_api_error',
			$message ? 'DeepSeek API 请求失败：' . $message : sprintf( 'DeepSeek API 返回 HTTP %d。', $status_code ),
			array( 'status_code' => $status_code )
		);
	}

	/**
	 * @param array<mixed>|null $decoded Decoded response.
	 * @return string
	 */
	protected function extract_api_error_message( $decoded ) {
		if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
			return trim( $decoded['error']['message'] );
		}

		return '';
	}
}
