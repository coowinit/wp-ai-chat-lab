<?php
/**
 * Diagnostic retrieval-strength calibration for v0.5.0 Stage 3.
 *
 * This is intentionally not a Grounding Gate. It only turns the observable
 * ranking signals into a transparent strong / medium / weak / none label so
 * real query sets can calibrate the local retrieval layer before v0.6.0.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Retrieval_Strength_Evaluator {

	/** @var array<string,float|int> */
	protected $default_thresholds = array(
		'minimum_score'   => 12,
		'medium_score'    => 18,
		'strong_score'    => 25,
		'medium_coverage' => 0.50,
		'strong_coverage' => 0.75,
		'medium_gap'      => 3,
		'strong_gap'      => 6,
	);

	/**
	 * Evaluate an already sorted scored-result set.
	 *
	 * @param array<int,array<string,mixed>> $scored Sorted scored candidates.
	 * @return array<string,mixed>
	 */
	public function evaluate( array $scored ) {
		$thresholds = $this->get_thresholds();

		if ( empty( $scored ) ) {
			return $this->result_contract(
				'none',
				false,
				'no_candidates',
				0,
				0,
				0,
				0,
				$thresholds
			);
		}

		$top          = $scored[0];
		$top_score    = isset( $top['score'] ) ? (int) $top['score'] : 0;
		$second_score = isset( $scored[1]['score'] ) ? (int) $scored[1]['score'] : 0;
		$score_gap    = isset( $scored[1] ) ? max( 0, $top_score - $second_score ) : $top_score;
		$coverage     = isset( $top['score_breakdown']['coverage']['ratio'] ) ? (float) $top['score_breakdown']['coverage']['ratio'] : 0;
		$has_second   = isset( $scored[1] );

		if ( $top_score < (int) $thresholds['minimum_score'] ) {
			return $this->result_contract(
				'none',
				false,
				'below_minimum_score',
				$top_score,
				$second_score,
				$score_gap,
				$coverage,
				$thresholds
			);
		}

		$strong_gap_ok = ! $has_second || $score_gap >= (int) $thresholds['strong_gap'];
		if (
			$top_score >= (int) $thresholds['strong_score'] &&
			$coverage >= (float) $thresholds['strong_coverage'] &&
			$strong_gap_ok
		) {
			return $this->result_contract(
				'strong',
				true,
				'strong_score_coverage_gap',
				$top_score,
				$second_score,
				$score_gap,
				$coverage,
				$thresholds
			);
		}

		$medium_gap_ok = ! $has_second || $score_gap >= (int) $thresholds['medium_gap'];
		if (
			$top_score >= (int) $thresholds['medium_score'] &&
			$coverage >= (float) $thresholds['medium_coverage'] &&
			$medium_gap_ok
		) {
			return $this->result_contract(
				'medium',
				true,
				'medium_score_coverage_gap',
				$top_score,
				$second_score,
				$score_gap,
				$coverage,
				$thresholds
			);
		}

		return $this->result_contract(
			'weak',
			false,
			'weak_or_ambiguous_match',
			$top_score,
			$second_score,
			$score_gap,
			$coverage,
			$thresholds
		);
	}

	/**
	 * The thresholds are calibration defaults, not product truth. Keep them
	 * filterable so real query sets can tune behavior without rewriting logic.
	 *
	 * @return array<string,float|int>
	 */
	public function get_thresholds() {
		$thresholds = apply_filters( 'wpaic_retrieval_strength_thresholds', $this->default_thresholds );
		$thresholds = is_array( $thresholds ) ? $thresholds : $this->default_thresholds;

		$clean = array(
			'minimum_score'   => max( 0, min( 1000, isset( $thresholds['minimum_score'] ) ? (int) $thresholds['minimum_score'] : 12 ) ),
			'medium_score'    => max( 0, min( 1000, isset( $thresholds['medium_score'] ) ? (int) $thresholds['medium_score'] : 18 ) ),
			'strong_score'    => max( 0, min( 1000, isset( $thresholds['strong_score'] ) ? (int) $thresholds['strong_score'] : 25 ) ),
			'medium_coverage' => $this->clamp_ratio( isset( $thresholds['medium_coverage'] ) ? $thresholds['medium_coverage'] : 0.50 ),
			'strong_coverage' => $this->clamp_ratio( isset( $thresholds['strong_coverage'] ) ? $thresholds['strong_coverage'] : 0.75 ),
			'medium_gap'      => max( 0, min( 1000, isset( $thresholds['medium_gap'] ) ? (int) $thresholds['medium_gap'] : 3 ) ),
			'strong_gap'      => max( 0, min( 1000, isset( $thresholds['strong_gap'] ) ? (int) $thresholds['strong_gap'] : 6 ) ),
		);

		// Keep the levels monotonic even if a filter supplies unusual values.
		$clean['medium_score']    = max( $clean['minimum_score'], $clean['medium_score'] );
		$clean['strong_score']    = max( $clean['medium_score'], $clean['strong_score'] );
		$clean['strong_coverage'] = max( $clean['medium_coverage'], $clean['strong_coverage'] );
		$clean['strong_gap']      = max( $clean['medium_gap'], $clean['strong_gap'] );

		return $clean;
	}

	/** @return float */
	protected function clamp_ratio( $value ) {
		$value = (float) $value;
		return max( 0, min( 1, $value ) );
	}

	/**
	 * @param string                  $strength     Strength label.
	 * @param bool                    $reliable     Diagnostic reliable flag.
	 * @param string                  $reason       Machine-readable reason.
	 * @param int                     $top_score    Top score.
	 * @param int                     $second_score Second score.
	 * @param int                     $score_gap    Score gap.
	 * @param float                   $coverage     Top coverage ratio.
	 * @param array<string,float|int> $thresholds   Calibration thresholds.
	 * @return array<string,mixed>
	 */
	protected function result_contract( $strength, $reliable, $reason, $top_score, $second_score, $score_gap, $coverage, array $thresholds ) {
		return array(
			'strength'             => (string) $strength,
			'reliable_match'       => (bool) $reliable,
			'reason'               => (string) $reason,
			'top_score'            => (int) $top_score,
			'second_score'         => (int) $second_score,
			'score_gap'            => (int) $score_gap,
			'top_coverage'         => (float) $coverage,
			'minimum_score_passed' => (int) $top_score >= (int) $thresholds['minimum_score'],
			'thresholds'           => $thresholds,
		);
	}
}
