<?php
/**
 * Normalize local-retrieval questions without damaging model-like tokens.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Retrieval_Query_Normalizer {

	/**
	 * @param string $question Raw question.
	 * @return array<string,mixed>|WP_Error
	 */
	public function normalize( $question ) {
		$question = trim( (string) $question );
		if ( '' === $question ) {
			return new WP_Error( 'wpaic_retrieval_empty_query', '请输入要检索的问题。' );
		}

		$decoded = html_entity_decode( wp_strip_all_tags( $question ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ?: 'UTF-8' );
		$decoded = function_exists( 'mb_strtolower' ) ? mb_strtolower( $decoded, 'UTF-8' ) : strtolower( $decoded );
		$decoded = str_replace( array( "\r\n", "\r", "\n", "\t", "\xc2\xa0" ), ' ', $decoded );

		// Keep letters, numbers, hyphen and underscore so model tokens such as
		// CWC-610 / COC-09 / TW-02 / 3D remain stable.
		$normalized = preg_replace( '/[^\p{L}\p{N}\-_]+/u', ' ', $decoded );
		$normalized = is_string( $normalized ) ? preg_replace( '/\s+/u', ' ', trim( $normalized ) ) : '';
		$normalized = is_string( $normalized ) ? trim( $normalized ) : '';

		if ( '' === $normalized ) {
			return new WP_Error( 'wpaic_retrieval_empty_normalized_query', '问题标准化后没有可用于检索的内容。' );
		}

		$stop_words = array(
			'what', 'is', 'the', 'of', 'a', 'an', 'to', 'for', 'in', 'on', 'and',
		);
		$stop_words = apply_filters( 'wpaic_retrieval_stop_words', $stop_words, $question, $normalized );
		$stop_words = is_array( $stop_words ) ? array_map( 'strval', $stop_words ) : array();
		$stop_words = array_values( array_unique( array_map( 'strtolower', $stop_words ) ) );

		$tokens = preg_split( '/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY );
		$tokens = is_array( $tokens ) ? $tokens : array();
		$terms  = array();

		foreach ( $tokens as $token ) {
			$token = trim( (string) $token );
			if ( '' === $token || in_array( $token, $stop_words, true ) ) {
				continue;
			}
			$terms[] = $token;
		}

		$terms = array_values( array_unique( $terms ) );
		$terms = apply_filters( 'wpaic_retrieval_query_terms', $terms, $question, $normalized );
		$terms = is_array( $terms ) ? $terms : array();

		$clean_terms = array();
		foreach ( $terms as $term ) {
			$term = trim( (string) $term );
			if ( '' !== $term ) {
				$clean_terms[] = $term;
			}
		}
		$clean_terms = array_values( array_unique( $clean_terms ) );

		return array(
			'raw'        => $question,
			'normalized' => $normalized,
			'phrase'     => $normalized,
			'terms'      => $clean_terms,
		);
	}
}
