<?php
/**
 * Best-effort public request rate limiting for v0.8.0 Stage 3.
 *
 * This guard protects the public REST boundary itself. It is intentionally
 * separate from Usage Guard, which remains the precise Provider-call quota.
 * Counters use short-lived WordPress transients and store only hashed
 * identities. High-assurance production rate limiting still belongs at the
 * edge (for example Cloudflare/WAF).
 *
 * @package WP_AI_Chat_Lab
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Public_Request_Guard {
	const WINDOW_SECONDS        = 60;
	const DEFAULT_VISITOR_LIMIT = 20;
	const DEFAULT_IP_LIMIT      = 0;

	/** @return array<string,int> */
	public function get_settings() {
		$settings = get_option( WPAIC_OPTION_PUBLIC_GUARD_SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();
		return array(
			'enabled'            => ! empty( $settings['enabled'] ) ? 1 : 0,
			'visitor_per_minute' => isset( $settings['visitor_per_minute'] ) ? max( 0, (int) $settings['visitor_per_minute'] ) : self::DEFAULT_VISITOR_LIMIT,
			'ip_per_minute'      => isset( $settings['ip_per_minute'] ) ? max( 0, (int) $settings['ip_per_minute'] ) : self::DEFAULT_IP_LIMIT,
		);
	}

	/**
	 * Consume one public request allowance.
	 *
	 * @return array<string,mixed>
	 */
	public function consume( WPAIC_Chat_Context $context ) {
		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return array( 'allowed' => true, 'reason_code' => 'disabled', 'retry_after' => 0 );
		}

		$window     = $this->window_id();
		$retry_after = $this->retry_after();
		$targets    = array();

		if ( $settings['visitor_per_minute'] > 0 ) {
			$targets[] = array(
				'scope' => 'visitor',
				'key'   => $this->visitor_transient_key( $context, $window ),
				'limit' => $settings['visitor_per_minute'],
			);
		}

		if ( $settings['ip_per_minute'] > 0 ) {
			$ip_hash = $this->current_ip_hash();
			if ( '' !== $ip_hash ) {
				$targets[] = array(
					'scope' => 'ip',
					'key'   => $this->transient_key( 'ip', $ip_hash, $window ),
					'limit' => $settings['ip_per_minute'],
				);
			}
		}

		foreach ( $targets as $target ) {
			$current = (int) get_transient( $target['key'] );
			if ( $current >= (int) $target['limit'] ) {
				return array(
					'allowed'     => false,
					'reason_code' => 'public_' . $target['scope'] . '_rate_limit_reached',
					'blocked_scope' => $target['scope'],
					'retry_after' => $retry_after,
				);
			}
		}

		// Best-effort application guard. Transients are deliberately separate
		// from billing/Provider quotas, so exact atomicity is not required here.
		foreach ( $targets as $target ) {
			$current = (int) get_transient( $target['key'] );
			set_transient( $target['key'], $current + 1, self::WINDOW_SECONDS + 5 );
		}

		return array( 'allowed' => true, 'reason_code' => 'within_rate_limit', 'retry_after' => 0 );
	}

	/** Reset current-browser / current-IP buckets for the active minute. */
	public function reset_current_buckets( WPAIC_Chat_Context $context ) {
		$window  = $this->window_id();
		$deleted = array( 'visitor' => false, 'ip' => false );
		$visitor_key = $this->visitor_transient_key( $context, $window );
		if ( '' !== $visitor_key ) {
			$deleted['visitor'] = delete_transient( $visitor_key );
		}
		$ip_hash = $this->current_ip_hash();
		if ( '' !== $ip_hash ) {
			$deleted['ip'] = delete_transient( $this->transient_key( 'ip', $ip_hash, $window ) );
		}
		return $deleted;
	}

	/** @return array<string,mixed> */
	public function get_current_snapshot( WPAIC_Chat_Context $context ) {
		$settings = $this->get_settings();
		$window   = $this->window_id();
		$visitor_key = $this->visitor_transient_key( $context, $window );
		$ip_hash = $this->current_ip_hash();
		return array(
			'enabled'            => ! empty( $settings['enabled'] ),
			'window_seconds'     => self::WINDOW_SECONDS,
			'retry_after'        => $this->retry_after(),
			'visitor_limit'      => $settings['visitor_per_minute'],
			'visitor_used'       => '' !== $visitor_key ? (int) get_transient( $visitor_key ) : 0,
			'ip_limit'           => $settings['ip_per_minute'],
			'ip_used'            => '' !== $ip_hash ? (int) get_transient( $this->transient_key( 'ip', $ip_hash, $window ) ) : 0,
			'visitor_cookie'     => $context->has_existing_visitor() ? 'present' : 'missing/new',
		);
	}

	protected function visitor_transient_key( WPAIC_Chat_Context $context, $window ) {
		$hash = $context->get_visitor_identity_hash();
		return '' === $hash ? '' : $this->transient_key( 'visitor', $hash, $window );
	}

	protected function transient_key( $scope, $hash, $window ) {
		return 'wpaic_prg_' . sanitize_key( $scope ) . '_' . substr( preg_replace( '/[^a-f0-9]/i', '', (string) $hash ), 0, 32 ) . '_' . absint( $window );
	}

	protected function current_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
		if ( '' === $ip ) { return ''; }
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	protected function window_id() {
		return (int) floor( time() / self::WINDOW_SECONDS );
	}

	protected function retry_after() {
		$remaining = self::WINDOW_SECONDS - ( time() % self::WINDOW_SECONDS );
		return max( 1, (int) $remaining );
	}
}
