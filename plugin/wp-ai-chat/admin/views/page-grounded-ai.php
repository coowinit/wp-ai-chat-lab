<?php
/**
 * v0.6.0 Stage 3 — Grounded Answer Playground.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$provider            = $this->manager->get_current_provider();
$provider_name       = is_wp_error( $provider ) ? 'Unavailable' : $provider->get_name();
$provider_configured = ! is_wp_error( $provider ) && $provider->is_configured();
$model_name          = ! is_wp_error( $provider ) && method_exists( $provider, 'get_model' ) ? $provider->get_model() : '';
?>
<div class="wrap wpaic-wrap wpaic-grounded-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Stage 3 — Grounded Answer</p>

	<div class="wpaic-grid wpaic-grounded-grid">
		<div class="wpaic-card">
			<h2>Grounded AI Playground</h2>
			<p>执行 Local Retrieval → Grounding Gate → Evidence Pack → Prompt Builder。只有 <code>allow_answer</code> 才会真正调用 AI Provider；<code>clarify / no_answer</code> 继续保持零 Token。</p>

			<div class="wpaic-grounding-controls">
				<label for="wpaic-grounding-question"><strong>Question</strong></label>
				<textarea id="wpaic-grounding-question" rows="4" maxlength="1000" placeholder="例如：CWC-610 dimension"></textarea>

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

				<p><button type="button" id="wpaic-run-grounding-gate" class="button button-primary">生成 Grounded Answer</button></p>
				<p class="description">只有 Gate = <code>allow_answer</code> 时按钮才可能产生真实 API 调用与 Token Usage。</p>
			</div>
			<div id="wpaic-grounding-result" class="wpaic-result" aria-live="polite"></div>
		</div>

		<div class="wpaic-card">
			<h2>Stage 3 边界</h2>
			<ul class="wpaic-plain-list">
				<li>✓ Stage 1 Grounding Gate</li>
				<li>✓ Stage 2 Evidence Pack / Prompt Builder</li>
				<li>✓ 只有 allow_answer 才调用 Provider</li>
				<li>✓ AI Manager 统一 Provider 入口</li>
				<li>✓ Grounded Answer + Source Trace</li>
				<li>✓ Provider / Model / Usage / Elapsed</li>
				<li>✓ clarify / no_answer 保持 AI Called = No</li>
				<li>✓ Source URL 由应用层维护</li>
				<li>— 不做 Conversation History</li>
				<li>— 不做前台 Chat / Human Handoff</li>
				<li>— 不做 Chunk / Embedding / Vector / RAG</li>
			</ul>
			<p class="description">Provider：<strong><?php echo esc_html( $provider_name ); ?></strong></p>
			<p class="description">Model：<code><?php echo esc_html( $model_name ? $model_name : '—' ); ?></code></p>
			<p class="description">API Key：<?php echo $provider_configured ? '<strong>已配置</strong>' : '<strong>未配置</strong>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<p class="description">默认 Grounded Answer：max_tokens 640，temperature 0.1；可通过 Filter 调整。</p>
			<p class="description">当前 active Knowledge Store Rows：<?php echo esc_html( number_format_i18n( $active_count ) ); ?></p>
		</div>
	</div>
</div>
