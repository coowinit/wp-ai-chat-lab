<?php
/**
 * Normalize source-derived structured field values.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Structured_Value_Normalizer {

	/** @var WPAIC_Content_Normalizer */
	protected $content_normalizer;

	/**
	 * @param WPAIC_Content_Normalizer $content_normalizer Shared content normalizer.
	 */
	public function __construct( WPAIC_Content_Normalizer $content_normalizer ) {
		$this->content_normalizer = $content_normalizer;
	}

	/**
	 * Normalize one field value.
	 *
	 * v0.3.1 first code slice formally validates text fields only. textarea and
	 * editor support are included because they can reuse the existing deterministic
	 * Content Normalizer without field guessing or AI calls.
	 *
	 * @param mixed  $value Stored post meta value.
	 * @param string $type  Structured field type.
	 * @return string|null
	 */
	public function normalize( $value, $type ) {
		$type = sanitize_key( $type );

		if ( is_array( $value ) || is_object( $value ) || null === $value ) {
			return null;
		}

		$value = (string) $value;
		if ( '' === trim( $value ) ) {
			return null;
		}

		switch ( $type ) {
			case 'textarea':
			case 'editor':
				$value = $this->content_normalizer->normalize( $value );
				break;

			case 'text':
				$value = trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ) ) );
				break;

			default:
				return null;
		}

		return '' === $value ? null : $value;
	}
}
