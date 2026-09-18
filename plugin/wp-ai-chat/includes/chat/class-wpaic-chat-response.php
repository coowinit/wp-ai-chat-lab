<?php
/** Public response mapper for v0.8.0 Stage 1. @package WP_AI_Chat_Lab */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Chat_Response {
	/** @return array<string,mixed> */
	public static function from_grounded_result( array $result, WPAIC_Chat_Context $context ) {
		$type    = 'no_answer';
		$message = __( 'I do not have enough reliable information in the site knowledge base to answer that yet.', 'wp-ai-chat-lab' );
		$answer_type = isset( $result['answer_type'] ) ? sanitize_key( (string) $result['answer_type'] ) : '';
		$gate        = isset( $result['gate'] ) && is_array( $result['gate'] ) ? $result['gate'] : array();
		$decision    = isset( $gate['decision'] ) ? sanitize_key( (string) $gate['decision'] ) : '';

		if ( 'usage_block' === $answer_type ) {
			$type    = 'blocked';
			$message = self::blocked_message( $result );
		} elseif ( ! empty( $result['ai_called'] ) && 'ai' === $answer_type ) {
			$type    = 'answer';
			$message = isset( $result['answer'] ) ? trim( (string) $result['answer'] ) : '';
		} elseif ( 'clarify' === $decision ) {
			$type    = 'clarify';
			$message = __( 'Please provide a more specific product model, use case, or question so I can answer reliably.', 'wp-ai-chat-lab' );
		} elseif ( 'no_answer' === $decision ) {
			$type    = 'no_answer';
			$message = __( 'I do not have enough reliable information in the site knowledge base to answer that yet.', 'wp-ai-chat-lab' );
		}
		if ( '' === $message ) {
			$type    = 'error';
			$message = __( 'The AI chat is temporarily unavailable. Please try again later.', 'wp-ai-chat-lab' );
		}
		return array(
			'success'         => true,
			'type'            => $type,
			'message'         => $message,
			'conversation_id' => $context->get_conversation_id(),
			'session'         => $context->public_session(),
		);
	}

	/** @return array<string,mixed> */
	public static function error( $message, $conversation_id = '' ) {
		return array(
			'success'         => false,
			'type'            => 'error',
			'message'         => (string) $message,
			'conversation_id' => (string) $conversation_id,
		);
	}

	protected static function blocked_message( array $result ) {
		$usage_guard = isset( $result['usage_guard'] ) && is_array( $result['usage_guard'] ) ? $result['usage_guard'] : array();
		$reason      = isset( $usage_guard['reason_code'] ) ? sanitize_key( (string) $usage_guard['reason_code'] ) : '';
		switch ( $reason ) {
			case 'conversation_limit_reached':
				return __( 'This conversation has reached its AI answer limit. Please start a new conversation.', 'wp-ai-chat-lab' );
			case 'visitor_daily_limit_reached':
				return __( 'Today\'s AI answer limit for this visitor has been reached. Please try again later.', 'wp-ai-chat-lab' );
			case 'site_daily_limit_reached':
				return __( 'The site\'s AI answer limit has been reached for today. Please try again later.', 'wp-ai-chat-lab' );
			default:
				return __( 'The AI chat is temporarily unavailable. Please try again later.', 'wp-ai-chat-lab' );
		}
	}
}
