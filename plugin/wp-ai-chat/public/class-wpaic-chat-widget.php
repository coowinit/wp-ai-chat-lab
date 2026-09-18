<?php
/**
 * Front-end Chat Widget integration for v0.8.0 Stage 2.
 *
 * @package WP_AI_Chat_Lab
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Chat_Widget {
	/** @var bool */
	protected $assets_enqueued = false;

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 30 );
	}

	/** @return bool */
	public function is_enabled() {
		$settings = get_option( WPAIC_OPTION_CHAT_UI_SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();
		return ! empty( $settings['enabled'] );
	}

	/** @return void */
	public function enqueue_assets() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$css_file = WPAIC_PLUGIN_DIR . 'public/assets/css/chat.css';
		$js_file  = WPAIC_PLUGIN_DIR . 'public/assets/js/chat.js';
		$css_hash = is_readable( $css_file ) ? md5_file( $css_file ) : false;
		$js_hash  = is_readable( $js_file ) ? md5_file( $js_file ) : false;
		$css_ver  = $css_hash ? WPAIC_VERSION . '.' . substr( $css_hash, 0, 8 ) : WPAIC_VERSION;
		$js_ver   = $js_hash ? WPAIC_VERSION . '.' . substr( $js_hash, 0, 8 ) : WPAIC_VERSION;

		wp_enqueue_style( 'wpaic-chat', WPAIC_PLUGIN_URL . 'public/assets/css/chat.css', array(), $css_ver );
		wp_enqueue_script( 'wpaic-chat', WPAIC_PLUGIN_URL . 'public/assets/js/chat.js', array(), $js_ver, true );

		$site_name    = trim( wp_strip_all_tags( get_bloginfo( 'name' ) ) );
		$support_name = $site_name ? sprintf( __( '%s Support', 'wp-ai-chat-lab' ), $site_name ) : __( 'AI Support', 'wp-ai-chat-lab' );

		wp_localize_script(
			'wpaic-chat',
			'WPAICChat',
			array(
				'endpoint'    => WPAIC_Chat_Controller::get_endpoint_url(),
				'storageKey'  => 'wpaic_chat_session_v1',
				'supportName' => $support_name,
				'greeting'    => __( 'Hello! How can we help you today?', 'wp-ai-chat-lab' ),
				'i18n'        => array(
					'you'            => __( 'You', 'wp-ai-chat-lab' ),
					'typing'         => __( 'Thinking…', 'wp-ai-chat-lab' ),
					'networkError'   => __( 'The chat is temporarily unavailable. Please try again.', 'wp-ai-chat-lab' ),
					'newConversation'=> __( 'New conversation started.', 'wp-ai-chat-lab' ),
				),
			)
		);
		$this->assets_enqueued = true;
	}

	/** @return void */
	public function render() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! $this->assets_enqueued ) {
			$this->enqueue_assets();
		}

		$site_name    = trim( wp_strip_all_tags( get_bloginfo( 'name' ) ) );
		$support_name = $site_name ? sprintf( __( '%s Support', 'wp-ai-chat-lab' ), $site_name ) : __( 'AI Support', 'wp-ai-chat-lab' );
		$avatar       = $this->avatar_text( $site_name ? $site_name : 'AI' );
		include WPAIC_PLUGIN_DIR . 'public/views/chat-widget.php';
	}

	/** @return string */
	protected function avatar_text( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value ) {
			return 'AI';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return strtoupper( mb_substr( $value, 0, 2, 'UTF-8' ) );
		}
		return strtoupper( substr( $value, 0, 2 ) );
	}
}
