<?php
/**
 * Conservative application-layer Grounding Gate for v0.6.0 Stage 1.
 *
 * The gate consumes the v0.5.0 Retrieval Result contract and decides whether a
 * later Grounded AI pipeline would be allowed to proceed. Stage 1 never calls
 * an AI provider; allow_ai only represents policy permission for a future
 * stage, not an actual provider call.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Grounding_Gate {

	/**
	 * Evaluate one Local Retrieval result.
	 *
	 * Conservative v0.6.0 Stage 1 policy:
	 * - strong + reliable => allow_answer
	 * - medium            => clarify
	 * - weak              => clarify
	 * - none              => no_answer
	 *
	 * @param array<string,mixed> $retrieval Retrieval result from WPAIC_Local_Retriever.
	 * @return array<string,mixed>
	 */
	public function evaluate( array $retrieval ) {
		$strength_data = isset( $retrieval['strength'] ) && is_array( $retrieval['strength'] ) ? $retrieval['strength'] : array();
		$strength      = isset( $strength_data['strength'] ) ? sanitize_key( (string) $strength_data['strength'] ) : 'none';
		$reliable      = ! empty( $strength_data['reliable_match'] );
		$top_score     = isset( $strength_data['top_score'] ) ? (int) $strength_data['top_score'] : 0;
		$score_gap     = isset( $strength_data['score_gap'] ) ? (int) $strength_data['score_gap'] : 0;
		$top_coverage  = isset( $strength_data['top_coverage'] ) ? $this->clamp_ratio( $strength_data['top_coverage'] ) : 0;

		if ( 'strong' === $strength && $reliable ) {
			return $this->contract(
				'allow_answer',
				true,
				'strong_reliable_match',
				'本地知识存在明确且可靠的 Strong 匹配；允许进入后续 Evidence Pack。Stage 1 仍不会调用 AI。',
				$strength,
				$reliable,
				$top_score,
				$score_gap,
				$top_coverage
			);
		}

		if ( 'medium' === $strength ) {
			return $this->contract(
				'clarify',
				false,
				'medium_needs_clarification',
				'存在一定本地证据，但尚未达到 Strong；请补充产品型号、具体场景或更明确的问题。',
				$strength,
				$reliable,
				$top_score,
				$score_gap,
				$top_coverage
			);
		}

		if ( 'weak' === $strength || 'strong' === $strength ) {
			return $this->contract(
				'clarify',
				false,
				'weak_ambiguous_match',
				'候选知识存在，但相关性或区分度不足；请补充产品型号、具体场景或更明确的问题。',
				$strength,
				$reliable,
				$top_score,
				$score_gap,
				$top_coverage
			);
		}

		return $this->contract(
			'no_answer',
			false,
			'no_local_evidence',
			'当前 Knowledge Store 中没有找到足够相关的本地证据。',
			'none',
			false,
			$top_score,
			$score_gap,
			$top_coverage
		);
	}

	/**
	 * @param string $decision           Gate decision.
	 * @param bool   $allow_ai           Whether a future AI stage may proceed.
	 * @param string $reason_code        Machine-readable reason code.
	 * @param string $reason_message     Human-readable reason.
	 * @param string $strength           Retrieval strength.
	 * @param bool   $reliable           Retrieval reliable-match flag.
	 * @param int    $top_score          Top score.
	 * @param int    $score_gap          Top/second score gap.
	 * @param float  $top_coverage       Top coverage ratio.
	 * @return array<string,mixed>
	 */
	protected function contract( $decision, $allow_ai, $reason_code, $reason_message, $strength, $reliable, $top_score, $score_gap, $top_coverage ) {
		return array(
			'decision'           => (string) $decision,
			'allow_ai'           => (bool) $allow_ai,
			'reason_code'        => (string) $reason_code,
			'reason_message'     => (string) $reason_message,
			'retrieval_strength' => (string) $strength,
			'reliable_match'     => (bool) $reliable,
			'top_score'          => (int) $top_score,
			'score_gap'          => (int) $score_gap,
			'top_coverage'       => (float) $top_coverage,
		);
	}

	/** @return float */
	protected function clamp_ratio( $value ) {
		$value = (float) $value;
		return max( 0, min( 1, $value ) );
	}
}
