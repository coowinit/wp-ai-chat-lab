<?php
/** Public Inquiry REST boundary. @package WP_AI_Chat_Lab */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Inquiry_Controller {
	const REST_NAMESPACE = 'wpaic/v1';
	const REST_ROUTE     = '/inquiry';

	/** @var WPAIC_Inquiry_Service */ protected $service;
	/** @var WPAIC_Inquiry_Rate_Guard */ protected $rate_guard;

	public function __construct( WPAIC_Inquiry_Service $service, WPAIC_Inquiry_Rate_Guard $rate_guard ) {
		$this->service    = $service;
		$this->rate_guard = $rate_guard;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_inquiry' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** @return WP_REST_Response */
	public function handle_inquiry( WP_REST_Request $request ) {
		$context = WPAIC_Inquiry_Context::from_request( $request );
		if ( is_wp_error( $context ) ) {
			return $this->response( 'error', __( 'The inquiry context is invalid. Please refresh and try again.', 'wp-ai-chat-lab' ), 400 );
		}

		// Honeypot is intentionally checked before rate consumption. A bot that
		// fills this hidden field is rejected without consuming a legitimate
		// visitor submission slot.
		$website = trim( sanitize_text_field( (string) $request->get_param( 'website' ) ) );
		if ( '' !== $website ) {
			$response = $this->response( 'error', __( 'Unable to submit this request.', 'wp-ai-chat-lab' ), 400 );
			$this->attach_visitor_cookie( $response, $context );
			return $response;
		}

		$rate = $this->rate_guard->consume( $context );
		if ( empty( $rate['allowed'] ) ) {
			$response = $this->response( 'error', __( 'Too many inquiry submissions. Please wait and try again later.', 'wp-ai-chat-lab' ), 429 );
			$response->header( 'Retry-After', (string) max( 1, isset( $rate['retry_after'] ) ? (int) $rate['retry_after'] : WPAIC_Inquiry_Rate_Guard::WINDOW ) );
			$this->attach_visitor_cookie( $response, $context );
			return $response;
		}

		$input = array(
			'name'          => $request->get_param( 'name' ),
			'email'         => $request->get_param( 'email' ),
			'phone'         => $request->get_param( 'phone' ),
			'company'       => $request->get_param( 'company' ),
			'message'       => $request->get_param( 'message' ),
			'trigger_type'  => $request->get_param( 'trigger_type' ),
			'last_question' => $request->get_param( 'last_question' ),
			'source_url'    => $this->resolve_source_url( $request ),
		);

		$result = $this->service->submit( $input, $context );
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
			$response = $this->response( 'error', $result->get_error_message(), $status );
			$this->attach_visitor_cookie( $response, $context );
			return $response;
		}

		$response = $this->response( 'submitted', __( 'Thank you. Your request has been submitted successfully.', 'wp-ai-chat-lab' ), 201 );
		$this->attach_visitor_cookie( $response, $context );
		return $response;
	}

	public static function get_endpoint_url() { return rest_url( self::REST_NAMESPACE . self::REST_ROUTE ); }

	protected function response( $type, $message, $status ) {
		return new WP_REST_Response(
			array(
				'type'    => (string) $type,
				'message' => (string) $message,
			),
			(int) $status
		);
	}

	protected function attach_visitor_cookie( WP_REST_Response $response, WPAIC_Inquiry_Context $context ) {
		$cookie = $context->get_set_cookie_header();
		if ( '' !== $cookie ) { $response->header( 'Set-Cookie', $cookie ); }
	}

	/** Accept only same-site source URLs; otherwise fall back to a same-site Referer. */
	protected function resolve_source_url( WP_REST_Request $request ) {
		$candidates = array(
			(string) $request->get_param( 'source_url' ),
			(string) $request->get_header( 'referer' ),
		);
		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$home_host = is_string( $home_host ) ? strtolower( $home_host ) : '';
		foreach ( $candidates as $candidate ) {
			$url = esc_url_raw( trim( $candidate ) );
			if ( '' === $url ) { continue; }
			$host = wp_parse_url( $url, PHP_URL_HOST );
			$host = is_string( $host ) ? strtolower( $host ) : '';
			if ( '' !== $home_host && $host === $home_host ) { return $url; }
		}
		return '';
	}
}
