<?php
/**
 * Local Retrieval orchestration: normalize, recall, score and rank candidates.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Local_Retriever {

	/** @var WPAIC_Retrieval_Query_Normalizer */
	protected $normalizer;

	/** @var WPAIC_Retrieval_Candidate_Searcher */
	protected $searcher;

	/** @var WPAIC_Retrieval_Scorer */
	protected $scorer;

	/** @var WPAIC_Retrieval_Strength_Evaluator */
	protected $strength_evaluator;

	public function __construct( WPAIC_Retrieval_Query_Normalizer $normalizer, WPAIC_Retrieval_Candidate_Searcher $searcher, WPAIC_Retrieval_Scorer $scorer, WPAIC_Retrieval_Strength_Evaluator $strength_evaluator ) {
		$this->normalizer           = $normalizer;
		$this->searcher             = $searcher;
		$this->scorer               = $scorer;
		$this->strength_evaluator   = $strength_evaluator;
	}

	/**
	 * @param string              $question User question.
	 * @param array<string,mixed> $args     Retrieval arguments.
	 * @return array<string,mixed>|WP_Error
	 */
	public function retrieve( $question, array $args = array() ) {
		$started = microtime( true );
		$query   = $this->normalizer->normalize( $question );
		if ( is_wp_error( $query ) ) {
			return $query;
		}

		$candidate_limit = isset( $args['candidate_limit'] ) ? $args['candidate_limit'] : 100;
		$candidate_limit = $this->searcher->normalize_limit( $candidate_limit );
		$top_k           = $this->normalize_top_k( isset( $args['top_k'] ) ? $args['top_k'] : 5 );
		$candidates      = $this->searcher->search( $query, $candidate_limit );
		if ( is_wp_error( $candidates ) ) {
			return $candidates;
		}

		$scored = array();
		foreach ( $candidates as $candidate ) {
			$scored[] = $this->scorer->score( $candidate, $query );
		}

		usort( $scored, array( $this, 'compare_results' ) );
		$strength    = $this->strength_evaluator->evaluate( $scored );
		$top_results = array_slice( $scored, 0, $top_k );
		$rank        = 1;
		foreach ( $top_results as &$result ) {
			$result['rank'] = $rank++;
			$result = $this->prepare_public_result( $result );
		}
		unset( $result );

		$result = array(
			'question'         => isset( $query['raw'] ) ? (string) $query['raw'] : '',
			'normalized_query' => isset( $query['normalized'] ) ? (string) $query['normalized'] : '',
			'terms'            => isset( $query['terms'] ) && is_array( $query['terms'] ) ? array_values( $query['terms'] ) : array(),
			'candidate_limit'  => $candidate_limit,
			'candidate_count'  => count( $candidates ),
			'scored_count'     => count( $scored ),
			'top_k'            => $top_k,
			'elapsed_ms'       => (int) round( ( microtime( true ) - $started ) * 1000 ),
			'field_weights'    => $this->scorer->get_field_weights(),
			'score_baseline'   => array(
				'exact_identifier_boost' => $this->scorer->get_exact_identifier_boost(),
				'phrase_match_boost'     => $this->scorer->get_phrase_match_boost(),
				'coverage_bonus_max'     => $this->scorer->get_coverage_bonus_max(),
			),
			'strength'         => $strength,
			'results'          => $top_results,
		);

		return apply_filters( 'wpaic_retrieval_results', $result, $query, $args );
	}

	/**
	 * @param mixed $top_k Requested Top K.
	 * @return int
	 */
	public function normalize_top_k( $top_k ) {
		$top_k = absint( $top_k );
		if ( ! $top_k ) {
			$top_k = 5;
		}
		$top_k = (int) apply_filters( 'wpaic_retrieval_top_k', $top_k );
		return max( 1, min( 10, $top_k ) );
	}

	/**
	 * Stable ranking: score, coverage, matched-term count, matched-field count,
	 * then source_id as the final deterministic tie breaker.
	 *
	 * @param array<string,mixed> $left  Left result.
	 * @param array<string,mixed> $right Right result.
	 * @return int
	 */
	protected function compare_results( array $left, array $right ) {
		$left_score  = isset( $left['score'] ) ? (int) $left['score'] : 0;
		$right_score = isset( $right['score'] ) ? (int) $right['score'] : 0;
		if ( $left_score !== $right_score ) {
			return $left_score > $right_score ? -1 : 1;
		}

		$left_coverage  = isset( $left['score_breakdown']['coverage']['ratio'] ) ? (float) $left['score_breakdown']['coverage']['ratio'] : 0;
		$right_coverage = isset( $right['score_breakdown']['coverage']['ratio'] ) ? (float) $right['score_breakdown']['coverage']['ratio'] : 0;
		if ( $left_coverage !== $right_coverage ) {
			return $left_coverage > $right_coverage ? -1 : 1;
		}

		$left_terms  = isset( $left['matched_terms'] ) && is_array( $left['matched_terms'] ) ? count( $left['matched_terms'] ) : 0;
		$right_terms = isset( $right['matched_terms'] ) && is_array( $right['matched_terms'] ) ? count( $right['matched_terms'] ) : 0;
		if ( $left_terms !== $right_terms ) {
			return $left_terms > $right_terms ? -1 : 1;
		}

		$left_fields  = isset( $left['matched_fields'] ) && is_array( $left['matched_fields'] ) ? count( $left['matched_fields'] ) : 0;
		$right_fields = isset( $right['matched_fields'] ) && is_array( $right['matched_fields'] ) ? count( $right['matched_fields'] ) : 0;
		if ( $left_fields !== $right_fields ) {
			return $left_fields > $right_fields ? -1 : 1;
		}

		$left_id  = isset( $left['source_id'] ) ? (string) $left['source_id'] : '';
		$right_id = isset( $right['source_id'] ) ? (string) $right['source_id'] : '';
		return strcmp( $left_id, $right_id );
	}

	/**
	 * Do not send full Knowledge Store content / structured payload to the
	 * diagnostics UI. The scorer consumes it internally and returns only the
	 * explainable result contract needed by Stage 2.
	 *
	 * @param array<string,mixed> $result Scored result.
	 * @return array<string,mixed>
	 */
	protected function prepare_public_result( array $result ) {
		return array(
			'rank'            => isset( $result['rank'] ) ? (int) $result['rank'] : 0,
			'source_id'       => isset( $result['source_id'] ) ? (string) $result['source_id'] : '',
			'object_id'       => isset( $result['object_id'] ) ? (int) $result['object_id'] : 0,
			'post_type'       => isset( $result['post_type'] ) ? (string) $result['post_type'] : '',
			'knowledge_type'  => isset( $result['knowledge_type'] ) ? (string) $result['knowledge_type'] : '',
			'title'           => isset( $result['title'] ) ? (string) $result['title'] : '',
			'url'             => isset( $result['url'] ) ? (string) $result['url'] : '',
			'source_hash'     => isset( $result['source_hash'] ) ? (string) $result['source_hash'] : '',
			'score'           => isset( $result['score'] ) ? (int) $result['score'] : 0,
			'matched_terms'   => isset( $result['matched_terms'] ) && is_array( $result['matched_terms'] ) ? array_values( $result['matched_terms'] ) : array(),
			'matched_fields'  => isset( $result['matched_fields'] ) && is_array( $result['matched_fields'] ) ? array_values( $result['matched_fields'] ) : array(),
			'score_breakdown' => isset( $result['score_breakdown'] ) && is_array( $result['score_breakdown'] ) ? $result['score_breakdown'] : array(),
			'snippet'         => isset( $result['snippet'] ) ? (string) $result['snippet'] : '',
		);
	}
}
