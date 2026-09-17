<?php
/**
 * Explainable weighted scoring for Local Retrieval candidates.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Retrieval_Scorer {

	/** @var array<string,int> */
	protected $default_field_weights = array(
		'structured_data' => 8,
		'title'           => 6,
		'taxonomies'      => 4,
		'excerpt'         => 3,
		'content'         => 1,
	);

	/** @var int */
	protected $exact_identifier_boost = 4;

	/** @var int */
	protected $phrase_match_boost = 8;

	/** @var int */
	protected $coverage_bonus_max = 10;

	/**
	 * Score one candidate.
	 *
	 * @param array<string,mixed> $candidate Candidate from the Candidate Searcher.
	 * @param array<string,mixed> $query     Normalized query representation.
	 * @return array<string,mixed>
	 */
	public function score( array $candidate, array $query ) {
		$weights = $this->get_field_weights();
		$terms   = isset( $query['terms'] ) && is_array( $query['terms'] ) ? array_values( $query['terms'] ) : array();
		$terms   = array_values( array_unique( array_filter( array_map( 'strval', $terms ) ) ) );

		$field_values = array(
			'structured_data' => $this->json_search_text( isset( $candidate['structured_data'] ) && is_array( $candidate['structured_data'] ) ? $candidate['structured_data'] : array() ),
			'title'           => isset( $candidate['title'] ) ? (string) $candidate['title'] : '',
			'taxonomies'      => $this->json_search_text( isset( $candidate['taxonomies'] ) && is_array( $candidate['taxonomies'] ) ? $candidate['taxonomies'] : array() ),
			'excerpt'         => isset( $candidate['excerpt'] ) ? (string) $candidate['excerpt'] : '',
			'content'         => isset( $candidate['content'] ) ? (string) $candidate['content'] : '',
		);

		$field_scores       = array();
		$field_term_matches = array();
		$matched_terms      = array();
		$matched_fields     = array();
		$base_score         = 0;

		foreach ( $weights as $field => $weight ) {
			$value = isset( $field_values[ $field ] ) ? $field_values[ $field ] : '';
			if ( '' === trim( (string) $value ) ) {
				continue;
			}

			$field_matches = array();
			foreach ( $terms as $term ) {
				if ( $this->contains( $value, $term ) ) {
					$field_matches[] = $term;
					$matched_terms[] = $term;
				}
			}

			$field_matches = array_values( array_unique( $field_matches ) );
			if ( empty( $field_matches ) ) {
				continue;
			}

			$field_score                  = count( $field_matches ) * $weight;
			$field_scores[ $field ]       = $field_score;
			$field_term_matches[ $field ] = $field_matches;
			$matched_fields[]             = $field;
			$base_score                  += $field_score;
		}

		$matched_terms  = array_values( array_unique( $matched_terms ) );
		$matched_fields = array_values( array_unique( $matched_fields ) );

		$exact_terms = array();
		foreach ( $terms as $term ) {
			if ( ! $this->is_identifier_like( $term ) ) {
				continue;
			}
			if ( $this->has_exact_identifier_match( $candidate, $field_values, $term ) ) {
				$exact_terms[] = $term;
			}
		}
		$exact_terms = array_values( array_unique( $exact_terms ) );
		$exact_score = count( $exact_terms ) * $this->exact_identifier_boost;

		$phrase_fields = $this->find_phrase_fields( $field_values, $query );
		$phrase_score  = empty( $phrase_fields ) ? 0 : $this->phrase_match_boost;

		$total_terms     = count( $terms );
		$matched_count   = count( $matched_terms );
		$coverage_ratio  = $total_terms > 0 ? $matched_count / $total_terms : 0;
		$coverage_score  = $total_terms > 0 ? (int) round( $coverage_ratio * $this->coverage_bonus_max ) : 0;
		$total_score     = $base_score + $exact_score + $phrase_score + $coverage_score;

		$result = $candidate;
		$result['score']          = $total_score;
		$result['matched_terms']  = $matched_terms;
		$result['matched_fields'] = $matched_fields;
		$result['score_breakdown'] = array(
			'field_scores' => $field_scores,
			'field_terms'  => $field_term_matches,
			'exact_match'  => array(
				'terms' => $exact_terms,
				'boost' => $exact_score,
			),
			'phrase_match' => array(
				'fields' => $phrase_fields,
				'boost'  => $phrase_score,
			),
			'coverage' => array(
				'matched' => $matched_count,
				'total'   => $total_terms,
				'ratio'   => $coverage_ratio,
				'bonus'   => $coverage_score,
			),
			'base_score'  => $base_score,
			'total_score' => $total_score,
		);

		return $result;
	}

	/**
	 * @return array<string,int>
	 */
	public function get_field_weights() {
		$weights = apply_filters( 'wpaic_retrieval_field_weights', $this->default_field_weights );
		$weights = is_array( $weights ) ? $weights : $this->default_field_weights;

		$clean = array();
		foreach ( $this->default_field_weights as $field => $default_weight ) {
			$weight = isset( $weights[ $field ] ) ? absint( $weights[ $field ] ) : $default_weight;
			$clean[ $field ] = max( 0, min( 100, $weight ) );
		}
		return $clean;
	}

	/** @return int */
	public function get_exact_identifier_boost() {
		return $this->exact_identifier_boost;
	}

	/** @return int */
	public function get_phrase_match_boost() {
		return $this->phrase_match_boost;
	}

	/** @return int */
	public function get_coverage_bonus_max() {
		return $this->coverage_bonus_max;
	}

	/**
	 * @param array<string,string> $field_values Searchable field values.
	 * @param array<string,mixed>  $query        Query representation.
	 * @return array<int,string>
	 */
	protected function find_phrase_fields( array $field_values, array $query ) {
		$phrases = array();
		$phrase  = isset( $query['phrase'] ) ? trim( (string) $query['phrase'] ) : '';
		if ( '' !== $phrase && false !== strpos( $phrase, ' ' ) ) {
			$phrases[] = $phrase;
		}

		$terms = isset( $query['terms'] ) && is_array( $query['terms'] ) ? array_values( $query['terms'] ) : array();
		if ( count( $terms ) > 1 ) {
			$term_phrase = trim( implode( ' ', array_map( 'strval', $terms ) ) );
			if ( '' !== $term_phrase ) {
				$phrases[] = $term_phrase;
			}
		}
		$phrases = array_values( array_unique( $phrases ) );

		if ( empty( $phrases ) ) {
			return array();
		}

		$matched = array();
		foreach ( $field_values as $field => $value ) {
			foreach ( $phrases as $candidate_phrase ) {
				if ( $this->contains( $value, $candidate_phrase ) ) {
					$matched[] = $field;
					break;
				}
			}
		}
		return array_values( array_unique( $matched ) );
	}

	/**
	 * Stronger exact-match boost is intentionally limited to identifier-like
	 * tokens so common words such as "order" do not receive hidden priority.
	 *
	 * @param string $term Query term.
	 * @return bool
	 */
	protected function is_identifier_like( $term ) {
		$term = (string) $term;
		return 1 === preg_match( '/[\d\-_]/u', $term );
	}

	/**
	 * @param array<string,mixed>  $candidate    Candidate.
	 * @param array<string,string> $field_values Searchable values.
	 * @param string               $term         Identifier-like term.
	 * @return bool
	 */
	protected function has_exact_identifier_match( array $candidate, array $field_values, $term ) {
		$structured = isset( $candidate['structured_data'] ) && is_array( $candidate['structured_data'] ) ? $candidate['structured_data'] : array();
		$scalars    = $this->flatten_scalar_values( $structured );
		foreach ( $scalars as $value ) {
			if ( $this->normalized_equals( $value, $term ) ) {
				return true;
			}
		}

		foreach ( array( 'title', 'taxonomies', 'excerpt', 'content' ) as $field ) {
			if ( ! empty( $field_values[ $field ] ) && $this->contains_token( $field_values[ $field ], $term ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $value Structured data.
	 * @return array<int,string>
	 */
	protected function flatten_scalar_values( array $value ) {
		$output = array();
		$walk = function ( $item ) use ( &$output, &$walk ) {
			if ( is_array( $item ) ) {
				foreach ( $item as $child ) {
					$walk( $child );
				}
				return;
			}
			if ( is_scalar( $item ) ) {
				$output[] = (string) $item;
			}
		};
		$walk( $value );
		return $output;
	}

	/** @return bool */
	protected function normalized_equals( $left, $right ) {
		$left  = $this->normalize_for_compare( $left );
		$right = $this->normalize_for_compare( $right );
		return '' !== $left && $left === $right;
	}

	/** @return string */
	protected function normalize_for_compare( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		$value = preg_replace( '/\s+/u', ' ', $value );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/** @return bool */
	protected function contains_token( $haystack, $needle ) {
		$haystack = $this->normalize_for_compare( $haystack );
		$needle   = $this->normalize_for_compare( $needle );
		if ( '' === $needle ) {
			return false;
		}

		$pattern = '/(?<![\p{L}\p{N}_-])' . preg_quote( $needle, '/' ) . '(?![\p{L}\p{N}_-])/u';
		return 1 === preg_match( $pattern, $haystack );
	}

	/** @return bool */
	protected function contains( $haystack, $needle ) {
		$haystack = (string) $haystack;
		$needle   = trim( (string) $needle );
		if ( '' === $needle ) {
			return false;
		}
		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $haystack, $needle, 0, 'UTF-8' );
		}
		return false !== stripos( $haystack, $needle );
	}

	/** @return string */
	protected function json_search_text( array $value ) {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return false === $encoded ? '' : (string) $encoded;
	}
}
