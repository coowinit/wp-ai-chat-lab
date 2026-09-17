<?php
/**
 * Stage 1 Local Retrieval orchestration: normalize query and recall candidates.
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

	public function __construct( WPAIC_Retrieval_Query_Normalizer $normalizer, WPAIC_Retrieval_Candidate_Searcher $searcher ) {
		$this->normalizer = $normalizer;
		$this->searcher   = $searcher;
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

		$limit      = isset( $args['candidate_limit'] ) ? $args['candidate_limit'] : 100;
		$limit      = $this->searcher->normalize_limit( $limit );
		$candidates = $this->searcher->search( $query, $limit );
		if ( is_wp_error( $candidates ) ) {
			return $candidates;
		}

		$result = array(
			'question'        => isset( $query['raw'] ) ? (string) $query['raw'] : '',
			'normalized_query'=> isset( $query['normalized'] ) ? (string) $query['normalized'] : '',
			'terms'           => isset( $query['terms'] ) && is_array( $query['terms'] ) ? array_values( $query['terms'] ) : array(),
			'candidate_limit' => $limit,
			'candidate_count' => count( $candidates ),
			'elapsed_ms'      => (int) round( ( microtime( true ) - $started ) * 1000 ),
			'candidates'      => $candidates,
		);

		return apply_filters( 'wpaic_retrieval_results', $result, $query, $args );
	}
}
