<?php
/**
 * Database repository for the derived AI Knowledge Store.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Knowledge_Store_Repository {

	/** @var wpdb */
	protected $wpdb;

	/** @var string */
	protected $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = WPAIC_DB_Installer::get_table_name();
	}

	/**
	 * @return string
	 */
	public function get_table_name() {
		return $this->table;
	}

	/**
	 * @param string $source_id Stable Knowledge Source ID.
	 * @return array<string,mixed>|null
	 */
	public function find_by_source_id( $source_id ) {
		$source_id = trim( (string) $source_id );
		if ( '' === $source_id ) {
			return null;
		}

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE source_id = %s LIMIT 1", $source_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate_row( $row ) : null;
	}


	/**
	 * Find a persisted source by its original WordPress object ID. WordPress
	 * post IDs are globally unique within the site, so this is sufficient for
	 * Stage 2 deleted-source diagnostics.
	 *
	 * @param int $object_id WordPress object ID.
	 * @return array<string,mixed>|null
	 */
	public function find_by_object_id( $object_id ) {
		$object_id = absint( $object_id );
		if ( ! $object_id ) {
			return null;
		}

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE object_id = %d ORDER BY id DESC LIMIT 1", $object_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate_row( $row ) : null;
	}

	/**
	 * @param array<string,mixed> $data Prepared database fields.
	 * @return int|WP_Error Inserted row ID or error.
	 */
	public function insert( array $data ) {
		$data = $this->prepare_write_data( $data );
		$result = $this->wpdb->insert( $this->table, $data );

		if ( false === $result ) {
			return new WP_Error( 'wpaic_store_insert_failed', $this->database_error_message( 'Knowledge Store 写入失败。' ) );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * @param string              $source_id Stable source ID.
	 * @param array<string,mixed> $data      Fields to update.
	 * @return bool|WP_Error
	 */
	public function update( $source_id, array $data ) {
		$source_id = trim( (string) $source_id );
		if ( '' === $source_id ) {
			return new WP_Error( 'wpaic_store_missing_source_id', 'Knowledge Store 更新缺少 source_id。' );
		}

		$data   = $this->prepare_write_data( $data, false );
		$result = $this->wpdb->update( $this->table, $data, array( 'source_id' => $source_id ) );

		if ( false === $result ) {
			return new WP_Error( 'wpaic_store_update_failed', $this->database_error_message( 'Knowledge Store 更新失败。' ) );
		}

		return true;
	}

	/**
	 * @param string $status Optional active/inactive filter.
	 * @return int
	 */
	public function count_by_status( $status = '' ) {
		$status = sanitize_key( $status );

		if ( '' === $status ) {
			return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
		}

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE store_status = %s", $status )
		);
	}

	/**
	 * Return a small diagnostic list only; this is not a management editor.
	 *
	 * @param int $limit Row limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function list_rows( $limit = 10 ) {
		$limit = max( 1, min( 50, absint( $limit ) ) );
		$rows  = $this->wpdb->get_results(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} ORDER BY updated_at DESC, id DESC LIMIT %d", $limit ),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate_row' ), $rows );
	}

	/**
	 * Convert a Unified Knowledge Source into store snapshot columns.
	 *
	 * @param array<string,mixed> $source Unified source.
	 * @param string              $now    UTC mysql datetime.
	 * @param string              $action Lifecycle action.
	 * @return array<string,mixed>
	 */
	public function snapshot_fields( array $source, $now, $action ) {
		return array(
			'source_id'         => isset( $source['source_id'] ) ? (string) $source['source_id'] : '',
			'source_type'       => isset( $source['source_type'] ) ? sanitize_key( $source['source_type'] ) : '',
			'object_id'         => isset( $source['object_id'] ) ? absint( $source['object_id'] ) : 0,
			'post_type'         => isset( $source['post_type'] ) ? sanitize_key( $source['post_type'] ) : '',
			'knowledge_type'    => isset( $source['knowledge_type'] ) ? sanitize_key( $source['knowledge_type'] ) : '',
			'title'             => isset( $source['title'] ) ? (string) $source['title'] : '',
			'excerpt'           => isset( $source['excerpt'] ) ? (string) $source['excerpt'] : '',
			'url'               => isset( $source['url'] ) ? (string) $source['url'] : '',
			'taxonomies'        => isset( $source['taxonomies'] ) && is_array( $source['taxonomies'] ) ? $source['taxonomies'] : array(),
			'structured_data'   => isset( $source['structured_data'] ) && is_array( $source['structured_data'] ) ? $source['structured_data'] : array(),
			'content'           => isset( $source['content'] ) ? (string) $source['content'] : '',
			'source_hash'       => isset( $source['source_hash'] ) ? (string) $source['source_hash'] : '',
			'source_status'     => isset( $source['status'] ) ? sanitize_key( $source['status'] ) : '',
			'store_status'      => 'active',
			'inactive_reason'   => '',
			'source_updated_at' => isset( $source['updated_at'] ) && '' !== $source['updated_at'] ? (string) $source['updated_at'] : null,
			'last_action'       => sanitize_key( $action ),
			'last_checked_at'   => $now,
			'updated_at'        => $now,
		);
	}

	/**
	 * @param array<string,mixed> $data          Fields.
	 * @param bool                $include_create Whether to preserve created_at defaults.
	 * @return array<string,mixed>
	 */
	protected function prepare_write_data( array $data, $include_create = true ) {
		foreach ( array( 'taxonomies', 'structured_data' ) as $json_key ) {
			if ( array_key_exists( $json_key, $data ) && is_array( $data[ $json_key ] ) ) {
				$encoded = wp_json_encode( $data[ $json_key ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				$data[ $json_key ] = false === $encoded ? '{}' : $encoded;
			}
		}

		if ( $include_create && ! array_key_exists( 'created_at', $data ) ) {
			$data['created_at'] = gmdate( 'Y-m-d H:i:s' );
		}

		return $data;
	}

	/**
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	protected function hydrate_row( array $row ) {
		$row['id']        = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$row['object_id'] = isset( $row['object_id'] ) ? (int) $row['object_id'] : 0;

		foreach ( array( 'taxonomies', 'structured_data' ) as $json_key ) {
			$decoded = isset( $row[ $json_key ] ) ? json_decode( (string) $row[ $json_key ], true ) : array();
			$row[ $json_key ] = is_array( $decoded ) ? $decoded : array();
		}

		return $row;
	}

	/**
	 * @param string $fallback Fallback error.
	 * @return string
	 */
	protected function database_error_message( $fallback ) {
		$error = trim( (string) $this->wpdb->last_error );
		return '' !== $error ? $fallback . ' ' . $error : $fallback;
	}
}
