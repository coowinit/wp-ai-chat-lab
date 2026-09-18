<?php
/**
 * Public Chat identity context for v0.8.0 Stage 1.
 *
 * @package WP_AI_Chat_Lab
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Chat_Context {
	const VISITOR_COOKIE = 'wpaic_visitor';
	const VISITOR_TTL    = YEAR_IN_SECONDS;

	/** @var string */ protected $conversation_id = '';
	/** @var string */ protected $visitor_token = '';
	/** @var string */ protected $site_key = '';
	/** @var bool */ protected $conversation_created = false;
	/** @var bool */ protected $visitor_created = false;

	protected function __construct( $conversation_id, $visitor_token, $site_key, $conversation_created, $visitor_created ) {
		$this->conversation_id      = (string) $conversation_id;
		$this->visitor_token        = (string) $visitor_token;
		$this->site_key             = (string) $site_key;
		$this->conversation_created = (bool) $conversation_created;
		$this->visitor_created      = (bool) $visitor_created;
	}

	/** @return self|WP_Error */
	public static function from_request( WP_REST_Request $request ) {
		$conversation_id      = trim( sanitize_text_field( (string) $request->get_param( 'conversation_id' ) ) );
		$conversation_created = false;
		if ( '' === $conversation_id ) {
			$conversation_id      = wp_generate_uuid4();
			$conversation_created = true;
		} elseif ( ! self::is_uuid4( $conversation_id ) ) {
			return new WP_Error( 'wpaic_chat_invalid_conversation', 'Conversation ID must be a valid UUID v4.' );
		}

		$visitor_token = '';
		if ( isset( $_COOKIE[ self::VISITOR_COOKIE ] ) ) {
			$visitor_token = trim( sanitize_text_field( wp_unslash( $_COOKIE[ self::VISITOR_COOKIE ] ) ) );
		}
		$visitor_created = false;
		if ( ! self::is_uuid4( $visitor_token ) ) {
			$visitor_token   = wp_generate_uuid4();
			$visitor_created = true;
		}

		$site_identity = strtolower( untrailingslashit( home_url( '/' ) ) );
		$site_key      = 'site:' . hash( 'sha256', $site_identity );
		return new self( $conversation_id, $visitor_token, $site_key, $conversation_created, $visitor_created );
	}

	public function get_conversation_id() { return $this->conversation_id; }
	public function is_visitor_created() { return $this->visitor_created; }

	/** @return array<string,string> */
	public function to_usage_context() {
		return array(
			'conversation_key' => 'chat:' . $this->conversation_id,
			'visitor_key'      => 'visitor:' . $this->visitor_token,
			'site_key'         => $this->site_key,
		);
	}

	/** @return array<string,string> */
	public function public_session() {
		return array(
			'conversation' => $this->conversation_created ? 'created' : 'existing',
			'visitor'      => $this->visitor_created ? 'created' : 'existing',
		);
	}

	/** @return string */
	public function get_set_cookie_header() {
		if ( ! $this->visitor_created ) { return ''; }
		$path    = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$expires = time() + self::VISITOR_TTL;
		$parts   = array(
			self::VISITOR_COOKIE . '=' . rawurlencode( $this->visitor_token ),
			'Expires=' . gmdate( 'D, d M Y H:i:s', $expires ) . ' GMT',
			'Max-Age=' . self::VISITOR_TTL,
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
