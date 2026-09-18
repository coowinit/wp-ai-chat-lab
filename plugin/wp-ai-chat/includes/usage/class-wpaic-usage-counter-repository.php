<?php
/** Usage counter persistence. @package WP_AI_Chat_Lab */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Usage_Counter_Repository {
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wpaic_usage_counter';
	}

	/** @return array<string,mixed> */
	public function get_operational_status() {
		global $wpdb;
		$table = self::get_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$engine = $row && isset( $row['Engine'] ) ? (string) $row['Engine'] : '';
		return array(
			'table'         => $table,
			'table_exists'  => is_array( $row ),
			'engine'        => $engine,
			'transactional' => 'innodb' === strtolower( $engine ),
			'last_error'    => (string) $wpdb->last_error,
		);
	}

	/** @return true|WP_Error */
	public function assert_operational() {
		$status = $this->get_operational_status();
		if ( empty( $status['table_exists'] ) ) {
			return new WP_Error( 'usage_guard_unavailable', 'Usage Counter table is unavailable.' );
		}
		if ( empty( $status['transactional'] ) ) {
			return new WP_Error( 'usage_guard_unavailable', 'Usage Counter requires InnoDB transactional storage before Public Chat can call an AI Provider.' );
		}
		if ( ! empty( $status['last_error'] ) ) {
			return new WP_Error( 'usage_guard_unavailable', 'Usage Counter database preflight failed.' );
		}
		return true;
	}

	public function get_period_key( $scope ) {
		if ( 'conversation' === $scope ) { return 'lifetime'; }
		return current_time( 'Y-m-d' );
	}

	/** @return array<string,mixed> */
	public function get_counter( $scope, $scope_key_hash ) {
		global $wpdb;
		$table  = self::get_table_name();
		$period = $this->get_period_key( $scope );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT provider_calls,prompt_tokens,completion_tokens,total_tokens,last_called_at FROM {$table} WHERE scope_type=%s AND scope_key_hash=%s AND period_key=%s LIMIT 1",
				$scope, $scope_key_hash, $period
			), ARRAY_A
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

	/** @return array<string,mixed>|WP_Error */
	public function reserve_provider_call( WPAIC_Usage_Context $context, array $limits ) {
		global $wpdb;
		$table   = self::get_table_name();
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
		$operational = $this->assert_operational();
		if ( is_wp_error( $operational ) ) { return $operational; }

		try {
			$this->transaction_query( 'START TRANSACTION', 'Could not start Usage reservation transaction.' );
			$now = current_time( 'mysql' );
			foreach ( $enabled as $item ) {
				$this->transaction_query(
					$wpdb->prepare(
						"INSERT INTO {$table} (scope_type,scope_key_hash,period_key,provider_calls,prompt_tokens,completion_tokens,total_tokens,last_called_at,created_at,updated_at) VALUES (%s,%s,%s,0,0,0,0,NULL,%s,%s) ON DUPLICATE KEY UPDATE id=id",
						$item['scope'], $item['hash'], $item['period'], $now, $now
					),
					'Could not prepare Usage counter row.'
				);
			}

			foreach ( $enabled as $item ) {
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id,provider_calls,prompt_tokens,completion_tokens,total_tokens,last_called_at FROM {$table} WHERE scope_type=%s AND scope_key_hash=%s AND period_key=%s FOR UPDATE",
						$item['scope'], $item['hash'], $item['period']
					), ARRAY_A
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( '' !== (string) $wpdb->last_error || ! is_array( $row ) ) {
					throw new RuntimeException( 'Could not lock Usage counter row for scope: ' . $item['scope'] );
				}
				if ( (int) $row['provider_calls'] >= $item['limit'] ) {
					$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					return array(
						'reserved'      => false,
						'reason_code'   => $item['scope'] . ( 'conversation' === $item['scope'] ? '_limit_reached' : '_daily_limit_reached' ),
						'blocked_scope' => $item['scope'],
						'scopes'        => $this->get_scope_states( $context, $limits ),
					);
				}
			}

			foreach ( $enabled as $item ) {
				$updated = $this->transaction_query(
					$wpdb->prepare(
						"UPDATE {$table} SET provider_calls=provider_calls+1,last_called_at=%s,updated_at=%s WHERE scope_type=%s AND scope_key_hash=%s AND period_key=%s",
						$now, $now, $item['scope'], $item['hash'], $item['period']
					),
					'Could not reserve Usage counter for scope: ' . $item['scope']
				);
				if ( 1 !== (int) $updated ) {
					throw new RuntimeException( 'Usage reservation row disappeared for scope: ' . $item['scope'] );
				}
			}
			$this->transaction_query( 'COMMIT', 'Could not commit Usage reservation.' );
			return array( 'reserved' => true, 'reason_code' => 'within_limits', 'blocked_scope' => '', 'scopes' => $this->get_scope_states( $context, $limits ) );
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new WP_Error( 'usage_guard_unavailable', $e->getMessage() );
		}
	}

	/** @return array<string,array<string,mixed>>|WP_Error */
	public function add_token_usage( WPAIC_Usage_Context $context, array $limits, array $usage ) {
		global $wpdb;
		$table             = self::get_table_name();
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
		$operational = $this->assert_operational();
		if ( is_wp_error( $operational ) ) { return $operational; }

		try {
			$this->transaction_query( 'START TRANSACTION', 'Could not start Usage token transaction.' );
			$now = current_time( 'mysql' );
			foreach ( $enabled as $item ) {
				$updated = $this->transaction_query(
					$wpdb->prepare(
						"UPDATE {$table} SET prompt_tokens=prompt_tokens+%d,completion_tokens=completion_tokens+%d,total_tokens=total_tokens+%d,updated_at=%s WHERE scope_type=%s AND scope_key_hash=%s AND period_key=%s",
						$prompt_tokens, $completion_tokens, $total_tokens, $now, $item['scope'], $item['hash'], $item['period']
					),
					'Usage token accounting failed for scope: ' . $item['scope']
				);
				if ( 1 !== (int) $updated ) {
					throw new RuntimeException( 'Usage token accounting row is missing for scope: ' . $item['scope'] );
				}
			}
			$this->transaction_query( 'COMMIT', 'Could not commit Usage token accounting.' );
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new WP_Error( 'usage_guard_unavailable', $e->getMessage() );
		}
		return $this->get_scope_states( $context, $limits );
	}

	/** @return array<string,mixed>|WP_Error */
	public function reset_context_counters( WPAIC_Usage_Context $context, array $scopes ) {
		global $wpdb;
		$operational = $this->assert_operational();
		if ( is_wp_error( $operational ) ) { return $operational; }
		$table   = self::get_table_name();
		$allowed = array( 'conversation', 'visitor', 'site' );
		$scopes  = array_values( array_unique( array_intersect( $allowed, array_map( 'sanitize_key', $scopes ) ) ) );
		if ( empty( $scopes ) ) { return new WP_Error( 'usage_reset_no_scopes', '请至少选择一个要重置的 Usage Scope。' ); }

		$targets = array();
		foreach ( $scopes as $scope ) {
			$hash = $context->get_hash( $scope );
			if ( '' === $hash ) {
				return new WP_Error( 'missing_usage_context', '重置所选 Usage Scope 时缺少必要 Context。', array( 'scope' => $scope ) );
			}
			$targets[] = array( 'scope' => $scope, 'hash' => $hash, 'period' => $this->get_period_key( $scope ) );
		}

		$deleted = array();
		try {
			$this->transaction_query( 'START TRANSACTION', 'Could not start Usage reset transaction.' );
			foreach ( $targets as $target ) {
				$result = $this->transaction_query(
					$wpdb->prepare(
						"DELETE FROM {$table} WHERE scope_type=%s AND scope_key_hash=%s AND period_key=%s",
						$target['scope'], $target['hash'], $target['period']
					),
					'Usage counter reset failed for scope: ' . $target['scope']
				);
				$deleted[ $target['scope'] ] = (int) $result;
			}
			$this->transaction_query( 'COMMIT', 'Could not commit Usage reset.' );
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new WP_Error( 'usage_guard_unavailable', $e->getMessage() );
		}

		return array(
			'reset'        => true,
			'reset_scopes' => $scopes,
			'deleted_rows' => $deleted,
			'period_keys'  => array_reduce( $targets, function ( $carry, $target ) {
				$carry[ $target['scope'] ] = $target['period'];
				return $carry;
			}, array() ),
		);
	}

	/** @return array<string,array<string,mixed>> */
	public function get_scope_states( WPAIC_Usage_Context $context, array $limits ) {
		$out = array();
		foreach ( array( 'conversation', 'visitor', 'site' ) as $scope ) {
			$limit   = isset( $limits[ $scope ] ) ? max( 0, (int) $limits[ $scope ] ) : 0;
			$hash    = $context->get_hash( $scope );
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

	/** @return int|bool */
	protected function transaction_query( $sql, $message ) {
		global $wpdb;
		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( false === $result || '' !== (string) $wpdb->last_error ) {
			throw new RuntimeException( (string) $message );
		}
		return $result;
	}
}
