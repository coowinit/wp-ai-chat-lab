<?php
/**
 * Local Retrieval Playground.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpaic-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Local Retrieval · Stage 1</p>

	<div class="wpaic-grid">
		<div class="wpaic-card">
			<h2>本地检索 Playground</h2>
			<p>从 Knowledge Store 中召回可能相关的 <strong>active</strong> Knowledge Source。Stage 1 只验证 Query Normalize 与 Candidate Recall，不做最终 Ranking，也不调用 DeepSeek。</p>
			<div class="wpaic-retrieval-controls">
				<label for="wpaic-retrieval-question"><strong>Question</strong></label>
				<textarea id="wpaic-retrieval-question" rows="4" placeholder="例如：What is the dimension of CWC-610?"></textarea>

				<div class="wpaic-retrieval-options">
					<label for="wpaic-retrieval-candidate-limit"><strong>Candidate Limit</strong></label>
					<input id="wpaic-retrieval-candidate-limit" type="number" min="20" max="200" step="10" value="100" class="small-text">
					<span class="description">默认 100；最终会被限制在 20~200。</span>
				</div>

				<p><button type="button" id="wpaic-run-retrieval" class="button button-primary">本地检索</button></p>
			</div>
			<div id="wpaic-retrieval-result" class="wpaic-result" aria-live="polite"></div>
		</div>

		<div class="wpaic-card">
			<h2>Stage 1 边界</h2>
			<ul class="wpaic-plain-list">
				<li>✓ Query Normalizer</li>
				<li>✓ Term Extraction</li>
				<li>✓ Active-only Candidate Search</li>
				<li>✓ Candidate Limit</li>
				<li>✓ 只读 Knowledge Store</li>
				<li>✓ 不调用 DeepSeek</li>
				<li>— 不做 Weighted Scoring / Top-K</li>
				<li>— 不做 Chunk / Embedding / Vector / RAG</li>
			</ul>
			<p class="description">当前 active Knowledge Store Rows：<?php echo esc_html( number_format_i18n( $active_count ) ); ?></p>
		</div>
	</div>
</div>
