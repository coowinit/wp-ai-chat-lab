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
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Local Retrieval</p>

	<div class="wpaic-grid wpaic-retrieval-grid">
		<div class="wpaic-card">
			<h2>本地检索 Playground</h2>
			<p>从 Knowledge Store 中召回 <strong>active</strong> Knowledge Source，完成 Weighted Scoring 后再输出诊断级 Retrieval Strength。当前页面用于观察与校准本地检索质量，不调用 DeepSeek，也不是 Grounding Gate。</p>
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
			<h2>v0.5.0 边界</h2>
			<ul class="wpaic-plain-list">
				<li>✓ Stage 1 Candidate Recall</li>
				<li>✓ Stage 2 Weighted Scoring</li>
				<li>✓ Diagnostic Strength</li>
				<li>✓ Minimum Score Floor</li>
				<li>✓ Coverage + Score Gap</li>
				<li>✓ No Reliable Local Match</li>
				<li>✓ 只读 Knowledge Store</li>
				<li>✓ 不调用 DeepSeek</li>
				<li>— 不是 Grounding Gate</li>
				<li>— 不做 Chunk / Embedding / Vector / RAG</li>
			</ul>
			<p class="description">校准基线：Minimum 12 · Medium 18 · Strong 25；Coverage 50% / 75%；Score Gap 3 / 6。这是 v0.5.0 的初始诊断阈值，可通过 Filter 调整。</p>
			<p class="description">当前 active Knowledge Store Rows：<?php echo esc_html( number_format_i18n( $active_count ) ); ?></p>
		</div>
	</div>
</div>
