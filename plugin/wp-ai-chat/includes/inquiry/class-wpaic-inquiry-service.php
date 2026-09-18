<?php
/** Inquiry validation and persistence service. @package WP_AI_Chat_Lab */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Inquiry_Service {
	/** @var WPAIC_Inquiry_Repository */ protected $repository;

	public function __construct( WPAIC_Inquiry_Repository $repository ) {
		$this->repository = $repository;
	}

	/** @return array<string,mixed>|WP_Error */
	public function submit( array $input, WPAIC_Inquiry_Context $context ) {
		$raw_name          = isset( $input['name'] ) ? (string) $input['name'] : '';
		$raw_email         = isset( $input['email'] ) ? (string) $input['email'] : '';
		$raw_phone         = isset( $input['phone'] ) ? (string) $input['phone'] : '';
		$raw_company       = isset( $input['company'] ) ? (string) $input['company'] : '';
		$raw_message       = isset( $input['message'] ) ? (string) $input['message'] : '';
		$raw_last_question = isset( $input['last_question'] ) ? (string) $input['last_question'] : '';

		$length_error = $this->validate_lengths( $raw_name, $raw_email, $raw_phone, $raw_company, $raw_message, $raw_last_question );
		if ( is_wp_error( $length_error ) ) { return $length_error; }

		$name          = $this->clean_text( $raw_name );
		$email         = sanitize_email( $raw_email );
		$phone         = $this->clean_text( $raw_phone );
		$company       = $this->clean_text( $raw_company );
		$message       = $this->clean_textarea( $raw_message );
		$last_question = $this->clean_textarea( $raw_last_question );
		$trigger_type = isset( $input['trigger_type'] ) ? sanitize_key( (string) $input['trigger_type'] ) : '';
		$source_url   = isset( $input['source_url'] ) ? esc_url_raw( (string) $input['source_url'] ) : '';

		if ( '' === $name ) {
			return new WP_Error( 'inquiry_name_required', 'Please enter your name.', array( 'status' => 400 ) );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'inquiry_email_invalid', 'Please enter a valid email address.', array( 'status' => 400 ) );
		}
		if ( '' === $message ) {
			return new WP_Error( 'inquiry_message_required', 'Please enter your message.', array( 'status' => 400 ) );
		}

		$allowed_triggers = array(
			WPAIC_Lead_Trigger_Policy::TRIGGER_MANUAL,
			WPAIC_Lead_Trigger_Policy::TRIGGER_COMMERCIAL_INTENT,
			WPAIC_Lead_Trigger_Policy::TRIGGER_NO_ANSWER,
			WPAIC_Lead_Trigger_Policy::TRIGGER_USAGE_BLOCKED,
		);
		if ( ! in_array( $trigger_type, $allowed_triggers, true ) ) {
			return new WP_Error( 'inquiry_trigger_invalid', 'The inquiry context is invalid. Please try again.', array( 'status' => 400 ) );
		}

		$visitor_hash = $context->get_visitor_identity_hash();
		if ( '' === $visitor_hash ) {
			return new WP_Error( 'inquiry_context_unavailable', 'The inquiry service is temporarily unavailable.', array( 'status' => 503 ) );
		}

		$now = current_time( 'mysql' );
		$id = $this->repository->insert(
			array(
				'created_at'      => $now,
				'updated_at'      => $now,
				'status'          => 'new',
				'name'            => $name,
				'email'           => $email,
				'phone'           => $phone,
				'company'         => $company,
				'message'         => $message,
				'trigger_type'    => $trigger_type,
				'conversation_id' => $context->get_conversation_id(),
				'source_url'      => $source_url,
				'last_question'   => $last_question,
				'visitor_hash'    => $visitor_hash,
			)
		);
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'inquiry_persistence_failed', 'The inquiry service is temporarily unavailable. Please try again later.', array( 'status' => 500 ) );
		}

		return array( 'saved' => true, 'id' => (int) $id );
	}

	protected function validate_lengths( $name, $email, $phone, $company, $message, $last_question ) {
		$limits = array(
			'name' => array( $name, 120 ),
			'email' => array( $email, 191 ),
			'phone' => array( $phone, 80 ),
			'company' => array( $company, 191 ),
			'message' => array( $message, 5000 ),
			'last_question' => array( $last_question, 1000 ),
		);
		foreach ( $limits as $field => $definition ) {
			if ( $this->string_length( (string) $definition[0] ) > (int) $definition[1] ) {
				return new WP_Error( 'inquiry_field_too_long', sprintf( 'The %s field is too long.', $field ), array( 'status' => 400 ) );
			}
		}
		return true;
	}

	protected function clean_text( $value ) {
		return trim( sanitize_text_field( (string) $value ) );
	}

	protected function clean_textarea( $value ) {
		return trim( sanitize_textarea_field( (string) $value ) );
	}

	protected function string_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value, 'UTF-8' ) : strlen( (string) $value );
	}
}
