<?php
/** Best-effort public Inquiry submission rate guard. @package WP_AI_Chat_Lab */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Inquiry_Rate_Guard {
	const LIMIT  = 5;
	const WINDOW = HOUR_IN_SECONDS;

	/** @return array<string,mixed> */
	public function consume( WPAIC_Inquiry_Context $context ) {
		$hash = $context->get_visitor_identity_hash();
		if ( '' === $hash ) {
			return array( 'allowed' => false, 'retry_after' => self::WINDOW, 'used' => 0, 'limit' => self::LIMIT );
		}

		$key  = $this->transient_key( $hash );
		$now  = time();
		$data = get_transient( $key );
		$data = is_array( $data ) ? $data : array();
		$expires_at = isset( $data['expires_at'] ) ? (int) $data['expires_at'] : 0;
		$count      = isset( $data['count'] ) ? (int) $data['count'] : 0;

		if ( $expires_at <= $now ) {
			$expires_at = $now + self::WINDOW;
			$count      = 0;
		}

		if ( $count >= self::LIMIT ) {
			return array(
				'allowed'     => false,
				'retry_after' => max( 1, $expires_at - $now ),
				'used'        => $count,
				'limit'       => self::LIMIT,
			);
		}

		$count++;
		set_transient(
			$key,
			array( 'count' => $count, 'expires_at' => $expires_at ),
			max( 1, $expires_at - $now )
		);

		return array(
			'allowed'     => true,
			'retry_after' => 0,
			'used'        => $count,
			'limit'       => self::LIMIT,
		);
	}

	/** @return array<string,int> */
	public function get_state( WPAIC_Inquiry_Context $context ) {
		$hash = $context->get_visitor_identity_hash();
		if ( '' === $hash ) { return array( 'used' => 0, 'limit' => self::LIMIT, 'retry_after' => 0 ); }
		$data = get_transient( $this->transient_key( $hash ) );
		$data = is_array( $data ) ? $data : array();
		$now  = time();
		$expires_at = isset( $data['expires_at'] ) ? (int) $data['expires_at'] : 0;
		if ( $expires_at <= $now ) { return array( 'used' => 0, 'limit' => self::LIMIT, 'retry_after' => 0 ); }
		return array(
			'used'        => isset( $data['count'] ) ? (int) $data['count'] : 0,
			'limit'       => self::LIMIT,
			'retry_after' => max( 0, $expires_at - $now ),
		);
	}

	public function reset( WPAIC_Inquiry_Context $context ) {
		$hash = $context->get_visitor_identity_hash();
		if ( '' === $hash ) { return false; }
		return delete_transient( $this->transient_key( $hash ) );
	}

	protected function transient_key( $hash ) {
		return 'wpaic_inquiry_rate_' . substr( (string) $hash, 0, 40 );
	}
}
