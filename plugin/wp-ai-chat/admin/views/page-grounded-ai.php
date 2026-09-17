<?php
/**
 * v0.6.0 Stage 2 — Evidence Pack & Prompt Builder Playground.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpaic-wrap wpaic-grounded-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Stage 2 — Evidence Pack &amp; Prompt Builder</p>

	<div class="wpaic-grid wpaic-grounded-grid">
		<div class="wpaic-card">
			<h2>Grounded AI Playground</h2>
			<p>先执行 Local Retrieval 与 Grounding Gate。只有 <code>allow_answer</code> 才构建 Evidence Pack 与 Prompt Preview；Stage 2 <strong>仍不会调用 DeepSeek</strong>。</p>

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

				<p><button type="button" id="wpaic-run-grounding-gate" class="button button-primary">测试 Evidence Pipeline</button></p>
			</div>
			<div id="wpaic-grounding-result" class="wpaic-result" aria-live="polite"></div>
		</div>

		<div class="wpaic-card">
			<h2>Stage 2 边界</h2>
			<ul class="wpaic-plain-list">
				<li>✓ Stage 1 Grounding Gate</li>
				<li>✓ Evidence Pack Builder</li>
				<li>✓ Primary Evidence = Rank #1</li>
				<li>✓ Supporting Evidence Quality Floor</li>
				<li>✓ Evidence Budget / Token Firewall</li>
				<li>✓ S1 / S2 / S3 Source Trace</li>
				<li>✓ Evidence 作为 Data，不作为 Instruction</li>
				<li>✓ Prompt Preview</li>
				<li>✓ AI Called 始终为 No</li>
				<li>✓ Token Usage 始终为 0</li>
				<li>— Stage 3 才允许真实 AI Call</li>
			</ul>
			<p class="description">默认预算：最多 3 个 Evidence Source；每个最多 2400 字符；总 Evidence 最多 6000 字符。</p>
			<p class="description">Supporting Evidence 默认至少 Score 18 且 Coverage 75%，避免普通召回结果污染 Prompt。</p>
			<p class="description">当前 active Knowledge Store Rows：<?php echo esc_html( number_format_i18n( $active_count ) ); ?></p>
		</div>
	</div>
</div>
