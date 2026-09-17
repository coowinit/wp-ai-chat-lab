<?php
/**
 * v0.6.0 Stage 1 — Grounding Gate Playground.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpaic-wrap wpaic-grounded-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Stage 1 — Grounding Gate Foundation</p>

	<div class="wpaic-grid wpaic-grounded-grid">
		<div class="wpaic-card">
			<h2>Grounding Gate Playground</h2>
			<p>先执行 v0.5.0 Local Retrieval，再由应用层 Gate 决定 <code>allow_answer</code>、<code>clarify</code> 或 <code>no_answer</code>。Stage 1 <strong>不会调用 DeepSeek</strong>；即使 Gate 允许进入后续 AI Pipeline，也只显示策略结果。</p>

			<div class="wpaic-grounding-controls">
				<label for="wpaic-grounding-question"><strong>Question</strong></label>
				<textarea id="wpaic-grounding-question" rows="4" placeholder="例如：CWC-610 dimension"></textarea>

				<div class="wpaic-retrieval-options">
					<label for="wpaic-grounding-candidate-limit"><strong>Candidate Limit</strong></label>
					<input id="wpaic-grounding-candidate-limit" type="number" min="20" max="200" step="10" value="100" class="small-text">

					<label for="wpaic-grounding-top-k"><strong>Top K</strong></label>
					<select id="wpaic-grounding-top-k">
						<option value="3">3</option>
						<option value="5" selected>5</option>
						<option value="10">10</option>
					</select>
				</div>

				<p><button type="button" id="wpaic-run-grounding-gate" class="button button-primary">测试 Grounding Gate</button></p>
			</div>
			<div id="wpaic-grounding-result" class="wpaic-result" aria-live="polite"></div>
		</div>

		<div class="wpaic-card">
			<h2>Stage 1 边界</h2>
			<ul class="wpaic-plain-list">
				<li>✓ Grounding Gate Contract</li>
				<li>✓ <code>allow_answer</code></li>
				<li>✓ <code>clarify</code></li>
				<li>✓ <code>no_answer</code></li>
				<li>✓ Machine-readable Reason Code</li>
				<li>✓ Application Gate 在 AI Provider 之前</li>
				<li>✓ AI Called 始终为 No</li>
				<li>✓ Token Usage 始终为 0</li>
				<li>— Stage 2 才构建 Evidence Pack / Prompt</li>
				<li>— Stage 3 才允许真实 AI Call</li>
			</ul>
			<p class="description">第一版保守策略：Strong + Reliable Yes → allow_answer；Medium / Weak → clarify；None → no_answer。</p>
			<p class="description">当前 active Knowledge Store Rows：<?php echo esc_html( number_format_i18n( $active_count ) ); ?></p>
		</div>
	</div>
</div>
