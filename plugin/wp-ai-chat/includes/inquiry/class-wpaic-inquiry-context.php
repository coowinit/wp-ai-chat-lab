<?php
/**
 * Public Inquiry identity context.
 *
 * Reuses the existing HttpOnly wpaic_visitor cookie contract without creating
 * a Conversation when one is not supplied. Raw visitor UUIDs are never
 * persisted by the Inquiry layer.
 *
 * @package WP_AI_Chat_Lab
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Inquiry_Context {
	/** @var string */ protected $conversation_id = '';
	/** @var string */ protected $visitor_token = '';
	/** @var bool */ protected $visitor_created = false;

	protected function __construct( $conversation_id, $visitor_token, $visitor_created ) {
		$this->conversation_id = (string) $conversation_id;
		$this->visitor_token   = (string) $visitor_token;
		$this->visitor_created = (bool) $visitor_created;
	}

	/** @return self|WP_Error */
	public static function from_request( WP_REST_Request $request ) {
		$conversation_id = trim( sanitize_text_field( (string) $request->get_param( 'conversation_id' ) ) );
		if ( '' !== $conversation_id && ! self::is_uuid4( $conversation_id ) ) {
			return new WP_Error( 'wpaic_inquiry_invalid_conversation', 'Conversation ID must be a valid UUID v4.' );
		}

		$visitor_token = '';
		if ( isset( $_COOKIE[ WPAIC_Chat_Context::VISITOR_COOKIE ] ) ) {
			$visitor_token = trim( sanitize_text_field( wp_unslash( $_COOKIE[ WPAIC_Chat_Context::VISITOR_COOKIE ] ) ) );
		}

		$visitor_created = false;
		if ( ! self::is_uuid4( $visitor_token ) ) {
			$visitor_token   = wp_generate_uuid4();
			$visitor_created = true;
		}

		return new self( $conversation_id, $visitor_token, $visitor_created );
	}

	public function get_conversation_id() { return $this->conversation_id; }

	/** Hash-only identity for persistence and short-lived rate limiting. */
	public function get_visitor_identity_hash() {
		if ( '' === $this->visitor_token ) { return ''; }
		return hash_hmac( 'sha256', $this->visitor_token, wp_salt( 'auth' ) );
	}

	/** @return string */
	public function get_set_cookie_header() {
		if ( ! $this->visitor_created ) { return ''; }
		$path    = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$expires = time() + WPAIC_Chat_Context::VISITOR_TTL;
		$parts   = array(
			WPAIC_Chat_Context::VISITOR_COOKIE . '=' . rawurlencode( $this->visitor_token ),
			'Expires=' . gmdate( 'D, d M Y H:i:s', $expires ) . ' GMT',
			'Max-Age=' . WPAIC_Chat_Context::VISITOR_TTL,
			'Path=' . $path,
			'SameSite=Lax',
			'HttpOnly',
		);
		if ( is_ssl() ) { $parts[] = 'Secure'; }
		return implode( '; ', $parts );
	}

	protected static function is_uuid4( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
