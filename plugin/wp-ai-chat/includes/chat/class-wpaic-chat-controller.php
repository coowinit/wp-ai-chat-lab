<?php
/** Public REST Chat boundary for v0.8.0 Stage 1. @package WP_AI_Chat_Lab */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Chat_Controller {
	const REST_NAMESPACE = 'wpaic/v1';
	const REST_ROUTE     = '/chat';
	const MAX_QUESTION   = 1000;

	/** @var WPAIC_Grounded_Answer_Service */
	protected $grounded_answer;

	/** @var WPAIC_Public_Request_Guard */
	protected $request_guard;

	/** @var WPAIC_Provider_Failure_Lab */
	protected $provider_failure_lab;

	/** @var WPAIC_Lead_Trigger_Policy */
	protected $lead_trigger_policy;

	public function __construct( WPAIC_Grounded_Answer_Service $grounded_answer, WPAIC_Public_Request_Guard $request_guard, WPAIC_Provider_Failure_Lab $provider_failure_lab, WPAIC_Lead_Trigger_Policy $lead_trigger_policy ) {
		$this->grounded_answer      = $grounded_answer;
		$this->request_guard        = $request_guard;
		$this->provider_failure_lab = $provider_failure_lab;
		$this->lead_trigger_policy  = $lead_trigger_policy;
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

		// Optional session-only Lead continuity context. This value is never sent to
		// Retrieval / Grounding / Usage Guard / Provider. The server re-evaluates
		// the deterministic Lead Trigger Policy instead of trusting a client flag.
		$lead_context_question = trim( sanitize_textarea_field( (string) $request->get_param( 'lead_context_question' ) ) );
		if ( $this->string_length( $lead_context_question ) > self::MAX_QUESTION ) {
			$lead_context_question = '';
		}

		$context = WPAIC_Chat_Context::from_request( $request );
		if ( is_wp_error( $context ) ) {
			return $this->error_response( __( 'The conversation context is invalid. Please start a new conversation.', 'wp-ai-chat-lab' ), 400 );
		}

		$rate = $this->request_guard->consume( $context );
		if ( empty( $rate['allowed'] ) ) {
			$response = $this->error_response( __( 'Too many chat requests. Please wait a moment and try again.', 'wp-ai-chat-lab' ), 429, $context->get_conversation_id() );
			$response->header( 'Retry-After', (string) max( 1, isset( $rate['retry_after'] ) ? (int) $rate['retry_after'] : 60 ) );
			$this->attach_visitor_cookie( $response, $context );
			return $response;
		}

		// Permanent Lab-only failure injection is scoped to the current Visitor
		// and attaches only for this request. It is consumed only if Grounding
		// and Usage Guard actually reach AI Manager.
		$this->provider_failure_lab->attach_for_request( $context );
		try {
			$result = $this->grounded_answer->answer(
				$question,
				array(
					'candidate_limit' => 100,
					'top_k'           => 5,
					'usage_context'   => $context->to_usage_context(),
				)
			);
		} finally {
			$this->provider_failure_lab->detach();
		}
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

		$public        = WPAIC_Chat_Response::from_grounded_result( $result, $context );
		$response_type = isset( $public['type'] ) ? sanitize_key( (string) $public['type'] ) : 'error';
		$block_reason  = $this->extract_block_reason( $result );

		// Commercial intent may legitimately receive `clarify` first. Round 1
		// correctly prevents an automatic CTA on clarify, but we preserve a safe
		// pending marker so the next clarified turn can recover the original intent.
		if ( 'clarify' === $response_type ) {
			$pending_question = '' !== $lead_context_question ? $lead_context_question : $question;
			if ( '' !== $this->lead_trigger_policy->match_commercial_keyword( $pending_question ) ) {
				$public['lead_pending'] = array( 'trigger_type' => 'commercial_intent' );
			}
		} else {
			$lead = $this->lead_trigger_policy->evaluate(
				array(
					'manual'        => false,
					'question'      => $question,
					'response_type' => $response_type,
					'block_reason'  => $block_reason,
				)
			);

			// If this follow-up does not itself contain the commercial keyword, run the
			// same server-owned Policy against the pending commercial question. This
			// affects only Lead CTA selection and never the AI answer path.
			if ( empty( $lead['offer'] ) && '' !== $lead_context_question ) {
				$lead = $this->lead_trigger_policy->evaluate(
					array(
						'manual'        => false,
						'question'      => $lead_context_question,
						'response_type' => $response_type,
						'block_reason'  => $block_reason,
					)
				);
			}

			if ( ! empty( $lead['offer'] ) ) {
				$public['lead'] = array(
					'trigger_type' => isset( $lead['trigger_type'] ) ? (string) $lead['trigger_type'] : '',
					'cta_label'    => isset( $lead['cta_label'] ) ? (string) $lead['cta_label'] : '',
					'placement'    => isset( $lead['placement'] ) ? (string) $lead['placement'] : 'primary',
				);
			}
		}

		$response = new WP_REST_Response( $public, 200 );
		$this->attach_visitor_cookie( $response, $context );
		return $response;
	}


	/** Internal Usage reason is used only to choose the safe public Lead CTA. */
	protected function extract_block_reason( array $result ) {
		if ( 'usage_block' !== ( isset( $result['answer_type'] ) ? (string) $result['answer_type'] : '' ) ) {
			return '';
		}
		$guard  = isset( $result['usage_guard'] ) && is_array( $result['usage_guard'] ) ? $result['usage_guard'] : array();
		$reason = isset( $guard['reason_code'] ) ? sanitize_key( (string) $guard['reason_code'] ) : '';
		$allowed = array( 'conversation_limit_reached', 'visitor_daily_limit_reached', 'site_daily_limit_reached' );
		return in_array( $reason, $allowed, true ) ? $reason : '';
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
