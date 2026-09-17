<?php
/**
 * v0.4.0 Knowledge Store lifecycle orchestrator.
 *
 * Stage 1 established Create / Unchanged / Update persistence.
 * Stage 2 adds eligibility-driven Deactivate / Reactivate while preserving the
 * existing AI-visible snapshot for inactive rows.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Knowledge_Lifecycle_Manager {

	/** @var WPAIC_Generic_Extractor */
	protected $extractor;

	/** @var WPAIC_Source_Discovery */
	protected $discovery;

	/** @var WPAIC_Knowledge_Store_Repository */
	protected $repository;

	public function __construct( WPAIC_Generic_Extractor $extractor, WPAIC_Source_Discovery $discovery, WPAIC_Knowledge_Store_Repository $repository ) {
		$this->extractor  = $extractor;
		$this->discovery  = $discovery;
		$this->repository = $repository;
	}

	/**
	 * Sync one WordPress object into the Knowledge Store.
	 *
	 * Stage 2 treats content-change and lifecycle eligibility as independent
	 * dimensions:
	 * - source_hash decides whether the AI-visible snapshot changed;
	 * - publish / enabled / exists decides whether the row may stay active.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function sync_post( $post_id ) {
		$started = microtime( true );
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return new WP_Error( 'wpaic_store_missing_post_id', '请输入有效的 WordPress 内容 ID。' );
		}

		$post = get_post( $post_id );

		// A permanently deleted / missing source can still deactivate an existing
		// Store snapshot because object_id is stable in the derived read model.
		if ( ! $post ) {
			$current = $this->repository->find_by_object_id( $post_id );
			if ( ! $current ) {
				return new WP_Error( 'wpaic_store_source_not_found', '未找到对应的 WordPress Source，Knowledge Store 中也没有可停用的历史 Snapshot。' );
			}

			return $this->deactivate_row( $current, 'source_deleted', 'deleted', $started );
		}

		$source_id = $this->source_id_for_post( $post );
		$current   = $this->repository->find_by_source_id( $source_id );
		if ( ! $current ) {
			$current = $this->repository->find_by_object_id( $post_id );
		}
		$state = $this->get_eligibility_state( $post );

		if ( ! $state['eligible'] ) {
			if ( ! $current ) {
				return new WP_Error(
					'wpaic_store_no_snapshot_to_deactivate',
					'当前 Source 不满足正式 Knowledge 条件，且 Store 中没有可停用的历史 Snapshot。'
				);
			}

			return $this->deactivate_row( $current, $state['reason'], $post->post_status, $started, $post );
		}

		$source = $this->extractor->extract( $post_id, false );
		if ( is_wp_error( $source ) ) {
			$this->mark_error( $current );
			return $source;
		}

		$source_id = isset( $source['source_id'] ) ? (string) $source['source_id'] : '';
		$new_hash  = isset( $source['source_hash'] ) ? (string) $source['source_hash'] : '';

		if ( '' === $source_id || '' === $new_hash ) {
			$this->mark_error( $current );
			return new WP_Error( 'wpaic_store_invalid_source', 'Unified Knowledge Source 缺少 source_id 或 source_hash。' );
		}

		// Re-resolve using the extractor-provided ID in case a developer filter
		// intentionally customizes source_id.
		$current  = $this->repository->find_by_source_id( $source_id );
		$old_hash = is_array( $current ) && isset( $current['source_hash'] ) ? (string) $current['source_hash'] : '';
		$now      = gmdate( 'Y-m-d H:i:s' );

		if ( ! $current ) {
			$fields               = $this->repository->snapshot_fields( $source, $now, 'created' );
			$fields['created_at'] = $now;
			$result               = $this->repository->insert( $fields );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$action = 'created';
		} elseif ( 'inactive' === $current['store_status'] ) {
			$fields = $this->repository->snapshot_fields( $source, $now, 'reactivated' );
			$result = $this->repository->update( $source_id, $fields );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$action = 'reactivated';
		} elseif ( hash_equals( $old_hash, $new_hash ) ) {
			$result = $this->repository->update(
				$source_id,
				array(
					'source_status'     => isset( $source['status'] ) ? sanitize_key( $source['status'] ) : '',
					'source_updated_at' => isset( $source['updated_at'] ) && '' !== $source['updated_at'] ? (string) $source['updated_at'] : null,
					'store_status'      => 'active',
					'inactive_reason'   => '',
					'last_action'       => 'unchanged',
					'last_checked_at'   => $now,
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$action = 'unchanged';
		} else {
			$fields = $this->repository->snapshot_fields( $source, $now, 'updated' );
			$result = $this->repository->update( $source_id, $fields );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$action = 'updated';
		}

		$row = $this->repository->find_by_source_id( $source_id );

		return $this->result_payload( $action, $source_id, $post_id, $old_hash, $new_hash, $row, $started );
	}

	/**
	 * Determine whether a source is currently eligible for active retrieval.
	 *
	 * @param WP_Post $post Source post.
	 * @return array{eligible:bool,reason:string}
	 */
	protected function get_eligibility_state( WP_Post $post ) {
		if ( 'revision' === $post->post_type || 'auto-draft' === $post->post_status ) {
			return array(
				'eligible' => false,
				'reason'   => 'source_missing',
			);
		}

		if ( 'publish' !== $post->post_status ) {
			return array(
				'eligible' => false,
				'reason'   => 'not_published',
			);
		}

		if ( WPAIC_Manual_Knowledge::POST_TYPE === $post->post_type ) {
			return array(
				'eligible' => true,
				'reason'   => '',
			);
		}

		if ( ! $this->discovery->is_discoverable( $post->post_type ) ) {
			return array(
				'eligible' => false,
				'reason'   => 'source_missing',
			);
		}

		$enabled = get_option( WPAIC_OPTION_KNOWLEDGE_SOURCES, array() );
		$enabled = is_array( $enabled ) ? array_values( array_unique( array_map( 'sanitize_key', $enabled ) ) ) : array();

		if ( ! in_array( $post->post_type, $enabled, true ) ) {
			return array(
				'eligible' => false,
				'reason'   => 'source_disabled',
			);
		}

		return array(
			'eligible' => true,
			'reason'   => '',
		);
	}

	/**
	 * Soft-deactivate an existing row while preserving the last good snapshot.
	 *
	 * @param array<string,mixed> $current Existing Store row.
	 * @param string              $reason Inactive reason.
	 * @param string              $source_status Current source status.
	 * @param float               $started Request start time.
	 * @param WP_Post|null        $post Optional current post.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function deactivate_row( array $current, $reason, $source_status, $started, $post = null ) {
		$source_id = isset( $current['source_id'] ) ? (string) $current['source_id'] : '';
		$old_hash  = isset( $current['source_hash'] ) ? (string) $current['source_hash'] : '';
		$now       = gmdate( 'Y-m-d H:i:s' );

		if ( '' === $source_id ) {
			return new WP_Error( 'wpaic_store_missing_source_id', 'Knowledge Store Row 缺少 source_id，无法执行生命周期停用。' );
		}

		$data = array(
			'source_status'   => sanitize_key( $source_status ),
			'store_status'    => 'inactive',
			'inactive_reason' => sanitize_key( $reason ),
			'last_action'     => 'deactivated',
			'last_checked_at' => $now,
			'updated_at'      => $now,
		);

		if ( $post instanceof WP_Post ) {
			$modified = $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified;
			$data['source_updated_at'] = '' !== $modified ? $modified : null;
		}

		$result = $this->repository->update( $source_id, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$row = $this->repository->find_by_source_id( $source_id );

		return $this->result_payload(
			'deactivated',
			$source_id,
			isset( $current['object_id'] ) ? absint( $current['object_id'] ) : 0,
			$old_hash,
			$old_hash,
			$row,
			$started
		);
	}

	/**
	 * Preserve the last good snapshot but surface that the most recent sync hit
	 * an extraction/validation error.
	 *
	 * @param array<string,mixed>|null $current Existing row.
	 * @return void
	 */
	protected function mark_error( $current ) {
		if ( ! is_array( $current ) || empty( $current['source_id'] ) ) {
			return;
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		$this->repository->update(
			$current['source_id'],
			array(
				'last_action'     => 'error',
				'last_checked_at' => $now,
				'updated_at'      => $now,
			)
		);
	}

	/**
	 * Stable default source ID for lifecycle lookups before extraction.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	protected function source_id_for_post( WP_Post $post ) {
		return ( WPAIC_Manual_Knowledge::POST_TYPE === $post->post_type ? 'manual_' : 'wordpress_post_' ) . absint( $post->ID );
	}

	/**
	 * @param string                   $action Action.
	 * @param string                   $source_id Source ID.
	 * @param int                      $object_id Object ID.
	 * @param string                   $old_hash Old hash.
	 * @param string                   $new_hash New hash.
	 * @param array<string,mixed>|null $row Persisted row.
	 * @param float                    $started Request start time.
	 * @return array<string,mixed>
	 */
	protected function result_payload( $action, $source_id, $object_id, $old_hash, $new_hash, $row, $started ) {
		return array(
			'action'          => sanitize_key( $action ),
			'source_id'       => (string) $source_id,
			'object_id'       => absint( $object_id ),
			'old_hash'        => (string) $old_hash,
			'new_hash'        => (string) $new_hash,
			'store_status'    => is_array( $row ) && isset( $row['store_status'] ) ? $row['store_status'] : '',
			'inactive_reason' => is_array( $row ) && isset( $row['inactive_reason'] ) ? $row['inactive_reason'] : '',
			'elapsed_ms'      => (int) round( ( microtime( true ) - $started ) * 1000 ),
			'row'             => $row,
		);
	}
}
