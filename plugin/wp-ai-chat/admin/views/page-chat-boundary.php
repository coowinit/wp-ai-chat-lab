<?php
/** v0.8.0 Stage 1 Public Chat Boundary Playground. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$transactional = ! empty( $usage_status['transactional'] );
?>
<div class="wrap wpaic-wrap">
	<h1>WP AI Chat Lab — Chat Boundary</h1>
	<p class="description">v0.8.0 Stage 1 — Public Chat Boundary Foundation · Validation Passed / Sealed。该页面作为永久 Lab / 回归工具保留；Stage 2 Chat UI 与 Stage 3 Public Hardening 也已完成验收并封板，v0.8.0 Final Review 已通过。</p>

	<div class="wpaic-card">
		<h2>Stage 1 架构边界</h2>
		<div class="wpaic-flow-line"><code>Public REST</code><span>→</span><code>Chat Context</code><span>→</span><code>Grounded Answer Service</code><span>→</span><code>Grounding</code><span>→</span><code>Usage Guard</code><span>→</span><code>Provider</code></div>
		<p><strong>Chat Controller 不拥有 AI 逻辑。</strong>它只负责输入、匿名身份、调用现有 Service，并把内部结果翻译成 <code>answer / clarify / no_answer / blocked / error</code>。</p>
	</div>

	<div class="wpaic-grid">
		<div class="wpaic-card wpaic-test-card">
			<h2>Public Chat Playground <span class="wpaic-pass-badge">Passed / Sealed ✓</span></h2>
			<p>这里调用的是真实公开端点，不走管理员专用 AJAX。Visitor 由服务器创建并保存在 HttpOnly Cookie；Site Context 完全由服务器生成。</p>
			<div class="notice notice-info inline"><p><strong>永久保留：</strong>这是正式 Public Chat 的诊断与回归入口。后续接入 simple-live-chat 后，该 Playground 仍保留并继续调用同一个 Public REST / 核心 Service。</p></div>
			<p class="description"><strong>建议验收时继续使用 Conversation 2 / Visitor 3 / Site 5。</strong>按 1 → 7 连续执行，中间不要手工修改 Conversation ID。</p>
			<div class="wpaic-chat-scenarios">
				<button type="button" class="button wpaic-chat-preset is-active" data-question="CWC-610 dimension" data-new-conversation="1" data-title="1. 首次匿名请求 → 正常 AI 回答" data-change="Conversation ID 留空；服务器创建新的 Conversation。若浏览器还没有 wpaic_visitor Cookie，也会创建 Visitor。" data-action="点击“发送到 Public Chat Endpoint”。" data-expect="HTTP 200；type = answer；Conversation = created；干净浏览器下 Visitor = created；返回 AI 答案，但不暴露 Provider / Model / Token / Score。">1. 首次明确问题</button>
				<button type="button" class="button wpaic-chat-preset" data-question="CWC-610 dimension" data-title="2. 同一会话第二次 → 身份继续" data-change="保留上一步服务器返回的 Conversation ID；Visitor Cookie 也由浏览器自动携带。" data-action="直接再次发送。" data-expect="HTTP 200；type = answer；Conversation = existing；Visitor = existing。若限额为 2 / 3 / 5，本次后 Conversation 达到 2 / 2。">2. 同一会话继续</button>
				<button type="button" class="button wpaic-chat-preset" data-question="dimension" data-title="3. 问题太模糊 → clarify" data-change="问题只有 dimension，Grounding 应判断为 Weak。" data-action="保持同一 Conversation，点击发送。" data-expect="HTTP 200；type = clarify；不调用 Provider，因此不会增加 AI Usage。">3. Weak / 模糊问题</button>
				<button type="button" class="button wpaic-chat-preset" data-question="CWC-610 warranty" data-title="4. 资料不够明确 → clarify" data-change="知道产品 CWC-610，但 warranty 证据只达到 Medium。" data-action="保持同一 Conversation，点击发送。" data-expect="HTTP 200；type = clarify；不调用 Provider，Usage 继续保持不变。">4. Medium / 资料不足</button>
				<button type="button" class="button wpaic-chat-preset" data-question="ZXQ-99999-NOMATCH" data-title="5. 完全没有资料 → no_answer" data-change="Knowledge Store 中没有对应候选。" data-action="保持同一 Conversation，点击发送。" data-expect="HTTP 200；type = no_answer；不调用 Provider，不消耗 AI Usage。">5. None / 无资料</button>
				<button type="button" class="button wpaic-chat-preset" data-question="CWC-610 dimension" data-title="6. 有可靠知识，但额度已满 → blocked" data-change="前两次 Strong 已让当前 Conversation 达到 2 / 2；现在再次提出 Strong 问题。" data-action="点击发送。" data-expect="HTTP 200；type = blocked；Provider 不调用。Public Response 只给用户友好信息，不暴露内部 reason_code。">6. 超过会话额度</button>
				<button type="button" class="button wpaic-chat-preset" data-question="CWC-610 dimension" data-invalid-conversation="1" data-title="7. 非法 Conversation ID → Public Error" data-change="故意把 Conversation ID 改成 invalid-conversation-id。" data-action="点击发送。" data-expect="HTTP 400；success = No；type = error；请求在进入 Grounded Answer Service 前被拒绝。">7. 非法 Conversation ID</button>
			</div>

			<div class="wpaic-card wpaic-chat-scene-guide">
				<h3 id="wpaic-chat-scene-title">1. 首次匿名请求 → 正常 AI 回答</h3>
				<p><strong>发生了什么：</strong><span id="wpaic-chat-scene-change">Conversation ID 留空；服务器创建新的 Conversation。若浏览器还没有 wpaic_visitor Cookie，也会创建 Visitor。</span></p>
				<p><strong>现在怎么做：</strong><span id="wpaic-chat-scene-action">点击“发送到 Public Chat Endpoint”。</span></p>
				<p><strong>预期结果：</strong><span id="wpaic-chat-scene-expect">HTTP 200；type = answer；Conversation = created；干净浏览器下 Visitor = created；返回 AI 答案，但不暴露 Provider / Model / Token / Score。</span></p>
			</div>
			<p><label for="wpaic-chat-boundary-question"><strong>Question</strong></label></p>
			<textarea id="wpaic-chat-boundary-question" rows="4">CWC-610 dimension</textarea>
			<p><label for="wpaic-chat-boundary-conversation"><strong>Conversation ID</strong></label></p>
			<input id="wpaic-chat-boundary-conversation" class="regular-text" type="text" value="" placeholder="留空：由服务器创建新的 Conversation UUID">
			<p class="description">第一次留空；服务器返回 UUID 后，本页面会自动写回这里。后续保持不变就是“同一会话”。</p>
			<p class="wpaic-preview-controls">
				<button type="button" class="button button-primary" id="wpaic-chat-boundary-send">发送到 Public Chat Endpoint</button>
				<button type="button" class="button" id="wpaic-chat-boundary-new">开始新会话（清空 Conversation ID）</button>
			</p>
			<div id="wpaic-chat-boundary-result" class="wpaic-result" aria-live="polite"></div>
		</div>

		<div class="wpaic-card-stack">
			<div class="wpaic-card">
				<h2>Operational Preflight</h2>
				<dl class="wpaic-meta wpaic-meta-source">
					<div class="wpaic-meta-wide"><dt>REST Endpoint</dt><dd><code><?php echo esc_html( $chat_endpoint ); ?></code></dd></div>
					<div><dt>Plugin Version</dt><dd><?php echo esc_html( WPAIC_VERSION ); ?></dd></div>
					<div><dt>DB Version</dt><dd><?php echo esc_html( WPAIC_DB_VERSION ); ?></dd></div>
					<div><dt>Usage Engine</dt><dd><?php echo esc_html( $usage_status['engine'] ? $usage_status['engine'] : 'Unknown' ); ?></dd></div>
					<div><dt>Transactional</dt><dd><?php echo $transactional ? 'Yes' : 'No'; ?></dd></div>
					<div><dt>WP Timezone</dt><dd><?php echo esc_html( $wp_timezone ); ?></dd></div>
					<div><dt>Conversation Limit</dt><dd><?php echo esc_html( $usage_limits['conversation'] ); ?></dd></div>
					<div><dt>Visitor Daily</dt><dd><?php echo esc_html( $usage_limits['visitor'] ); ?></dd></div>
					<div><dt>Site Daily</dt><dd><?php echo esc_html( $usage_limits['site'] ); ?></dd></div>
				</dl>
				<?php if ( ! $transactional ) : ?>
					<div class="notice notice-error inline"><p><strong>Public Provider Call 已 Fail Closed。</strong>Usage Counter 必须使用 InnoDB，当前不会允许公开 Chat 越过 Provider Boundary。</p></div>
				<?php else : ?>
					<div class="notice notice-success inline"><p><strong>事务前置条件正常。</strong>Reservation 可以使用 transaction + row lock。</p></div>
				<?php endif; ?>
			</div>
			<div class="wpaic-card">
				<h2>Stage 1 明确不做</h2>
				<ul class="wpaic-plain-list">
					<li>Stage 1 本身不拥有正式 Chat Widget；Stage 2 已独立接入 simple-live-chat UI</li>
					<li>Conversation / Message 数据表</li>
					<li>聊天历史、后台会话列表</li>
					<li>Lead / Human Handoff / Agent</li>
					<li>Streaming / RAG / Vector / Cost Guard</li>
					<li>Public Endpoint Request Rate Limit（记录为后续 Public Hardening）</li>
				</ul>
			</div>
		</div>
	</div>
</div>
