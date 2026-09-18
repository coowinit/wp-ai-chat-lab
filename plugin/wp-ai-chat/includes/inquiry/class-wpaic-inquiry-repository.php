<?php
/** Inquiry persistence repository. @package WP_AI_Chat_Lab */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Inquiry_Repository {
	const STATUS_UNREAD = 'unread';
	const STATUS_READ   = 'read';

	const VIEW_ALL      = 'all';
	const VIEW_UNREAD   = 'unread';
	const VIEW_READ     = 'read';
	const VIEW_TRASH    = 'trash';

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wpaic_inquiries';
	}

	/** @return array<int,string> */
	public static function get_allowed_statuses() {
		return array( self::STATUS_UNREAD, self::STATUS_READ );
	}

	/** @return array<int,string> */
	public static function get_allowed_views() {
		return array( self::VIEW_ALL, self::VIEW_UNREAD, self::VIEW_READ, self::VIEW_TRASH );
	}

	/** @return array<int,string> */
	public static function get_allowed_triggers() {
		return array( 'manual', 'commercial_intent', 'no_answer', 'usage_blocked' );
	}

	/** @return array<string,mixed> */
	public function get_operational_status() {
		global $wpdb;
		$table  = self::get_table_name();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array(
			'table'        => $table,
			'table_exists' => $table === $exists,
			'row_count'    => $table === $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 0, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		);
	}

	/** @return int|WP_Error */
	public function insert( array $row ) {
		global $wpdb;
		$table = self::get_table_name();
		$result = $wpdb->insert(
			$table,
			array(
				'created_at'      => (string) $row['created_at'],
				'updated_at'      => (string) $row['updated_at'],
				'status'          => (string) $row['status'],
				'name'            => (string) $row['name'],
				'email'           => (string) $row['email'],
				'phone'           => (string) $row['phone'],
				'company'         => (string) $row['company'],
				'message'         => (string) $row['message'],
				'trigger_type'    => (string) $row['trigger_type'],
				'conversation_id' => (string) $row['conversation_id'],
				'source_url'      => (string) $row['source_url'],
				'last_question'   => (string) $row['last_question'],
				'visitor_hash'    => (string) $row['visitor_hash'],
			),
			array( '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $result || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'inquiry_persistence_failed', 'Inquiry could not be saved.' );
		}
		return (int) $wpdb->insert_id;
	}

	/** @return array<int,array<string,mixed>> */
	public function list_paged( $page = 1, $per_page = 10, $view = self::VIEW_ALL, $trigger = '', $search = '' ) {
		global $wpdb;
		$table    = self::get_table_name();
		$page     = max( 1, absint( $page ) );
		$per_page = min( 100, max( 1, absint( $per_page ) ) );
		$view     = $this->normalize_view( $view );
		$trigger  = $this->normalize_trigger( $trigger );
		$search   = sanitize_text_field( (string) $search );
		$offset   = ( $page - 1 ) * $per_page;
		$exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $table !== $exists ) { return array(); }

		$params = array();
		$where  = $this->build_filter_where_sql( $view, $trigger, $search, $params );
		$sql    = "SELECT id,created_at,updated_at,status,trashed_at,name,email,phone,company,message,trigger_type,conversation_id,source_url,last_question,visitor_hash FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $params ),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<int,array<string,mixed>> */
	public function list_recent( $limit = 10 ) {
		global $wpdb;
		$table = self::get_table_name();
		$limit = min( 100, max( 1, absint( $limit ) ) );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $table !== $exists ) { return array(); }
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,created_at,updated_at,status,trashed_at,name,email,phone,company,message,trigger_type,conversation_id,source_url,last_question,visitor_hash FROM {$table} WHERE trashed_at IS NULL ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<string,mixed>|null */
	public function get_by_id( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		$id    = absint( $id );
		if ( $id < 1 ) { return null; }
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $table !== $exists ) { return null; }
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id,created_at,updated_at,status,trashed_at,name,email,phone,company,message,trigger_type,conversation_id,source_url,last_question,visitor_hash FROM {$table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return is_array( $row ) ? $row : null;
	}

	/** @return array<string,int> */
	public function count_by_view() {
		global $wpdb;
		$table  = self::get_table_name();
		$counts = array( 'all' => 0, 'unread' => 0, 'read' => 0, 'trash' => 0 );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $table !== $exists ) { return $counts; }

		$row = $wpdb->get_row(
			"SELECT
				SUM(CASE WHEN trashed_at IS NULL THEN 1 ELSE 0 END) AS active_total,
				SUM(CASE WHEN trashed_at IS NULL AND status = 'unread' THEN 1 ELSE 0 END) AS unread_total,
				SUM(CASE WHEN trashed_at IS NULL AND status = 'read' THEN 1 ELSE 0 END) AS read_total,
				SUM(CASE WHEN trashed_at IS NOT NULL THEN 1 ELSE 0 END) AS trash_total
			FROM {$table}",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery

		if ( is_array( $row ) ) {
			$counts['all']    = isset( $row['active_total'] ) ? (int) $row['active_total'] : 0;
			$counts['unread'] = isset( $row['unread_total'] ) ? (int) $row['unread_total'] : 0;
			$counts['read']   = isset( $row['read_total'] ) ? (int) $row['read_total'] : 0;
			$counts['trash']  = isset( $row['trash_total'] ) ? (int) $row['trash_total'] : 0;
		}
		return $counts;
	}

	/** @return int */
	public function count_for_view( $view = self::VIEW_ALL ) {
		$counts = $this->count_by_view();
		$view   = $this->normalize_view( $view );
		return isset( $counts[ $view ] ) ? (int) $counts[ $view ] : (int) $counts['all'];
	}

	/** @return int */
	public function count_filtered( $view = self::VIEW_ALL, $trigger = '', $search = '' ) {
		global $wpdb;
		$table   = self::get_table_name();
		$exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $table !== $exists ) { return 0; }
		$params  = array();
		$where   = $this->build_filter_where_sql( $this->normalize_view( $view ), $this->normalize_trigger( $trigger ), sanitize_text_field( (string) $search ), $params );
		$sql     = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		$value   = empty( $params ) ? $wpdb->get_var( $sql ) : $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) $value;
	}

	/** Mark an active inquiry read. @return true|WP_Error */
	public function mark_read( $id ) {
		return $this->set_single_status( $id, self::STATUS_READ );
	}

	/** Mark an active inquiry unread. @return true|WP_Error */
	public function mark_unread( $id ) {
		return $this->set_single_status( $id, self::STATUS_UNREAD );
	}

	/** @return int|WP_Error Number of updated rows. */
	public function bulk_set_status( array $ids, $status ) {
		global $wpdb;
		$status = sanitize_key( (string) $status );
		if ( ! in_array( $status, self::get_allowed_statuses(), true ) ) {
			return new WP_Error( 'inquiry_invalid_status', 'Invalid inquiry status.' );
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return new WP_Error( 'inquiry_no_selection', 'No inquiries selected.' );
		}
		$table = self::get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$params = array_merge( array( $status, current_time( 'mysql' ) ), $ids );
		$sql = "UPDATE {$table} SET status = %s, updated_at = %s WHERE trashed_at IS NULL AND id IN ({$placeholders})";
		$result = $wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( false === $result || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'inquiry_bulk_status_failed', 'Selected inquiries could not be updated.' );
		}
		return (int) $result;
	}

	/** Soft-delete one inquiry. @return true|WP_Error */
	public function move_to_trash( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( $id < 1 ) { return new WP_Error( 'inquiry_invalid_id', 'Invalid inquiry ID.' ); }
		$table = self::get_table_name();
		$now   = current_time( 'mysql' );
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET trashed_at = %s, updated_at = %s WHERE id = %d AND trashed_at IS NULL",
				$now,
				$now,
				$id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( false === $result || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'inquiry_trash_failed', 'Inquiry could not be moved to trash.' );
		}
		if ( 0 === $result && null === $this->get_by_id( $id ) ) {
			return new WP_Error( 'inquiry_not_found', 'Inquiry not found.' );
		}
		return true;
	}

	/** Restore one inquiry from trash. @return true|WP_Error */
	public function restore( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( $id < 1 ) { return new WP_Error( 'inquiry_invalid_id', 'Invalid inquiry ID.' ); }
		$table = self::get_table_name();
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET trashed_at = NULL, updated_at = %s WHERE id = %d AND trashed_at IS NOT NULL",
				current_time( 'mysql' ),
				$id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( false === $result || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'inquiry_restore_failed', 'Inquiry could not be restored.' );
		}
		if ( 0 === $result && null === $this->get_by_id( $id ) ) {
			return new WP_Error( 'inquiry_not_found', 'Inquiry not found.' );
		}
		return true;
	}

	/** Permanently delete one already-trashed inquiry. @return true|WP_Error */
	public function delete_permanently( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( $id < 1 ) { return new WP_Error( 'inquiry_invalid_id', 'Invalid inquiry ID.' ); }
		$table = self::get_table_name();
		$result = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE id = %d AND trashed_at IS NOT NULL", $id )
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( false === $result || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'inquiry_delete_failed', 'Inquiry could not be deleted.' );
		}
		if ( 0 === $result ) {
			return new WP_Error( 'inquiry_delete_not_allowed', 'Only inquiries in trash can be permanently deleted.' );
		}
		return true;
	}

	/** @return int|WP_Error Number of deleted rows. */
	public function empty_trash() {
		global $wpdb;
		$table = self::get_table_name();
		$result = $wpdb->query( "DELETE FROM {$table} WHERE trashed_at IS NOT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( false === $result || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'inquiry_empty_trash_failed', 'Inquiry trash could not be emptied.' );
		}
		return (int) $result;
	}

	/** @return true|WP_Error */
	protected function set_single_status( $id, $status ) {
		global $wpdb;
		$id = absint( $id );
		if ( $id < 1 ) { return new WP_Error( 'inquiry_invalid_id', 'Invalid inquiry ID.' ); }
		if ( ! in_array( $status, self::get_allowed_statuses(), true ) ) {
			return new WP_Error( 'inquiry_invalid_status', 'Invalid inquiry status.' );
		}
		$table = self::get_table_name();
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d AND trashed_at IS NULL AND status <> %s",
				$status,
				current_time( 'mysql' ),
				$id,
				$status
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( false === $result || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'inquiry_status_failed', 'Inquiry status could not be updated.' );
		}
		if ( 0 === $result && null === $this->get_by_id( $id ) ) {
			return new WP_Error( 'inquiry_not_found', 'Inquiry not found.' );
		}
		return true;
	}

	protected function normalize_view( $view ) {
		$view = sanitize_key( (string) $view );
		return in_array( $view, self::get_allowed_views(), true ) ? $view : self::VIEW_ALL;
	}

	protected function normalize_trigger( $trigger ) {
		$trigger = sanitize_key( (string) $trigger );
		return in_array( $trigger, self::get_allowed_triggers(), true ) ? $trigger : '';
	}

	protected function get_view_where_sql( $view ) {
		switch ( $view ) {
			case self::VIEW_UNREAD:
				return "trashed_at IS NULL AND status = 'unread'";
			case self::VIEW_READ:
				return "trashed_at IS NULL AND status = 'read'";
			case self::VIEW_TRASH:
				return 'trashed_at IS NOT NULL';
			case self::VIEW_ALL:
			default:
				return 'trashed_at IS NULL';
		}
	}

	/** Build safe filter SQL; dynamic values are returned through $params. */
	protected function build_filter_where_sql( $view, $trigger, $search, array &$params ) {
		global $wpdb;
		$where = $this->get_view_where_sql( $view );
		if ( '' !== $trigger ) {
			$where   .= ' AND trigger_type = %s';
			$params[] = $trigger;
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (name LIKE %s OR email LIKE %s OR company LIKE %s OR message LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		return $where;
	}
}
