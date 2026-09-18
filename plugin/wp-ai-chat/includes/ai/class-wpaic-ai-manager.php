<?php
/**
 * Unified entry point for AI providers.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_AI_Manager {

	/** @var array<string,WPAIC_AI_Provider_Interface> */
	protected $providers = array();

	public function __construct() {
		$this->register_provider( new WPAIC_DeepSeek_Provider() );
	}

	/**
	 * @param WPAIC_AI_Provider_Interface $provider Provider instance.
	 * @return void
	 */
	public function register_provider( WPAIC_AI_Provider_Interface $provider ) {
		$this->providers[ $provider->get_id() ] = $provider;
	}

	/**
	 * @return array<string,WPAIC_AI_Provider_Interface>
	 */
	public function get_providers() {
		return $this->providers;
	}

	/**
	 * Return the currently selected provider.
	 *
	 * v0.2.0 only exposes DeepSeek, but the manager owns provider selection so
	 * future business modules never need to depend on a DeepSeek class directly.
	 *
	 * @return WPAIC_AI_Provider_Interface|WP_Error
	 */
	public function get_current_provider() {
		$settings    = get_option( WPAIC_OPTION_SETTINGS, array() );
		$provider_id = is_array( $settings ) && ! empty( $settings['provider'] ) ? sanitize_key( $settings['provider'] ) : WPAIC_DeepSeek_Provider::PROVIDER_ID;

		if ( ! isset( $this->providers[ $provider_id ] ) ) {
			return new WP_Error( 'wpaic_ai_provider_not_found', '当前 AI Provider 不可用。' );
		}

		return $this->providers[ $provider_id ];
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function test_connection() {
		$provider = $this->get_current_provider();
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		return $provider->test_connection();
	}

	/**
	 * @param array<int,array<string,mixed>> $messages Messages.
	 * @param array<string,mixed>            $options  Options.
	 * @return array<string,mixed>|WP_Error
	 */
	public function chat( array $messages, array $options = array() ) {
		// Extensibility point used by permanent Lab diagnostics to simulate a
		// Provider-bound failure without changing credentials or transport code.
		// Normal requests receive null and continue to the real provider.
		$pre_chat = apply_filters( 'wpaic_ai_manager_pre_chat_result', null, $messages, $options );
		if ( is_wp_error( $pre_chat ) ) {
			return $pre_chat;
		}

		$provider = $this->get_current_provider();
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		return $provider->chat( $messages, $options );
	}
}
