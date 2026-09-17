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
<div class="wrap wpaic-wrap wpaic-retrieval-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Local Retrieval · Stage 2</p>

	<div class="wpaic-grid wpaic-retrieval-grid">
		<div class="wpaic-card">
			<h2>本地检索 Playground</h2>
			<p>从 Knowledge Store 中召回 <strong>active</strong> Knowledge Source，并使用透明的 Weighted Scoring 排名。Stage 2 只验证本地 Ranking，不调用 DeepSeek。</p>
			<div class="wpaic-retrieval-controls">
				<label for="wpaic-retrieval-question"><strong>Question</strong></label>
				<textarea id="wpaic-retrieval-question" rows="4" placeholder="例如：What is the dimension of CWC-610?"></textarea>

				<div class="wpaic-retrieval-options">
					<label for="wpaic-retrieval-candidate-limit"><strong>Candidate Limit</strong></label>
					<input id="wpaic-retrieval-candidate-limit" type="number" min="20" max="200" step="10" value="100" class="small-text">
					<span class="description">默认 100；限制在 20~200。</span>

					<label for="wpaic-retrieval-top-k"><strong>Top K</strong></label>
					<select id="wpaic-retrieval-top-k">
						<option value="3">3</option>
						<option value="5" selected>5</option>
						<option value="10">10</option>
					</select>
				</div>

				<p><button type="button" id="wpaic-run-retrieval" class="button button-primary">本地检索</button></p>
			</div>
			<div id="wpaic-retrieval-result" class="wpaic-result" aria-live="polite"></div>
		</div>

		<div class="wpaic-card">
			<h2>Stage 2 边界</h2>
			<ul class="wpaic-plain-list">
				<li>✓ Stage 1 Candidate Recall</li>
				<li>✓ Field Weights</li>
				<li>✓ Exact Identifier Boost</li>
				<li>✓ Phrase Match Boost</li>
				<li>✓ Query Coverage Bonus</li>
				<li>✓ Top-K</li>
				<li>✓ Score Breakdown</li>
				<li>✓ 只读 Knowledge Store</li>
				<li>✓ 不调用 DeepSeek</li>
				<li>— Strength / Threshold 留到 Stage 3 校准</li>
				<li>— 不做 Chunk / Embedding / Vector / RAG</li>
			</ul>
			<p class="description">初始字段权重：Structured Data 8 · Title 6 · Taxonomies 4 · Excerpt 3 · Content 1</p>
			<p class="description">当前 active Knowledge Store Rows：<?php echo esc_html( number_format_i18n( $active_count ) ); ?></p>
		</div>
	</div>
</div>
