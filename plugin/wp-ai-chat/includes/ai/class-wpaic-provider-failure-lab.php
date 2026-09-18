<?php
/**
 * Admin-armed, one-shot Provider failure injection for permanent Lab validation.
 *
 * The Lab never changes provider credentials or transport settings. Instead it
 * short-circuits the AI Manager only after Grounding and Usage reservation have
 * allowed the request to cross the Provider boundary. The arm is scoped to the
 * current anonymous Visitor hash, expires quickly, and is consumed only when an
 * actual AI Manager call occurs.
 *
 * @package WP_AI_Chat_Lab
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Provider_Failure_Lab {
	const TTL          = 180;
	const MODE_TIMEOUT = 'transport_timeout';

	/** @var string */
	protected $active_transient_key = '';

	/** @var bool */
	protected $filter_attached = false;

	/** Arm one one-shot failure for the current Visitor. @return array<string,mixed>|WP_Error */
	public function arm( WPAIC_Chat_Context $context, $mode = self::MODE_TIMEOUT ) {
		if ( ! $context->has_existing_visitor() ) {
			return new WP_Error( 'wpaic_provider_failure_lab_missing_visitor', '请先在同一浏览器打开一次前台 Chat，让服务器建立 Visitor Cookie，再武装 Provider Failure Lab。' );
		}

		$mode = sanitize_key( (string) $mode );
		if ( self::MODE_TIMEOUT !== $mode ) {
			return new WP_Error( 'wpaic_provider_failure_lab_invalid_mode', 'Unsupported Provider Failure Lab mode.' );
		}

		$key = $this->transient_key( $context );
		if ( '' === $key ) {
			return new WP_Error( 'wpaic_provider_failure_lab_missing_visitor', 'Provider Failure Lab requires an existing Visitor context.' );
		}

		$now = time();
		set_transient(
			$key,
			array(
				'mode'       => $mode,
				'armed_at'   => $now,
				'expires_at' => $now + self::TTL,
			),
			self::TTL
		);

		return $this->get_status( $context );
	}

	/** Clear any armed failure for the current Visitor. @return array<string,mixed> */
	public function clear( WPAIC_Chat_Context $context ) {
		$key = $this->transient_key( $context );
		if ( '' !== $key ) {
			delete_transient( $key );
		}
		return $this->get_status( $context );
	}

	/** @return array<string,mixed> */
	public function get_status( WPAIC_Chat_Context $context ) {
		$key   = $this->transient_key( $context );
		$value = '' !== $key ? get_transient( $key ) : false;
		$value = is_array( $value ) ? $value : array();
		$mode  = isset( $value['mode'] ) ? sanitize_key( (string) $value['mode'] ) : '';
		$expires_at = isset( $value['expires_at'] ) ? (int) $value['expires_at'] : 0;
		return array(
			'armed'      => self::MODE_TIMEOUT === $mode,
			'mode'       => self::MODE_TIMEOUT === $mode ? $mode : '',
			'ttl'        => self::TTL,
			'expires_in' => $expires_at > 0 ? max( 0, $expires_at - time() ) : 0,
		);
	}

	/**
	 * Attach the one-shot injector for this Public Chat request when armed.
	 * The transient is not consumed here. Weak / Medium / None / Usage BLOCK
	 * paths never call AI Manager, so the arm remains available for the next
	 * request that truly reaches the Provider boundary.
	 *
	 * @return bool Whether an injector was attached.
	 */
	public function attach_for_request( WPAIC_Chat_Context $context ) {
		$this->detach();
		$status = $this->get_status( $context );
		if ( empty( $status['armed'] ) ) {
			return false;
		}

		$this->active_transient_key = $this->transient_key( $context );
		add_filter( 'wpaic_ai_manager_pre_chat_result', array( $this, 'inject_once' ), 10, 3 );
		$this->filter_attached = true;
		return true;
	}

	/** Detach request-scoped injection filter. @return void */
	public function detach() {
		if ( $this->filter_attached ) {
			remove_filter( 'wpaic_ai_manager_pre_chat_result', array( $this, 'inject_once' ), 10 );
		}
		$this->filter_attached     = false;
		$this->active_transient_key = '';
	}

	/**
	 * AI Manager pre-chat filter. Consumed only when an actual Provider-bound
	 * call reaches AI Manager after Usage Guard reservation.
	 *
	 * @param mixed $pre Existing short-circuit value.
	 * @param array<int,array<string,mixed>> $messages Messages (unused).
	 * @param array<string,mixed> $options Options (unused).
	 * @return mixed|WP_Error
	 */
	public function inject_once( $pre, $messages = array(), $options = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( is_wp_error( $pre ) ) {
			return $pre;
		}
		if ( '' === $this->active_transient_key ) {
			return $pre;
		}

		$value = get_transient( $this->active_transient_key );
		$value = is_array( $value ) ? $value : array();
		$mode  = isset( $value['mode'] ) ? sanitize_key( (string) $value['mode'] ) : '';
		if ( self::MODE_TIMEOUT !== $mode ) {
			return $pre;
		}

		delete_transient( $this->active_transient_key );
		return new WP_Error(
			'wpaic_lab_provider_timeout',
			'Simulated Provider transport timeout for Lab validation.',
			array(
				'status_code'  => 504,
				'lab_simulated' => true,
				'mode'          => self::MODE_TIMEOUT,
			)
		);
	}

	/** @return string */
	protected function transient_key( WPAIC_Chat_Context $context ) {
		$hash = $context->get_visitor_identity_hash();
		if ( '' === $hash ) {
			return '';
		}
		return 'wpaic_pfl_' . substr( preg_replace( '/[^a-f0-9]/i', '', $hash ), 0, 40 );
	}
}
