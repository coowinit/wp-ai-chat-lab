<?php
/**
 * Small, deterministic content cleaning layer for generic WordPress content.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Content_Normalizer {

	/**
	 * Convert raw WordPress content into clean, AI-visible plain text.
	 *
	 * This intentionally does not try to fully render every page builder.
	 * Specialized extractors can be added later for builder-specific data.
	 *
	 * @param mixed $content Raw content.
	 * @return string
	 */
	public function normalize( $content ) {
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return '';
		}

		$text = $content;

		// Remove executable/style blocks before stripping tags so their code does
		// not become visible text.
		$text = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', "\n", $text );

		// Gutenberg block markers are HTML comments. Removing all comments also
		// removes ordinary editorial comments without touching visible content.
		$text = preg_replace( '/<!--.*?-->/s', "\n", $text );

		// Generic shortcodes are not a reliable knowledge source. Structured or
		// builder-specific content belongs in future dedicated extractors.
		$text = strip_shortcodes( $text );
		$text = wp_strip_all_tags( $text, false );

		$charset = get_bloginfo( 'charset' );
		$charset = $charset ? $charset : 'UTF-8';
		$text    = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, $charset );
		$text    = str_replace( "\xC2\xA0", ' ', $text );
		$text    = str_replace( array( "\r\n", "\r" ), "\n", $text );

		// Normalize horizontal whitespace while preserving useful paragraph breaks.
		$text = preg_replace( '/[\t\x0B\f ]+/', ' ', $text );
		$text = preg_replace( '/ *\n */', "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		$text = trim( $text );

		/**
		 * Filter normalized content before it is placed in a Knowledge Source.
		 *
		 * @param string $text    Normalized text.
		 * @param string $content Original raw content.
		 */
		$filtered = apply_filters( 'wpaic_normalized_content', $text, $content );

		return is_string( $filtered ) ? trim( $filtered ) : $text;
	}
}
