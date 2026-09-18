<?php
/** Inquiry persistence repository. @package WP_AI_Chat_Lab */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPAIC_Inquiry_Repository {
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wpaic_inquiries';
	}

	/** @return array<string,mixed> */
	public function get_operational_status() {
		global $wpdb;
		$table = self::get_table_name();
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
	public function list_recent( $limit = 10 ) {
		global $wpdb;
		$table = self::get_table_name();
		$limit = min( 50, max( 1, absint( $limit ) ) );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $table !== $exists ) { return array(); }
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,created_at,status,name,email,phone,company,message,trigger_type,conversation_id,source_url,last_question,visitor_hash FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return is_array( $rows ) ? $rows : array();
	}
}
