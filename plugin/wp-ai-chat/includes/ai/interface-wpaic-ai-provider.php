<?php
/**
 * AI provider contract.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface WPAIC_AI_Provider_Interface {
	/**
	 * Provider machine id.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human readable provider name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Supported model ids exposed by this plugin version.
	 *
	 * @return array<string,string>
	 */
	public function get_models();

	/**
	 * Whether the provider has the minimum required configuration.
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Run a minimal connection test.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function test_connection();

	/**
	 * Send a non-streaming chat request.
	 *
	 * @param array<int,array<string,mixed>> $messages Chat messages.
	 * @param array<string,mixed>            $options  Supported provider options.
	 * @return array<string,mixed>|WP_Error
	 */
	public function chat( array $messages, array $options = array() );
}
