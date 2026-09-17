<?php
/**
 * Build a small, bounded Evidence Pack from ranked Local Retrieval results.
 *
 * v0.6.0 Stage 2 remains provider-free. The builder reads the persisted
 * Knowledge Store snapshot only after the Grounding Gate has allowed the
 * pipeline to continue. It deliberately caps source count and characters so
 * Retrieval also acts as a token / context firewall.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Evidence_Pack_Builder {

	const DEFAULT_MAX_SOURCES          = 3;
	const DEFAULT_MAX_CHARS_PER_SOURCE = 2400;
	const DEFAULT_MAX_TOTAL_CHARS      = 6000;
	const DEFAULT_SUPPORT_MIN_SCORE    = 18;
	const DEFAULT_SUPPORT_MIN_COVERAGE = 0.75;

	/** @var WPAIC_Knowledge_Store_Repository */
	protected $repository;

	public function __construct( WPAIC_Knowledge_Store_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * @param array<string,mixed> $retrieval Local Retrieval result.
	 * @return array<string,mixed>|WP_Error
	 */
	public function build( array $retrieval ) {
		$results = isset( $retrieval['results'] ) && is_array( $retrieval['results'] ) ? array_values( $retrieval['results'] ) : array();
		if ( empty( $results ) ) {
			return new WP_Error( 'wpaic_no_evidence_candidates', '没有可用于构建 Evidence Pack 的 Retrieval Result。' );
		}

		$limits = $this->get_limits();
		$chosen = $this->select_results( $results, $limits );
		if ( empty( $chosen ) ) {
			return new WP_Error( 'wpaic_no_qualified_evidence', '没有达到 Stage 2 Evidence Pack 要求的来源。' );
		}

		$sources     = array();
		$total_chars = 0;
		$index       = 1;

		foreach ( $chosen as $result ) {
			$source_id = isset( $result['source_id'] ) ? (string) $result['source_id'] : '';
			$row       = $this->repository->find_by_source_id( $source_id );
			if ( ! is_array( $row ) || 'active' !== ( isset( $row['store_status'] ) ? (string) $row['store_status'] : '' ) ) {
				continue;
			}

			$remaining = max( 0, (int) $limits['max_total_chars'] - $total_chars );
			if ( 0 === $remaining ) {
				break;
			}

			$source_budget = min( (int) $limits['max_chars_per_source'], $remaining );
			$evidence      = $this->build_source_text( $row, $result, $source_budget );
			if ( '' === $evidence['text'] ) {
				continue;
			}

			$coverage = isset( $result['score_breakdown']['coverage']['ratio'] ) ? (float) $result['score_breakdown']['coverage']['ratio'] : 0;
			$sources[] = array(
				'evidence_id'   => 'S' . $index,
				'source_id'     => $source_id,
				'object_id'     => isset( $result['object_id'] ) ? (int) $result['object_id'] : 0,
				'post_type'     => isset( $result['post_type'] ) ? (string) $result['post_type'] : '',
				'knowledge_type'=> isset( $result['knowledge_type'] ) ? (string) $result['knowledge_type'] : '',
				'title'         => isset( $row['title'] ) ? (string) $row['title'] : '',
				'url'           => isset( $row['url'] ) ? (string) $row['url'] : '',
				'source_hash'   => isset( $row['source_hash'] ) ? (string) $row['source_hash'] : '',
				'rank'          => isset( $result['rank'] ) ? (int) $result['rank'] : $index,
				'score'         => isset( $result['score'] ) ? (int) $result['score'] : 0,
				'coverage'      => max( 0, min( 1, $coverage ) ),
				'matched_terms' => isset( $result['matched_terms'] ) && is_array( $result['matched_terms'] ) ? array_values( $result['matched_terms'] ) : array(),
				'matched_fields'=> isset( $result['matched_fields'] ) && is_array( $result['matched_fields'] ) ? array_values( $result['matched_fields'] ) : array(),
				'text'          => $evidence['text'],
				'char_count'    => $this->string_length( $evidence['text'] ),
				'truncated'     => (bool) $evidence['truncated'],
			);

			$total_chars += $this->string_length( $evidence['text'] );
			$index++;
			if ( count( $sources ) >= (int) $limits['max_sources'] ) {
				break;
			}
		}

		if ( empty( $sources ) ) {
			return new WP_Error( 'wpaic_evidence_store_rows_missing', 'Retrieval Result 对应的 active Knowledge Store Snapshot 不可用。' );
		}

		return array(
			'status'       => 'built',
			'source_count' => count( $sources ),
			'total_chars'  => $total_chars,
			'limits'       => $limits,
			'sources'      => $sources,
		);
	}

	/** @return array<string,int|float> */
	public function get_limits() {
		$limits = array(
			'max_sources'          => self::DEFAULT_MAX_SOURCES,
			'max_chars_per_source' => self::DEFAULT_MAX_CHARS_PER_SOURCE,
			'max_total_chars'      => self::DEFAULT_MAX_TOTAL_CHARS,
			'support_min_score'    => self::DEFAULT_SUPPORT_MIN_SCORE,
			'support_min_coverage' => self::DEFAULT_SUPPORT_MIN_COVERAGE,
		);
		$limits = apply_filters( 'wpaic_evidence_pack_limits', $limits );
		$limits = is_array( $limits ) ? $limits : array();

		return array(
			'max_sources'          => max( 1, min( 5, absint( isset( $limits['max_sources'] ) ? $limits['max_sources'] : self::DEFAULT_MAX_SOURCES ) ) ),
			'max_chars_per_source' => max( 500, min( 6000, absint( isset( $limits['max_chars_per_source'] ) ? $limits['max_chars_per_source'] : self::DEFAULT_MAX_CHARS_PER_SOURCE ) ) ),
			'max_total_chars'      => max( 1000, min( 12000, absint( isset( $limits['max_total_chars'] ) ? $limits['max_total_chars'] : self::DEFAULT_MAX_TOTAL_CHARS ) ) ),
			'support_min_score'    => max( 1, absint( isset( $limits['support_min_score'] ) ? $limits['support_min_score'] : self::DEFAULT_SUPPORT_MIN_SCORE ) ),
			'support_min_coverage' => max( 0, min( 1, (float) ( isset( $limits['support_min_coverage'] ) ? $limits['support_min_coverage'] : self::DEFAULT_SUPPORT_MIN_COVERAGE ) ) ),
		);
	}

	/**
	 * Always keep rank #1. Supporting evidence must independently clear the
	 * conservative Stage 2 quality floor; low-scoring generic recall results do
	 * not enter the prompt merely because they appeared in Top-K.
	 *
	 * @param array<int,array<string,mixed>> $results Ranked public results.
	 * @param array<string,int|float>         $limits Limits.
	 * @return array<int,array<string,mixed>>
	 */
	protected function select_results( array $results, array $limits ) {
		$selected = array();
		foreach ( $results as $index => $result ) {
			if ( 0 === $index ) {
				$selected[] = $result;
				continue;
			}
			if ( count( $selected ) >= (int) $limits['max_sources'] ) {
				break;
			}

			$score    = isset( $result['score'] ) ? (int) $result['score'] : 0;
			$coverage = isset( $result['score_breakdown']['coverage']['ratio'] ) ? (float) $result['score_breakdown']['coverage']['ratio'] : 0;
			if ( $score < (int) $limits['support_min_score'] || $coverage < (float) $limits['support_min_coverage'] ) {
				continue;
			}
			$selected[] = $result;
		}
		return $selected;
	}

	/**
	 * @param array<string,mixed> $row    Store row.
	 * @param array<string,mixed> $result Ranked result.
	 * @param int                 $budget Character budget.
	 * @return array{text:string,truncated:bool}
	 */
	protected function build_source_text( array $row, array $result, $budget ) {
		$sections = array();
		$title    = $this->clean_text( isset( $row['title'] ) ? $row['title'] : '' );
		if ( '' !== $title ) {
			$sections[] = 'Title: ' . $title;
		}

		$structured = $this->structured_text( isset( $row['structured_data'] ) && is_array( $row['structured_data'] ) ? $row['structured_data'] : array() );
		if ( '' !== $structured ) {
			$sections[] = "Structured Data:\n" . $structured;
		}

		$excerpt = $this->clean_text( isset( $row['excerpt'] ) ? $row['excerpt'] : '' );
		if ( '' !== $excerpt ) {
			$sections[] = 'Excerpt: ' . $excerpt;
		}

		$content = $this->clean_text( isset( $row['content'] ) ? $row['content'] : '' );
		if ( '' !== $content ) {
			$terms   = isset( $result['matched_terms'] ) && is_array( $result['matched_terms'] ) ? $result['matched_terms'] : array();
			$snippet = $this->relevant_snippet( $content, $terms, max( 500, (int) floor( $budget * 0.55 ) ) );
			if ( '' !== $snippet ) {
				$sections[] = 'Relevant Content: ' . $snippet;
			}
		}

		$text      = implode( "\n\n", $sections );
		$truncated = $this->string_length( $text ) > $budget;
		$text      = $this->truncate( $text, $budget );
		return array( 'text' => $text, 'truncated' => $truncated );
	}

	/** @param array<string,mixed> $data Structured data. @return string */
	protected function structured_text( array $data ) {
		$lines = array();
		foreach ( $data as $key => $value ) {
			$label = ucwords( str_replace( array( '_', '-' ), ' ', (string) $key ) );
			if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
				if ( ! empty( $value['label'] ) ) {
					$label = $this->clean_text( $value['label'] );
				}
				$value = $value['value'];
			}
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
			$value = $this->clean_text( $value );
			if ( '' !== $value ) {
				$lines[] = $label . ': ' . $value;
			}
		}
		return implode( "\n", $lines );
	}

	/** @param string $content Content. @param array<int,string> $terms Terms. @param int $limit Limit. @return string */
	protected function relevant_snippet( $content, array $terms, $limit ) {
		$content = $this->clean_text( $content );
		if ( '' === $content ) {
			return '';
		}
		$position = false;
		foreach ( $terms as $term ) {
			$term = trim( (string) $term );
			if ( '' === $term ) {
				continue;
			}
			$found = function_exists( 'mb_stripos' ) ? mb_stripos( $content, $term, 0, 'UTF-8' ) : stripos( $content, $term );
			if ( false !== $found && ( false === $position || $found < $position ) ) {
				$position = (int) $found;
			}
		}
		$start = false === $position ? 0 : max( 0, $position - (int) floor( $limit * 0.25 ) );
		$text  = $this->substr( $content, $start, $limit );
		if ( $start > 0 ) {
			$text = '…' . ltrim( $text );
		}
		if ( $start + $this->string_length( $text ) < $this->string_length( $content ) ) {
			$text = rtrim( $text ) . '…';
		}
		return $text;
	}

	/** @param mixed $value Value. @return string */
	protected function clean_text( $value ) {
		$text = wp_strip_all_tags( (string) $value, true );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( null === $text ? '' : $text );
	}

	/** @param string $text Text. @param int $limit Limit. @return string */
	protected function truncate( $text, $limit ) {
		if ( $this->string_length( $text ) <= $limit ) {
			return $text;
		}
		return rtrim( $this->substr( $text, 0, max( 0, $limit - 1 ) ) ) . '…';
	}

	/** @param string $text Text. @return int */
	protected function string_length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
	}

	/** @param string $text Text. @param int $start Start. @param int $length Length. @return string */
	protected function substr( $text, $start, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $text, $start, $length, 'UTF-8' ) : substr( (string) $text, $start, $length );
	}
}
