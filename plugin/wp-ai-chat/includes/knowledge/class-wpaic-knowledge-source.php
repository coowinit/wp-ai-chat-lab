<?php
/**
 * Unified Knowledge Source shape used by v0.3.0.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Knowledge_Source {

	/**
	 * Normalize a source array and calculate a stable SHA-256 hash from the
	 * content that AI will actually be allowed to see.
	 *
	 * @param array<string,mixed> $data Source data.
	 * @return array<string,mixed>
	 */
	public static function prepare( array $data ) {
		$source = wp_parse_args(
			$data,
			array(
				'source_id'       => '',
				'source_type'     => 'wordpress_post',
				'object_id'       => 0,
				'post_type'       => '',
				'knowledge_type'  => '',
				'title'           => '',
				'excerpt'         => '',
				'url'             => '',
				'taxonomies'      => array(),
				'structured_data' => array(),
				'content'         => '',
				'source_hash'     => '',
				'updated_at'      => '',
				'status'          => '',
			)
		);

		$source['source_id']       = (string) $source['source_id'];
		$source['source_type']     = sanitize_key( $source['source_type'] );
		$source['object_id']       = (int) $source['object_id'];
		$source['post_type']       = sanitize_key( $source['post_type'] );
		$source['knowledge_type']  = sanitize_key( $source['knowledge_type'] ? $source['knowledge_type'] : $source['post_type'] );
		$source['title']           = (string) $source['title'];
		$source['excerpt']         = (string) $source['excerpt'];
		$source['url']             = (string) $source['url'];
		$source['taxonomies']      = is_array( $source['taxonomies'] ) ? $source['taxonomies'] : array();
		$source['structured_data'] = is_array( $source['structured_data'] ) ? $source['structured_data'] : array();
		$source['content']         = (string) $source['content'];
		$source['updated_at']      = (string) $source['updated_at'];
		$source['status']          = sanitize_key( $source['status'] );
		$source['source_hash']     = self::calculate_hash( $source );

		return $source;
	}

	/**
	 * @param array<string,mixed> $source Source array.
	 * @return string
	 */
	public static function calculate_hash( array $source ) {
		$payload = array(
			'source_type'     => isset( $source['source_type'] ) ? $source['source_type'] : '',
			'post_type'       => isset( $source['post_type'] ) ? $source['post_type'] : '',
			'knowledge_type'  => isset( $source['knowledge_type'] ) ? $source['knowledge_type'] : '',
			'title'           => isset( $source['title'] ) ? $source['title'] : '',
			'excerpt'         => isset( $source['excerpt'] ) ? $source['excerpt'] : '',
			'url'             => isset( $source['url'] ) ? $source['url'] : '',
			'taxonomies'      => isset( $source['taxonomies'] ) ? $source['taxonomies'] : array(),
			'structured_data' => isset( $source['structured_data'] ) ? $source['structured_data'] : array(),
			'content'         => isset( $source['content'] ) ? $source['content'] : '',
		);

		self::recursive_ksort( $payload );

		return hash( 'sha256', wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Recursively sort associative arrays so identical visible data produces the
	 * same hash even if taxonomy/filter ordering changes.
	 *
	 * @param array<mixed> $array Array passed by reference.
	 * @return void
	 */
	protected static function recursive_ksort( &$array ) {
		if ( ! is_array( $array ) ) {
			return;
		}

		foreach ( $array as &$value ) {
			if ( is_array( $value ) ) {
				self::recursive_ksort( $value );
			}
		}
		unset( $value );

		if ( self::is_associative( $array ) ) {
			ksort( $array );
		}
	}

	/**
	 * @param array<mixed> $array Array.
	 * @return bool
	 */
	protected static function is_associative( array $array ) {
		if ( array() === $array ) {
			return false;
		}

		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}
}
