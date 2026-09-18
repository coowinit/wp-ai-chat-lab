<?php
/** Public REST Chat boundary for v0.8.0 Stage 1. @package WP_AI_Chat_Lab */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Chat_Controller {
	const REST_NAMESPACE = 'wpaic/v1';
	const REST_ROUTE     = '/chat';
	const MAX_QUESTION   = 1000;

	/** @var WPAIC_Grounded_Answer_Service */
	protected $grounded_answer;

	public function __construct( WPAIC_Grounded_Answer_Service $grounded_answer ) {
		$this->grounded_answer = $grounded_answer;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** @return WP_REST_Response */
	public function handle_chat( WP_REST_Request $request ) {
		$question = trim( sanitize_textarea_field( (string) $request->get_param( 'question' ) ) );
		if ( '' === $question ) {
			return $this->error_response( __( 'Please enter a question.', 'wp-ai-chat-lab' ), 400 );
		}
		if ( $this->string_length( $question ) > self::MAX_QUESTION ) {
			return $this->error_response( __( 'The question is too long. Please keep it within 1000 characters.', 'wp-ai-chat-lab' ), 400 );
		}

		$context = WPAIC_Chat_Context::from_request( $request );
		if ( is_wp_error( $context ) ) {
			return $this->error_response( __( 'The conversation context is invalid. Please start a new conversation.', 'wp-ai-chat-lab' ), 400 );
		}

		$result = $this->grounded_answer->answer(
			$question,
			array(
				'candidate_limit' => 100,
				'top_k'           => 5,
				'usage_context'   => $context->to_usage_context(),
			)
		);
		if ( is_wp_error( $result ) ) {
			do_action( 'wpaic_public_chat_error', $result );
			$response = $this->error_response(
				__( 'The AI chat is temporarily unavailable. Please try again later.', 'wp-ai-chat-lab' ),
				503,
				$context->get_conversation_id()
			);
			$this->attach_visitor_cookie( $response, $context );
			return $response;
		}

		$response = new WP_REST_Response( WPAIC_Chat_Response::from_grounded_result( $result, $context ), 200 );
		$this->attach_visitor_cookie( $response, $context );
		return $response;
	}

	public static function get_endpoint_url() { return rest_url( self::REST_NAMESPACE . self::REST_ROUTE ); }

	protected function error_response( $message, $status, $conversation_id = '' ) {
		return new WP_REST_Response( WPAIC_Chat_Response::error( $message, $conversation_id ), (int) $status );
	}

	protected function attach_visitor_cookie( WP_REST_Response $response, WPAIC_Chat_Context $context ) {
		$cookie = $context->get_set_cookie_header();
		if ( '' !== $cookie ) { $response->header( 'Set-Cookie', $cookie ); }
	}

	protected function string_length( $value ) {
		$value = (string) $value;
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}
}
