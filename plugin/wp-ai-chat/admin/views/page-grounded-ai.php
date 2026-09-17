<?php
/**
 * v0.7.0 Stage 2 — Provider Boundary Integration Playground.
 *
 * @var int               $active_count Active Knowledge Store rows.
 * @var array<string,int> $usage_limits Current Usage Guard limits.
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
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Stage 2 — Provider Boundary Integration</p>

	<div class="wpaic-grid wpaic-grounded-grid">
		<div class="wpaic-card">
			<h2>Grounded AI + Usage Guard Playground</h2>
			<p>执行 Local Retrieval → Grounding Gate → Evidence Pack → Prompt Builder → <strong>Usage Guard Reserve</strong> → AI Provider。只有 Grounding 与 Usage 两层都允许时，才真正越过 Provider Boundary。</p>

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

				<h3>Usage Context</h3>
				<p class="description">Stage 2 使用明确测试 Key 验证真实 Provider Boundary。原始 Key 不写入 Usage Counter Table，持久化的是 HMAC-SHA256 Hash。</p>
				<div class="wpaic-usage-fields">
					<label><strong>Conversation Key</strong><input type="text" id="wpaic-grounding-conversation-key" class="regular-text" value="grounded-stage2-conversation-1"></label>
					<label><strong>Visitor Key</strong><input type="text" id="wpaic-grounding-visitor-key" class="regular-text" value="grounded-stage2-visitor-1"></label>
					<label><strong>Site Key</strong><input type="text" id="wpaic-grounding-site-key" class="regular-text" value="grounded-stage2-site"></label>
				</div>

				<p><button type="button" id="wpaic-run-grounding-gate" class="button button-primary">生成 Grounded Answer</button></p>
				<p class="description">顺序固定：Grounding Gate 先判断知识资格；Usage Guard 再 Reserve Provider Call；只有两层都通过才产生真实 API 调用。</p>
			</div>
			<div id="wpaic-grounding-result" class="wpaic-result" aria-live="polite"></div>
		</div>

		<div class="wpaic-card">
			<h2>Stage 2 边界</h2>
			<ul class="wpaic-plain-list">
				<li>✓ Grounding Gate 仍优先</li>
				<li>✓ Evidence Pack / Prompt Builder</li>
				<li>✓ Usage Guard 位于 Provider Boundary 前</li>
				<li>✓ 原子 Reserve 后才允许 AI Manager</li>
				<li>✓ Provider Call Attempt 占用 Call Counter</li>
				<li>✓ 成功响应后记录真实 Token Usage</li>
				<li>✓ Usage BLOCK → AI Called = No / Token = 0</li>
				<li>✓ clarify / no_answer 不进入 Usage Guard</li>
				<li>— Stage 3 再做低额度综合校准与异常验证</li>
				<li>— 不做 Conversation History / Front-end Chat</li>
				<li>— 不做 Monthly Cost Guard</li>
			</ul>
			<p class="description">Provider：<strong><?php echo esc_html( $provider_name ); ?></strong></p>
			<p class="description">Model：<code><?php echo esc_html( $model_name ? $model_name : '—' ); ?></code></p>
			<p class="description">API Key：<?php echo $provider_configured ? '<strong>已配置</strong>' : '<strong>未配置</strong>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<p class="description">Usage Limits：Conversation <strong><?php echo esc_html( $usage_limits['conversation'] ); ?></strong> / Visitor <strong><?php echo esc_html( $usage_limits['visitor'] ); ?></strong> / Site <strong><?php echo esc_html( $usage_limits['site'] ); ?></strong></p>
			<p class="description">当前 active Knowledge Store Rows：<?php echo esc_html( number_format_i18n( $active_count ) ); ?></p>
		</div>
	</div>
</div>
