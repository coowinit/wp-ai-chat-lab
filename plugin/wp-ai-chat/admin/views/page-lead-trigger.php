<?php
/** Permanent v0.9.0 Stage 1 Lead Trigger Lab. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpaic-wrap">
	<h1>WP AI Chat Lab — Lead Trigger Lab</h1>
	<p class="description">v0.9.0 Stage 1 · Round 1 — Validation Passed / Sealed ✅ · Commercial Keywords Settings + Deterministic Lead Trigger Policy。该页面长期保留，用来调整商业需求关键词并回归验证“什么时候应该邀请访客提交询盘”。Round 2 已开始建立 Inquiry Capture Foundation。</p>
	<?php settings_errors( 'wpaic_lead_trigger_messages' ); ?>

	<div class="wpaic-card">
		<h2>Round 1 Validation Passed / Sealed ✅</h2>
		<div class="wpaic-flow-line"><code>Chat Result</code><span>→</span><code>Lead Trigger Policy</code><span>→</span><code>Offer / No Offer</code></div>
		<p><strong>Round 1 已完成真实 WordPress 验收并封板。</strong>Round 1 已封板；Stage 1 Round 2 / Round 3 也已完成 Inquiry Capture 与正式 Chat Inline Form 集成。Stage 2 Inquiry Admin Management 与 Stage 3 Operational Validation 也已完成；本页继续作为 Trigger Policy 永久回归工具。</p>
	</div>

	<div class="wpaic-grid">
		<div class="wpaic-card-stack">
			<div class="wpaic-card">
				<h2>商业需求关键词</h2>
				<p>插件只提供初始默认值，真正运行规则由管理员控制。支持一行一个，也支持逗号、中文逗号、分号分隔；保存后立即生效。</p>
				<form method="post" action="options.php">
					<?php settings_fields( 'wpaic_lead_trigger_settings_group' ); ?>
					<textarea name="<?php echo esc_attr( WPAIC_OPTION_LEAD_COMMERCIAL_KEYWORDS ); ?>" rows="18" class="large-text code" spellcheck="false"><?php echo esc_textarea( $lead_keywords_text ); ?></textarea>
					<p class="description"><strong>重要：</strong>保存空内容代表主动关闭 <code>commercial_intent</code> 关键词触发；插件不会自动重新填回默认值。</p>
					<?php submit_button( '保存商业需求关键词' ); ?>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpaic-inline-form">
					<input type="hidden" name="action" value="wpaic_restore_lead_keywords">
					<?php wp_nonce_field( 'wpaic_restore_lead_keywords' ); ?>
					<?php submit_button( '恢复默认关键词', 'secondary', 'submit', false ); ?>
				</form>
				<p class="description">只有点击“恢复默认关键词”才会覆盖当前列表。该操作不会影响 Chat、Usage 或 Provider 设置。</p>
			</div>

			<div class="wpaic-card">
				<h2>Lead Trigger Playground</h2>
				<p>这里直接调用正式 <code>WPAIC_Lead_Trigger_Policy</code>，不是另一套演示逻辑。你可以组合问题与 Chat 状态，观察是否应该显示询盘 CTA。</p>
				<form method="post">
					<?php wp_nonce_field( 'wpaic_lead_trigger_test' ); ?>
					<input type="hidden" name="wpaic_lead_trigger_test" value="1">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wpaic-lead-question">访客问题</label></th>
							<td><textarea id="wpaic-lead-question" name="question" rows="4" class="large-text"><?php echo esc_textarea( $lead_test_input['question'] ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="wpaic-lead-response-type">Chat 结果</label></th>
							<td>
								<select id="wpaic-lead-response-type" name="response_type">
									<?php foreach ( array( 'answer', 'clarify', 'no_answer', 'blocked', 'error' ) as $response_type ) : ?>
										<option value="<?php echo esc_attr( $response_type ); ?>" <?php selected( $lead_test_input['response_type'], $response_type ); ?>><?php echo esc_html( $response_type ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><code>clarify</code> / <code>error</code> 默认不自动触发 Lead。</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpaic-lead-block-reason">Blocked 原因</label></th>
							<td>
								<select id="wpaic-lead-block-reason" name="block_reason">
									<option value="" <?php selected( $lead_test_input['block_reason'], '' ); ?>>— 不适用 —</option>
									<option value="conversation_limit_reached" <?php selected( $lead_test_input['block_reason'], 'conversation_limit_reached' ); ?>>Conversation Limit</option>
									<option value="visitor_daily_limit_reached" <?php selected( $lead_test_input['block_reason'], 'visitor_daily_limit_reached' ); ?>>Visitor Daily Limit</option>
									<option value="site_daily_limit_reached" <?php selected( $lead_test_input['block_reason'], 'site_daily_limit_reached' ); ?>>Site Daily Limit</option>
								</select>
								<p class="description">只有 Chat 结果为 <code>blocked</code> 时使用。</p>
							</td>
						</tr>
						<tr>
							<th scope="row">用户主动操作</th>
							<td><label><input type="checkbox" name="manual" value="1" <?php checked( ! empty( $lead_test_input['manual'] ) ); ?>> 模拟用户主动点击 Contact / Quote CTA</label></td>
						</tr>
					</table>
					<?php submit_button( '测试 Lead Trigger' ); ?>
				</form>

				<?php if ( is_array( $lead_test_result ) ) : ?>
					<div class="wpaic-result <?php echo ! empty( $lead_test_result['offer'] ) ? 'is-success' : ''; ?>">
						<h3><?php echo ! empty( $lead_test_result['offer'] ) ? '应该显示 Lead CTA ✅' : '不应自动显示 Lead CTA'; ?></h3>
						<dl class="wpaic-meta wpaic-meta-source">
							<div><dt>Offer</dt><dd><strong><?php echo ! empty( $lead_test_result['offer'] ) ? 'Yes' : 'No'; ?></strong></dd></div>
							<div><dt>Trigger</dt><dd><code><?php echo esc_html( $lead_test_result['trigger_type'] ? $lead_test_result['trigger_type'] : '—' ); ?></code></dd></div>
							<div><dt>CTA</dt><dd><?php echo esc_html( $lead_test_result['cta_label'] ? $lead_test_result['cta_label'] : '—' ); ?></dd></div>
							<div><dt>Placement</dt><dd><code><?php echo esc_html( $lead_test_result['placement'] ? $lead_test_result['placement'] : '—' ); ?></code></dd></div>
							<div class="wpaic-meta-wide"><dt>Matched Keyword</dt><dd><code><?php echo esc_html( $lead_test_result['matched_keyword'] ? $lead_test_result['matched_keyword'] : '—' ); ?></code></dd></div>
							<div class="wpaic-meta-wide"><dt>Reason</dt><dd><?php echo esc_html( $lead_test_result['reason'] ); ?></dd></div>
						</dl>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="wpaic-card-stack">
			<div class="wpaic-card">
				<h2>当前状态</h2>
				<dl class="wpaic-meta wpaic-meta-source">
					<div><dt>Plugin</dt><dd><?php echo esc_html( WPAIC_VERSION ); ?></dd></div>
					<div><dt>DB Version</dt><dd><?php echo esc_html( WPAIC_DB_VERSION ); ?></dd></div>
					<div><dt>Keywords</dt><dd><?php echo esc_html( (int) $lead_keywords_count ); ?></dd></div>
					<div><dt>Commercial Intent</dt><dd><?php echo $lead_keywords_count > 0 ? 'Enabled' : 'Disabled'; ?></dd></div>
					<div><dt>Inquiry Table</dt><dd>Created in Round 2</dd></div>
					<div><dt>AI Lead Scoring</dt><dd>Disabled</dd></div>
				</dl>
			</div>

			<div class="wpaic-card">
				<h2>固定 Trigger 顺序</h2>
				<div class="wpaic-flow-line"><code>manual</code><span>→</span><code>commercial_intent</code><span>→</span><code>no_answer</code><span>→</span><code>usage_blocked</code></div>
				<p class="description"><code>clarify</code> 与 <code>error</code> 是自动 Lead 的硬停止状态；用户主动 <code>manual</code> 仍然始终允许。</p>
			</div>

			<div class="wpaic-card">
				<h2>Round 1 已通过的验收场景 ✅</h2>
				<ol class="wpaic-validation-list">
					<li><strong>普通 Answer：</strong><code>CWC-610 dimension</code> + <code>answer</code> → No Offer。</li>
					<li><strong>商业意图：</strong><code>Can I get a quote for CWC-610?</code> + <code>answer</code> → <code>commercial_intent</code>。</li>
					<li><strong>自定义中文：</strong>先新增 <code>报价</code>，保存后测试“我想要 CWC-610 报价” → 命中 <code>报价</code>。</li>
					<li><strong>清空关闭：</strong>清空关键词并保存，再测试报价问题 → 不再触发 <code>commercial_intent</code>。</li>
					<li><strong>恢复默认：</strong>点击恢复默认关键词 → 默认列表回来并再次生效。</li>
					<li><strong>no_answer：</strong>普通未知问题 + <code>no_answer</code> → <code>no_answer</code> CTA。</li>
					<li><strong>Usage Block：</strong><code>blocked</code> + Visitor/Site Limit → <code>usage_blocked</code>；Conversation Limit → secondary CTA。</li>
					<li><strong>Clarify / Error：</strong>即使问题包含 <code>quote</code>，也不自动触发。</li>
					<li><strong>Manual：</strong>勾选用户主动操作 → 始终优先得到 <code>manual</code>。</li>
					<li><strong>词边界：</strong><code>What is the border dimension?</code> + <code>answer</code> → 不应因 <code>order</code> 而触发。</li>
				</ol>
			</div>

			<div class="wpaic-card">
				<h2>Round 1 当时明确不做</h2>
				<ul class="wpaic-plain-list">
					<li>Round 1 不创建 <code>wp_wpaic_inquiries</code>（现由 Round 2 创建）</li>
					<li>Round 1 不新增 Public Inquiry REST（现由 Round 2 建立独立 Boundary）</li>
					<li>不修改正式 Chat Widget</li>
					<li>不保存 Name / Email / Phone 等个人信息</li>
					<li>不增加 AI Lead Scoring</li>
					<li>不进入 Human Handoff / CRM</li>
				</ul>
			</div>
		</div>
	</div>
</div>
