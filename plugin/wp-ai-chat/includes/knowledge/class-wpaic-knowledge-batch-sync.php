<?php
/**
 * Visible AJAX batch full-sync coordinator for the Knowledge Store.
 *
 * Stage 3 intentionally keeps orchestration simple: build the current eligible
 * WordPress ID set, process it in small batches, then reconcile active Store
 * rows that are no longer part of that set. No Cron or background queue is
 * introduced here.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Knowledge_Batch_Sync {

	const STATE_PREFIX = 'wpaic_full_sync_';
	const LOCK_KEY     = 'wpaic_full_sync_lock';
	const STATE_TTL    = 7200;
	const LOCK_TTL     = 600;

	/** @var WPAIC_Source_Discovery */
	protected $discovery;

	/** @var WPAIC_Knowledge_Store_Repository */
	protected $repository;

	/** @var WPAIC_Knowledge_Lifecycle_Manager */
	protected $lifecycle;

	public function __construct( WPAIC_Source_Discovery $discovery, WPAIC_Knowledge_Store_Repository $repository, WPAIC_Knowledge_Lifecycle_Manager $lifecycle ) {
		$this->discovery  = $discovery;
		$this->repository = $repository;
		$this->lifecycle  = $lifecycle;
	}

	/**
	 * Start a new full sync, or resume a still-valid interrupted run.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function start() {
		$existing_token = (string) get_transient( self::LOCK_KEY );
		if ( '' !== $existing_token ) {
			$existing = $this->get_state( $existing_token );
			if ( is_array( $existing ) ) {
				set_transient( self::LOCK_KEY, $existing_token, self::LOCK_TTL );
				$response            = $this->response_from_state( $existing );
				$response['resumed'] = true;
				return $response;
			}
			delete_transient( self::LOCK_KEY );
		}

		$token = wp_generate_password( 32, false, false );
		$queue = $this->get_current_eligible_ids();
		$now   = gmdate( 'Y-m-d H:i:s' );

		$state = array(
			'token'             => $token,
			'phase'             => 'sync',
			'queue'             => $queue,
			'offset'            => 0,
			'reconcile_queue'   => array(),
			'reconcile_offset'  => 0,
			'stats'             => $this->empty_stats(),
			'errors'            => array(),
			'started_at'        => $now,
			'updated_at'        => $now,
			'completed_at'      => '',
		);

		set_transient( self::LOCK_KEY, $token, self::LOCK_TTL );
		$this->save_state( $state );

		// An empty eligible set still needs reconciliation so stale active rows do
		// not remain usable forever.
		if ( empty( $queue ) ) {
			$state = $this->prepare_reconciliation( $state );
			if ( empty( $state['reconcile_queue'] ) ) {
				return $this->finalize( $state );
			}
		}

		return $this->response_from_state( $state );
	}

	/**
	 * Process one visible AJAX batch.
	 *
	 * @param string $token Sync token.
	 * @return array<string,mixed>|WP_Error
	 */
	public function run_batch( $token ) {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
		if ( '' === $token ) {
			return new WP_Error( 'wpaic_full_sync_missing_token', 'Full Sync 缺少有效 token。' );
		}

		$state = $this->get_state( $token );
		if ( ! is_array( $state ) ) {
			return new WP_Error( 'wpaic_full_sync_expired', 'Full Sync 状态已过期，请重新开始同步。' );
		}

		$lock = (string) get_transient( self::LOCK_KEY );
		if ( '' !== $lock && ! hash_equals( $lock, $token ) ) {
			return new WP_Error( 'wpaic_full_sync_conflict', '另一个 Full Sync 正在运行，请稍后重试。' );
		}
		set_transient( self::LOCK_KEY, $token, self::LOCK_TTL );

		$batch_size = (int) apply_filters( 'wpaic_full_sync_batch_size', 20 );
		$batch_size = max( 5, min( 50, $batch_size ) );

		if ( 'sync' === $state['phase'] ) {
			$state = $this->process_sync_batch( $state, $batch_size );

			if ( $state['offset'] >= count( $state['queue'] ) ) {
				$state = $this->prepare_reconciliation( $state );
				if ( empty( $state['reconcile_queue'] ) ) {
					return $this->finalize( $state );
				}
			}
		} elseif ( 'reconcile' === $state['phase'] ) {
			$state = $this->process_reconcile_batch( $state, $batch_size );
			if ( $state['reconcile_offset'] >= count( $state['reconcile_queue'] ) ) {
				return $this->finalize( $state );
			}
		} elseif ( 'done' === $state['phase'] ) {
			return $this->response_from_state( $state );
		} else {
			return new WP_Error( 'wpaic_full_sync_invalid_phase', 'Full Sync 状态无效，请重新开始。' );
		}

		$state['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$this->save_state( $state );

		return $this->response_from_state( $state );
	}

	/**
	 * Build the current eligible WordPress object ID set.
	 *
	 * @return array<int,int>
	 */
	protected function get_current_eligible_ids() {
		$available = $this->discovery->get_post_types();
		$enabled   = get_option( WPAIC_OPTION_KNOWLEDGE_SOURCES, array() );
		$enabled   = is_array( $enabled ) ? array_values( array_unique( array_map( 'sanitize_key', $enabled ) ) ) : array();

		$post_types = array();
		foreach ( $enabled as $post_type ) {
			if ( isset( $available[ $post_type ] ) ) {
				$post_types[] = $post_type;
			}
		}
		$post_types[] = WPAIC_Manual_Knowledge::POST_TYPE;
		$post_types   = array_values( array_unique( array_filter( $post_types ) ) );

		if ( empty( $post_types ) ) {
			return array();
		}

		$ids = get_posts(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			)
		);

		$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		sort( $ids, SORT_NUMERIC );

		return $ids;
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @param int                 $batch_size Batch size.
	 * @return array<string,mixed>
	 */
	protected function process_sync_batch( array $state, $batch_size ) {
		$items = array_slice( $state['queue'], (int) $state['offset'], $batch_size );
		foreach ( $items as $post_id ) {
			$result = $this->lifecycle->sync_post( $post_id );
			$state  = $this->record_result( $state, $result, $post_id );
			$state['offset']++;
		}
		return $state;
	}

	/**
	 * Build a queue of currently active Store rows whose object IDs were not in
	 * the eligible set captured at Full Sync start. Those rows need lifecycle
	 * re-evaluation so Draft / Deleted / Disabled / Missing sources are softened
	 * to inactive.
	 *
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>
	 */
	protected function prepare_reconciliation( array $state ) {
		$eligible_lookup = array_fill_keys( array_map( 'absint', $state['queue'] ), true );
		$active_rows     = $this->repository->list_status_rows( 'active' );
		$reconcile       = array();

		foreach ( $active_rows as $row ) {
			$object_id = isset( $row['object_id'] ) ? absint( $row['object_id'] ) : 0;
			if ( $object_id && ! isset( $eligible_lookup[ $object_id ] ) ) {
				$reconcile[] = $object_id;
			}
		}

		$reconcile = array_values( array_unique( $reconcile ) );
		sort( $reconcile, SORT_NUMERIC );

		$state['phase']            = 'reconcile';
		$state['reconcile_queue']  = $reconcile;
		$state['reconcile_offset'] = 0;
		$state['updated_at']       = gmdate( 'Y-m-d H:i:s' );
		$this->save_state( $state );

		return $state;
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @param int                 $batch_size Batch size.
	 * @return array<string,mixed>
	 */
	protected function process_reconcile_batch( array $state, $batch_size ) {
		$items = array_slice( $state['reconcile_queue'], (int) $state['reconcile_offset'], $batch_size );
		foreach ( $items as $post_id ) {
			$result = $this->lifecycle->sync_post( $post_id );
			$state  = $this->record_result( $state, $result, $post_id );
			$state['reconcile_offset']++;
		}
		return $state;
	}

	/**
	 * @param array<string,mixed>       $state State.
	 * @param array<string,mixed>|WP_Error $result Sync result.
	 * @param int                       $post_id Post ID.
	 * @return array<string,mixed>
	 */
	protected function record_result( array $state, $result, $post_id ) {
		$state['stats']['processed']++;

		if ( is_wp_error( $result ) ) {
			$state['stats']['errors']++;
			if ( count( $state['errors'] ) < 5 ) {
				$state['errors'][] = array(
					'post_id' => absint( $post_id ),
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				);
			}
			return $state;
		}

		$action = isset( $result['action'] ) ? sanitize_key( $result['action'] ) : '';
		if ( isset( $state['stats'][ $action ] ) ) {
			$state['stats'][ $action ]++;
		}

		return $state;
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>
	 */
	protected function finalize( array $state ) {
		$state['phase']        = 'done';
		$state['completed_at'] = gmdate( 'Y-m-d H:i:s' );
		$state['updated_at']   = $state['completed_at'];

		$last_sync = array(
			'completed_at' => $state['completed_at'],
			'started_at'   => $state['started_at'],
			'stats'        => $state['stats'],
			'sources'      => count( $state['queue'] ),
			'reconciled'   => count( $state['reconcile_queue'] ),
		);
		update_option( WPAIC_OPTION_LAST_FULL_SYNC, $last_sync, false );

		$response = $this->response_from_state( $state );
		delete_transient( self::STATE_PREFIX . $state['token'] );
		delete_transient( self::LOCK_KEY );

		return $response;
	}

	/**
	 * @return array<string,int>
	 */
	protected function empty_stats() {
		return array(
			'processed'   => 0,
			'created'     => 0,
			'updated'     => 0,
			'unchanged'   => 0,
			'reactivated' => 0,
			'deactivated' => 0,
			'errors'      => 0,
		);
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>
	 */
	protected function response_from_state( array $state ) {
		$phase          = isset( $state['phase'] ) ? sanitize_key( $state['phase'] ) : 'sync';
		$sync_total     = isset( $state['queue'] ) && is_array( $state['queue'] ) ? count( $state['queue'] ) : 0;
		$sync_processed = min( isset( $state['offset'] ) ? absint( $state['offset'] ) : 0, $sync_total );
		$rec_total      = isset( $state['reconcile_queue'] ) && is_array( $state['reconcile_queue'] ) ? count( $state['reconcile_queue'] ) : 0;
		$rec_processed  = min( isset( $state['reconcile_offset'] ) ? absint( $state['reconcile_offset'] ) : 0, $rec_total );

		if ( 'reconcile' === $phase ) {
			$phase_total     = $rec_total;
			$phase_processed = $rec_processed;
		} elseif ( 'done' === $phase ) {
			$phase_total     = $sync_total + $rec_total;
			$phase_processed = $phase_total;
		} else {
			$phase_total     = $sync_total;
			$phase_processed = $sync_processed;
		}

		$percent = $phase_total > 0 ? (int) floor( ( $phase_processed / $phase_total ) * 100 ) : 100;

		return array(
			'token'             => isset( $state['token'] ) ? (string) $state['token'] : '',
			'phase'             => $phase,
			'done'              => 'done' === $phase,
			'sync_total'        => $sync_total,
			'sync_processed'    => $sync_processed,
			'reconcile_total'   => $rec_total,
			'reconcile_processed' => $rec_processed,
			'phase_total'       => $phase_total,
			'phase_processed'   => $phase_processed,
			'percent'           => max( 0, min( 100, $percent ) ),
			'stats'             => isset( $state['stats'] ) && is_array( $state['stats'] ) ? $state['stats'] : $this->empty_stats(),
			'errors'            => isset( $state['errors'] ) && is_array( $state['errors'] ) ? $state['errors'] : array(),
			'started_at'        => isset( $state['started_at'] ) ? (string) $state['started_at'] : '',
			'completed_at'      => isset( $state['completed_at'] ) ? (string) $state['completed_at'] : '',
		);
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @return void
	 */
	protected function save_state( array $state ) {
		set_transient( self::STATE_PREFIX . $state['token'], $state, self::STATE_TTL );
	}

	/**
	 * @param string $token Token.
	 * @return array<string,mixed>|null
	 */
	protected function get_state( $token ) {
		$state = get_transient( self::STATE_PREFIX . $token );
		return is_array( $state ) ? $state : null;
	}
}
