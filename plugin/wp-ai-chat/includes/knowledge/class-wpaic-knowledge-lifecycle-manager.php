<?php
/**
 * v0.4.0 lifecycle orchestrator. Stage 1 implements only active single-source
 * Create / Unchanged / Update so persistence can be validated independently.
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
	 * Persist one currently eligible WordPress source.
	 *
	 * Stage 1 deliberately does not deactivate draft/trash/deleted/disabled
	 * rows. Those transitions are added and tested in Stage 2.
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
		if ( ! $post ) {
			return new WP_Error( 'wpaic_store_source_not_found', '未找到对应的 WordPress Source。Stage 1 尚不处理删除生命周期。' );
		}

		$eligibility = $this->check_stage1_eligibility( $post );
		if ( is_wp_error( $eligibility ) ) {
			return $eligibility;
		}

		$source = $this->extractor->extract( $post_id, false );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$source_id = isset( $source['source_id'] ) ? (string) $source['source_id'] : '';
		$new_hash  = isset( $source['source_hash'] ) ? (string) $source['source_hash'] : '';

		if ( '' === $source_id || '' === $new_hash ) {
			return new WP_Error( 'wpaic_store_invalid_source', 'Unified Knowledge Source 缺少 source_id 或 source_hash。' );
		}

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
		} elseif ( hash_equals( $old_hash, $new_hash ) && 'active' === $current['store_status'] ) {
			$result = $this->repository->update(
				$source_id,
				array(
					'source_status'     => isset( $source['status'] ) ? sanitize_key( $source['status'] ) : '',
					'source_updated_at' => isset( $source['updated_at'] ) && '' !== $source['updated_at'] ? (string) $source['updated_at'] : null,
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

		return array(
			'action'          => $action,
			'source_id'       => $source_id,
			'object_id'       => $post_id,
			'old_hash'        => $old_hash,
			'new_hash'        => $new_hash,
			'store_status'    => is_array( $row ) && isset( $row['store_status'] ) ? $row['store_status'] : 'active',
			'inactive_reason' => is_array( $row ) && isset( $row['inactive_reason'] ) ? $row['inactive_reason'] : '',
			'elapsed_ms'      => (int) round( ( microtime( true ) - $started ) * 1000 ),
			'row'             => $row,
		);
	}

	/**
	 * @param WP_Post $post Source post.
	 * @return true|WP_Error
	 */
	protected function check_stage1_eligibility( WP_Post $post ) {
		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'wpaic_store_source_not_published', 'Stage 1 只同步已发布 Source。Draft / Trash 生命周期将在 Stage 2 验证。' );
		}

		if ( WPAIC_Manual_Knowledge::POST_TYPE === $post->post_type ) {
			return true;
		}

		if ( ! $this->discovery->is_discoverable( $post->post_type ) ) {
			return new WP_Error( 'wpaic_store_source_not_discoverable', '该内容类型不是可用的 Generic Knowledge Source。' );
		}

		$enabled = get_option( WPAIC_OPTION_KNOWLEDGE_SOURCES, array() );
		$enabled = is_array( $enabled ) ? array_values( array_unique( array_map( 'sanitize_key', $enabled ) ) ) : array();

		if ( ! in_array( $post->post_type, $enabled, true ) ) {
			return new WP_Error( 'wpaic_store_source_disabled', '该内容类型尚未启用为 AI 知识来源。' );
		}

		return true;
	}
}
