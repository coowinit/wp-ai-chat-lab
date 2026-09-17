<?php
/** Usage policy evaluation. @package WP_AI_Chat_Lab */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Usage_Guard {
	/** @var WPAIC_Usage_Counter_Repository */
	protected $repository;

	public function __construct( WPAIC_Usage_Counter_Repository $repository ) {
		$this->repository = $repository;
	}

	/** @return array<string,int> */
	public function get_limits() {
		$settings = get_option( WPAIC_OPTION_USAGE_SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();
		return array(
			'conversation' => isset( $settings['conversation_limit'] ) ? max( 0, (int) $settings['conversation_limit'] ) : 10,
			'visitor'      => isset( $settings['visitor_daily_limit'] ) ? max( 0, (int) $settings['visitor_daily_limit'] ) : 20,
			'site'         => isset( $settings['site_daily_limit'] ) ? max( 0, (int) $settings['site_daily_limit'] ) : 200,
		);
	}

	/** @return array<string,mixed> */
	public function evaluate( WPAIC_Usage_Context $context ) {
		$limits = $this->get_limits();
		foreach ( array( 'conversation', 'visitor', 'site' ) as $scope ) {
			if ( $limits[ $scope ] > 0 && '' === $context->get_key( $scope ) ) {
				return array( 'decision' => 'block', 'reason_code' => 'missing_usage_context', 'blocked_scope' => $scope, 'scopes' => $this->repository->get_scope_states( $context, $limits ) );
			}
		}
		$states = $this->repository->get_scope_states( $context, $limits );
		foreach ( array( 'conversation', 'visitor', 'site' ) as $scope ) {
			if ( $states[ $scope ]['enabled'] && $states[ $scope ]['used'] >= $states[ $scope ]['limit'] ) {
				$reason = 'conversation' === $scope ? 'conversation_limit_reached' : $scope . '_daily_limit_reached';
				return array( 'decision' => 'block', 'reason_code' => $reason, 'blocked_scope' => $scope, 'scopes' => $states );
			}
		}
		return array( 'decision' => 'allow', 'reason_code' => 'within_limits', 'blocked_scope' => '', 'scopes' => $states );
	}

	/** @return array<string,mixed>|WP_Error */
	public function reserve( WPAIC_Usage_Context $context ) {
		$preflight = $this->evaluate( $context );
		if ( 'allow' !== $preflight['decision'] ) {
			$preflight['reserved'] = false;
			return $preflight;
		}

		$result = $this->repository->reserve_provider_call( $context, $this->get_limits() );
		if ( is_wp_error( $result ) ) { return $result; }
		$result['decision'] = ! empty( $result['reserved'] ) ? 'allow' : 'block';
		return $result;
	}
}
