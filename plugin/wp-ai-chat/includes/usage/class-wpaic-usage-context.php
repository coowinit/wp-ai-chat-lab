<?php
/**
 * Normalized Usage Guard scope context.
 *
 * @package WP_AI_Chat_Lab
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Usage_Context {
	/** @var array<string,string> */
	protected $keys = array();

	/**
	 * @param array<string,mixed> $context Raw context.
	 */
	public function __construct( array $context = array() ) {
		$this->keys = array(
			'conversation' => isset( $context['conversation_key'] ) ? trim( sanitize_text_field( (string) $context['conversation_key'] ) ) : '',
			'visitor'      => isset( $context['visitor_key'] ) ? trim( sanitize_text_field( (string) $context['visitor_key'] ) ) : '',
			'site'         => isset( $context['site_key'] ) ? trim( sanitize_text_field( (string) $context['site_key'] ) ) : 'site',
		);
	}

	/** @return string */
	public function get_key( $scope ) {
		return isset( $this->keys[ $scope ] ) ? $this->keys[ $scope ] : '';
	}

	/** @return string */
	public function get_hash( $scope ) {
		$key = $this->get_key( $scope );
		if ( '' === $key ) { return ''; }
		return hash_hmac( 'sha256', $scope . ':' . $key, wp_salt( 'auth' ) );
	}

	/** @return array<string,string> */
	public function to_array() {
		return array(
			'conversation_key' => $this->keys['conversation'],
			'visitor_key'      => $this->keys['visitor'],
			'site_key'         => $this->keys['site'],
		);
	}
}
