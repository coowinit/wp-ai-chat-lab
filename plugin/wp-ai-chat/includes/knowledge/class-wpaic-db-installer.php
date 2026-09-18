<?php
/**
 * Knowledge Store schema installer / upgrader.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_DB_Installer {

	/**
	 * Return the Knowledge Store table name for the current site.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wpaic_knowledge_store';
	}

	/**
	 * Return the Usage Counter table name for the current site.
	 *
	 * @return string
	 */
	public static function get_usage_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wpaic_usage_counter';
	}

	/** Return the Inquiry table name for the current site. @return string */
	public static function get_inquiry_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wpaic_inquiries';
	}

	/**
	 * Create or upgrade the Knowledge Store schema.
	 *
	 * dbDelta() makes this operation idempotent so both activation and normal
	 * coverage upgrades can call the same installer safely.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_id varchar(191) NOT NULL,
			source_type varchar(50) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_type varchar(50) NOT NULL DEFAULT '',
			knowledge_type varchar(100) NOT NULL DEFAULT '',
			title text NOT NULL,
			excerpt longtext NOT NULL,
			url text NOT NULL,
			taxonomies longtext NOT NULL,
			structured_data longtext NOT NULL,
			content longtext NOT NULL,
			source_hash char(64) NOT NULL DEFAULT '',
			source_status varchar(20) NOT NULL DEFAULT '',
			store_status varchar(20) NOT NULL DEFAULT 'active',
			inactive_reason varchar(50) NOT NULL DEFAULT '',
			source_updated_at datetime NULL,
			last_action varchar(20) NOT NULL DEFAULT '',
			last_checked_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_id (source_id),
			KEY object_ref (source_type,object_id),
			KEY post_type_status (post_type,store_status),
			KEY source_hash (source_hash)
		) {$charset_collate};";

		dbDelta( $sql );

		$usage_table = self::get_usage_table_name();
		$usage_sql = "CREATE TABLE {$usage_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scope_type varchar(32) NOT NULL,
			scope_key_hash char(64) NOT NULL,
			period_key varchar(32) NOT NULL,
			provider_calls bigint(20) unsigned NOT NULL DEFAULT 0,
			prompt_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			completion_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			total_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			last_called_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY scope_period (scope_type,scope_key_hash,period_key),
			KEY scope_type_period (scope_type,period_key)
		) {$charset_collate};";

		dbDelta( $usage_sql );

		$inquiry_table = self::get_inquiry_table_name();
		$inquiry_sql = "CREATE TABLE {$inquiry_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'unread',
			trashed_at datetime NULL,
			name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(80) NOT NULL DEFAULT '',
			company varchar(191) NOT NULL DEFAULT '',
			message longtext NOT NULL,
			trigger_type varchar(32) NOT NULL DEFAULT '',
			conversation_id char(36) NOT NULL DEFAULT '',
			source_url text NOT NULL,
			last_question text NOT NULL,
			visitor_hash char(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY status_created (status,created_at),
			KEY trashed_at (trashed_at),
			KEY visitor_created (visitor_hash,created_at),
			KEY trigger_type (trigger_type)
		) {$charset_collate};";

		dbDelta( $inquiry_sql );

		// v0.9.0 Stage 2 Round 2: simplify operational state to unread/read
		// and keep trash as an independent soft-delete lifecycle. These
		// migrations are idempotent so active-site zip upgrades remain safe.
		$wpdb->query( "UPDATE {$inquiry_table} SET status = 'unread' WHERE status = 'new'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "UPDATE {$inquiry_table} SET status = 'read' WHERE status IN ('contacted','closed')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "UPDATE {$inquiry_table} SET status = 'read', trashed_at = COALESCE(trashed_at, updated_at) WHERE status = 'spam'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery

		$exists         = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		$usage_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $usage_table ) );
		$inquiry_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $inquiry_table ) );
		if ( $table_name === $exists && $usage_table === $usage_exists && $inquiry_table === $inquiry_exists ) {
			update_option( WPAIC_OPTION_DB_VERSION, WPAIC_DB_VERSION, false );
		}
	}

	/**
	 * Upgrade schema when a plugin zip is copied over an already-active build.
	 * This deliberately does not depend on a deactivate/reactivate cycle.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$current = (string) get_option( WPAIC_OPTION_DB_VERSION, '' );

		if ( '' === $current || version_compare( $current, WPAIC_DB_VERSION, '<' ) ) {
			self::install();
		}
	}
}
