<?php
/** Usage counter persistence. @package WP_AI_Chat_Lab */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Usage_Counter_Repository {
	/** @return string */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wpaic_usage_counter';
	}

	/** @return string */
	public function get_period_key( $scope ) {
		if ( 'conversation' === $scope ) { return 'lifetime'; }
		return current_time( 'Y-m-d' );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_counter( $scope, $scope_key_hash ) {
		global $wpdb;
		$table  = self::get_table_name();
		$period = $this->get_period_key( $scope );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT provider_calls,prompt_tokens,completion_tokens,total_tokens,last_called_at FROM {$table} WHERE scope_type=%s AND scope_key_hash=%s AND period_key=%s LIMIT 1",
				$scope,
				$scope_key_hash,
				$period
			),
			ARRAY_A
		);
		return array(
			'scope'             => $scope,
			'period_key'        => $period,
			'provider_calls'    => $row ? (int) $row['provider_calls'] : 0,
			'prompt_tokens'     => $row ? (int) $row['prompt_tokens'] : 0,
			'completion_tokens' => $row ? (int) $row['completion_tokens'] : 0,
			'total_tokens'      => $row ? (int) $row['total_tokens'] : 0,
			'last_called_at'    => $row ? (string) $row['last_called_at'] : '',
		);
	}

	/**
	 * Reserve one provider call atomically across enabled scopes.
	 * Stage 1 exposes this only through the admin simulation tool; Stage 2 will
	 * place it immediately before the real Provider boundary.
	 *
	 * @param WPAIC_Usage_Context $context Context.
	 * @param array<string,int>   $limits Limits.
	 * @return array<string,mixed>|WP_Error
	 */
	public function reserve_provider_call( WPAIC_Usage_Context $context, array $limits ) {
		global $wpdb;
		$table = self::get_table_name();
		$enabled = array();
		foreach ( array( 'conversation', 'visitor', 'site' ) as $scope ) {
			$limit = isset( $limits[ $scope ] ) ? max( 0, (int) $limits[ $scope ] ) : 0;
			if ( $limit <= 0 ) { continue; }
			$hash = $context->get_hash( $scope );
			if ( '' === $hash ) {
				return new WP_Error( 'missing_usage_context', '已启用的 Usage Scope 缺少必要 Context。', array( 'scope' => $scope ) );
			}
			$enabled[] = array( 'scope' => $scope, 'limit' => $limit, 'hash' => $hash, 'period' => $this->get_period_key( $scope ) );
		}

		if ( empty( $enabled ) ) {
			return array( 'reserved' => true, 'reason_code' => 'within_limits', 'scopes' => array() );
		}

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		try {
			$now = current_time( 'mysql' );
			foreach ( $enabled as $item ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$table} (scope_type,scope_key_hash,period_key,provider_calls,prompt_tokens,completion_tokens,total_tokens,last_called_at,created_at,updated_at) VALUES (%s,%s,%s,0,0,0,0,NULL,%s,%s) ON DUPLICATE KEY UPDATE id=id",
						$item['scope'], $item['hash'], $item['period'], $now, $now
					)
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}

			$states = array();
			foreach ( $enabled as $item ) {
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id,provider_calls,prompt_tokens,completion_tokens,total_tokens,last_called_at FROM {$table} WHERE scope_type=%s AND scope_key_hash=%s AND period_key=%s FOR UPDATE",
						$item['scope'], $item['hash'], $item['period']
					), ARRAY_A
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$used = $row ? (int) $row['provider_calls'] : 0;
				if ( $used >= $item['limit'] ) {
					$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					return array(
						'reserved'     => false,
						'reason_code'  => $item['scope'] . ( 'conversation' === $item['scope'] ? '_limit_reached' : '_daily_limit_reached' ),
						'blocked_scope'=> $item['scope'],
						'scopes'        => $this->get_scope_states( $context, $limits ),
					);
				}
				$states[ $item['scope'] ] = $row;
			}

			foreach ( $enabled as $item ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET provider_calls=provider_calls+1,last_called_at=%s,updated_at=%s WHERE scope_type=%s AND scope_key_hash=%s AND period_key=%s",
						$now, $now, $item['scope'], $item['hash'], $item['period']
					)
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return array( 'reserved' => true, 'reason_code' => 'within_limits', 'blocked_scope' => '', 'scopes' => $this->get_scope_states( $context, $limits ) );
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new WP_Error( 'usage_guard_unavailable', $e->getMessage() );
		}
	}

	/**
	 * Add real Provider token usage to every enabled scope that was reserved.
	 * Provider Call count is reserved before the external request; this method
	 * runs only after a successful Provider response with trusted usage data.
	 *
	 * @param WPAIC_Usage_Context $context Usage context.
	 * @param array<string,int>   $limits  Current policy limits.
	 * @param array<string,mixed> $usage   Provider usage payload.
	 * @return array<string,array<string,mixed>>|WP_Error
	 */
	public function add_token_usage( WPAIC_Usage_Context $context, array $limits, array $usage ) {
		global $wpdb;
		$table = self::get_table_name();
		$prompt_tokens     = isset( $usage['prompt_tokens'] ) ? max( 0, (int) $usage['prompt_tokens'] ) : 0;
		$completion_tokens = isset( $usage['completion_tokens'] ) ? max( 0, (int) $usage['completion_tokens'] ) : 0;
		$total_tokens      = isset( $usage['total_tokens'] ) ? max( 0, (int) $usage['total_tokens'] ) : ( $prompt_tokens + $completion_tokens );

		$enabled = array();
		foreach ( array( 'conversation', 'visitor', 'site' ) as $scope ) {
			$limit = isset( $limits[ $scope ] ) ? max( 0, (int) $limits[ $scope ] ) : 0;
			if ( $limit <= 0 ) { continue; }
			$hash = $context->get_hash( $scope );
			if ( '' === $hash ) {
				return new WP_Error( 'missing_usage_context', '已启用的 Usage Scope 缺少必要 Context。', array( 'scope' => $scope ) );
			}
			$enabled[] = array( 'scope' => $scope, 'hash' => $hash, 'period' => $this->get_period_key( $scope ) );
		}

		if ( empty( $enabled ) || 0 === $total_tokens ) {
			return $this->get_scope_states( $context, $limits );
		}

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		try {
			$now = current_time( 'mysql' );
			foreach ( $enabled as $item ) {
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET prompt_tokens=prompt_tokens+%d,completion_tokens=completion_tokens+%d,total_tokens=total_tokens+%d,updated_at=%s WHERE scope_type=%s AND scope_key_hash=%s AND period_key=%s",
						$prompt_tokens, $completion_tokens, $total_tokens, $now, $item['scope'], $item['hash'], $item['period']
					)
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( false === $updated || 0 === $updated ) {
					throw new RuntimeException( 'Usage token accounting row is missing for scope: ' . $item['scope'] );
				}
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new WP_Error( 'usage_guard_unavailable', $e->getMessage() );
		}

		return $this->get_scope_states( $context, $limits );
	}

	/** @return array<string,array<string,mixed>> */
	public function get_scope_states( WPAIC_Usage_Context $context, array $limits ) {
		$out = array();
		foreach ( array( 'conversation', 'visitor', 'site' ) as $scope ) {
			$limit = isset( $limits[ $scope ] ) ? max( 0, (int) $limits[ $scope ] ) : 0;
			$hash = $context->get_hash( $scope );
			$counter = '' !== $hash ? $this->get_counter( $scope, $hash ) : array( 'provider_calls' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'period_key' => $this->get_period_key( $scope ), 'last_called_at' => '' );
			$used = (int) $counter['provider_calls'];
			$out[ $scope ] = array_merge( $counter, array(
				'enabled'   => $limit > 0,
				'limit'     => $limit,
				'used'      => $used,
				'remaining' => $limit > 0 ? max( 0, $limit - $used ) : null,
				'has_key'   => '' !== $context->get_key( $scope ),
				'key_hash'  => $hash,
			) );
		}
		return $out;
	}
}
