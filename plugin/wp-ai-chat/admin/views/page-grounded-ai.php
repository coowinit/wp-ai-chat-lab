<?php
/**
 * v0.7.0 Stage 3 — Final Operational Validation.
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
$final_limits_ready  = 2 === (int) $usage_limits['conversation'] && 3 === (int) $usage_limits['visitor'] && 5 === (int) $usage_limits['site'];
?>
<div class="wrap wpaic-wrap wpaic-grounded-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Stage 3 — Final Operational Validation</p>

	<div class="wpaic-grid wpaic-grounded-grid">
		<div class="wpaic-card">
			<div class="wpaic-final-intro">
				<p class="wpaic-scene-kicker">Validation Passed / Sealed ✅</p>
				<h2>Stage 3 最终验收 — 真实链路回归工具</h2>
				<p>Round 1 / Round 2 / Final Operational Validation 均已 Passed。这里保留完整回归工具，核心边界是：<strong>只有“知识足够可靠 + Usage 还有额度”时，系统才真正调用 AI。</strong></p>
			</div>

			<div class="wpaic-final-flow" aria-label="Final Operational Validation pipeline">
				<div><strong>① 先看知识</strong><span>Grounding 是否足够可靠？</span></div>
				<div class="wpaic-final-arrow">→</div>
				<div><strong>② 再看额度</strong><span>Usage Guard 是否允许？</span></div>
				<div class="wpaic-final-arrow">→</div>
				<div><strong>③ 最后调用 AI</strong><span>两层都通过才越过 Provider Boundary</span></div>
			</div>

			<?php if ( ! $final_limits_ready ) : ?>
				<div class="notice notice-warning inline"><p><strong>当前 Usage Limits 不是 2 / 3 / 5。</strong> 请先到 Usage Guard 页面调整并保存，否则下面的预期计数和第 6 步 BLOCK 不成立。</p></div>
			<?php endif; ?>

			<div class="wpaic-final-start">
				<div>
					<strong>需要回归复测时：</strong>
					<span>点击右侧“重置最终验收计数”，之后按 1 → 6 连续测试，中途不要再重置。</span>
				</div>
				<button type="button" id="wpaic-grounded-final-reset" class="button">重置最终验收计数</button>
			</div>
			<div id="wpaic-grounded-final-reset-result" class="wpaic-result" aria-live="polite"></div>

			<div class="wpaic-usage-presets wpaic-final-validation">
				<h3>按顺序点击 1 → 6</h3>
				<p class="description">场景按钮只会自动填写测试问题，不会自动调用 AI。每一步选择场景后，再点击下方“生成 Grounded Answer”。</p>
				<div class="wpaic-scenario-grid">
					<button type="button" class="button wpaic-scenario-card wpaic-grounded-final-preset is-active"
						data-question="CWC-610 dimension"
						data-title="1. 有明确知识 → AI 正常回答"
						data-summary="知识库能明确回答，而且当前还有 AI 调用额度。"
						data-change="访客询问 CWC-610 的 dimension。这是我们已经验证过的 Strong 问题。"
						data-action="点击“生成 Grounded Answer”一次。"
						data-expect="Grounding = Strong；Usage Guard = ALLOW；AI Called = Yes；C=1/2、V=1/3、S=1/5；本次 Token > 0。">
						<strong>1. 有明确知识 → AI 正常回答</strong><span>Strong + 有额度 = 真正调用 AI</span>
					</button>

					<button type="button" class="button wpaic-scenario-card wpaic-grounded-final-preset"
						data-question="dimension"
						data-title="2. 问题太模糊 → 不调用 AI"
						data-summary="只有一个模糊关键词，系统应该先让用户把问题说清楚。"
						data-change="访客只输入 dimension，没有产品名或足够上下文。"
						data-action="点击“生成 Grounded Answer”一次。"
						data-expect="Grounding = Weak；Decision = clarify；Usage Guard Skipped；AI Called = No；Token = 0；计数仍为 C=1/2、V=1/3、S=1/5。">
						<strong>2. 问题太模糊 → 不调用 AI</strong><span>Weak → clarify · 不花额度</span>
					</button>

					<button type="button" class="button wpaic-scenario-card wpaic-grounded-final-preset"
						data-question="CWC-610 warranty"
						data-title="3. 资料不够明确 → 不调用 AI"
						data-summary="找到了相关资料，但证据还不足以支持直接回答。"
						data-change="访客询问 CWC-610 warranty，当前 Evidence 只达到 Medium。"
						data-action="点击“生成 Grounded Answer”一次。"
						data-expect="Grounding = Medium；Decision = clarify；Usage Guard Skipped；AI Called = No；Token = 0；计数仍为 C=1/2、V=1/3、S=1/5。">
						<strong>3. 资料不够明确 → 不调用 AI</strong><span>Medium → clarify · 不花额度</span>
					</button>

					<button type="button" class="button wpaic-scenario-card wpaic-grounded-final-preset"
						data-question="ZXQ-99999-NOMATCH"
						data-title="4. 完全没有资料 → 不调用 AI"
						data-summary="知识库完全找不到可靠证据，系统应该明确停止。"
						data-change="访客询问一个知识库中不存在的 ZXQ-99999-NOMATCH。"
						data-action="点击“生成 Grounded Answer”一次。"
						data-expect="Grounding = None；Decision = no_answer；Usage Guard Skipped；AI Called = No；Token = 0；计数仍为 C=1/2、V=1/3、S=1/5。">
						<strong>4. 完全没有资料 → 不调用 AI</strong><span>None → no_answer · 不花额度</span>
					</button>

					<button type="button" class="button wpaic-scenario-card wpaic-grounded-final-preset"
						data-question="CWC-610 dimension"
						data-title="5. 第二次正常回答 → 达到会话上限"
						data-summary="再次提出可靠问题，第二次真实 AI 调用应该正常完成。"
						data-change="同一 Conversation 再次询问 Strong 问题。"
						data-action="点击“生成 Grounded Answer”一次。"
						data-expect="Grounding = Strong；Usage Guard = ALLOW；AI Called = Yes；C=2/2、V=2/3、S=2/5；Token 再次产生并累计。">
						<strong>5. 第二次正常回答 → 达到会话上限</strong><span>Strong + 有额度 = 第二次调用 AI</span>
					</button>

					<button type="button" class="button wpaic-scenario-card wpaic-grounded-final-preset"
						data-question="CWC-610 dimension"
						data-title="6. 再问一次 → 被额度阻止"
						data-summary="知识仍然可靠，但 Conversation 已经达到 2/2，不能再进入 Provider。"
						data-change="同一 Conversation 第三次询问 Strong 问题。"
						data-action="点击“生成 Grounded Answer”一次。"
						data-expect="Grounding = Strong；Usage Guard = BLOCK；Reason Code = conversation_limit_reached；AI Called = No；本次 Token = 0；计数保持 C=2/2、V=2/3、S=2/5。">
						<strong>6. 再问一次 → 被额度阻止</strong><span>有知识，但没额度 = Provider 不调用</span>
					</button>
				</div>
			</div>

			<div class="wpaic-scene-guide wpaic-final-guide" aria-live="polite">
				<p class="wpaic-scene-kicker">当前最终验收场景</p>
				<h3 id="wpaic-grounded-final-title">1. 有明确知识 → AI 正常回答</h3>
				<p id="wpaic-grounded-final-summary" class="wpaic-scene-summary">知识库能明确回答，而且当前还有 AI 调用额度。</p>
				<dl>
					<div><dt>发生了什么</dt><dd id="wpaic-grounded-final-change">访客询问 CWC-610 的 dimension。这是我们已经验证过的 Strong 问题。</dd></div>
					<div><dt>现在怎么做</dt><dd id="wpaic-grounded-final-action">点击“生成 Grounded Answer”一次。</dd></div>
					<div><dt>预期结果</dt><dd id="wpaic-grounded-final-expect">Grounding = Strong；Usage Guard = ALLOW；AI Called = Yes；C=1/2、V=1/3、S=1/5；本次 Token &gt; 0。</dd></div>
				</dl>
			</div>

			<div class="wpaic-final-question-box">
				<label for="wpaic-grounding-question"><strong>本步测试问题</strong></label>
				<textarea id="wpaic-grounding-question" rows="3" maxlength="1000">CWC-610 dimension</textarea>
				<p><button type="button" id="wpaic-run-grounding-gate" class="button button-primary button-large">生成 Grounded Answer</button></p>
				<p class="description">正常验收时只需要按场景自动填写的问题执行，不需要手工修改。</p>
			</div>

			<details class="wpaic-advanced-details">
				<summary><strong>高级测试参数</strong> <span>正常 Final Validation 不需要修改</span></summary>
				<div class="wpaic-advanced-body">
					<div class="wpaic-retrieval-options">
						<label for="wpaic-grounding-candidate-limit"><strong>Candidate Limit</strong></label>
						<input id="wpaic-grounding-candidate-limit" type="number" min="20" max="200" step="10" value="100" class="small-text">
						<label for="wpaic-grounding-top-k"><strong>Top K</strong></label>
						<select id="wpaic-grounding-top-k"><option value="3">3</option><option value="5" selected>5</option><option value="10">10</option></select>
					</div>
					<h4>Usage Context</h4>
					<p class="description">Final Validation 使用独立 Key，不影响前两轮计数。原始 Key 不写入 Counter Table，持久化的是 HMAC-SHA256 Hash。</p>
					<div class="wpaic-usage-fields">
						<label><strong>Conversation Key</strong><input type="text" id="wpaic-grounding-conversation-key" class="regular-text" value="grounded-stage3-final-conversation"></label>
						<label><strong>Visitor Key</strong><input type="text" id="wpaic-grounding-visitor-key" class="regular-text" value="grounded-stage3-final-visitor"></label>
						<label><strong>Site Key</strong><input type="text" id="wpaic-grounding-site-key" class="regular-text" value="grounded-stage3-final-site"></label>
					</div>
				</div>
			</details>

			<div id="wpaic-grounding-result" class="wpaic-result" aria-live="polite"></div>
		</div>

		<aside class="wpaic-card">
			<h2>当前环境</h2>
			<ul class="wpaic-plain-list">
				<li>Round 1 — Boundary / Reset / Local Day：<strong>Passed ✅</strong></li>
				<li>Round 2 — Isolation / Precedence：<strong>Passed ✅</strong></li>
				<li>Final Operational Validation：<strong>Passed ✅</strong></li>
			</ul>
			<hr>
			<p class="description">Provider：<strong><?php echo esc_html( $provider_name ); ?></strong></p>
			<p class="description">Model：<code><?php echo esc_html( $model_name ? $model_name : '—' ); ?></code></p>
			<p class="description">API Key：<?php echo $provider_configured ? '<strong>已配置</strong>' : '<strong>未配置</strong>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<p class="description">Usage Limits：Conversation <strong><?php echo esc_html( $usage_limits['conversation'] ); ?></strong> / Visitor <strong><?php echo esc_html( $usage_limits['visitor'] ); ?></strong> / Site <strong><?php echo esc_html( $usage_limits['site'] ); ?></strong></p>
			<p class="description">Active Knowledge Store Rows：<?php echo esc_html( number_format_i18n( $active_count ) ); ?></p>
			<hr>
			<p><strong>这一轮不测试：</strong></p>
			<p class="description">Chat UI、Lead、Human Handoff、Agent、Cost Guard、Embedding、Vector、RAG。</p>
		</aside>
	</div>
</div>
