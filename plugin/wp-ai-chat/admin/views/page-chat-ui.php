<?php
/** Permanent v0.8.0 Stage 2 Chat UI Lab. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap wpaic-wrap">
	<h1>WP AI Chat Lab — Chat UI Lab</h1>
	<p class="description">v0.8.0 Stage 2 — Simple Chat UI Integration · Validation Passed / Sealed ✅。该页面与 Chat Boundary 一样长期保留，用来控制、理解和回归测试正式前台 Widget。</p>
	<?php settings_errors( 'wpaic_chat_ui_messages' ); ?>

	<div class="wpaic-card">
		<h2>Stage 2 架构边界</h2>
		<div class="wpaic-flow-line"><code>simple-live-chat UI</code><span>→</span><code>Public REST</code><span>→</span><code>Chat Context</code><span>→</span><code>Grounded Answer Service</code><span>→</span><code>Usage Guard</code><span>→</span><code>Provider</code></div>
		<p><strong>前端 Widget 不拥有 AI 逻辑。</strong>原 simple-live-chat 的本地 <code>replies{}</code> 已移除；所有真实回答统一通过 Stage 1 已验证的 Public Chat Boundary。</p>
	</div>

	<div class="wpaic-grid">
		<div>
			<div class="wpaic-card">
				<h2>前台 Chat Widget</h2>
				<p>验证版本默认关闭，避免插件升级后立即影响正式访客。启用后，Widget 通过 <code>wp_footer</code> 注入前台，并只在启用时加载自己的 CSS / Vanilla JS。</p>
				<form method="post" action="options.php">
					<?php settings_fields( 'wpaic_chat_ui_settings_group' ); ?>
					<label class="wpaic-toggle-row">
						<input type="checkbox" name="<?php echo esc_attr( WPAIC_OPTION_CHAT_UI_SETTINGS ); ?>[enabled]" value="1" <?php checked( $chat_ui_enabled ); ?>>
						<strong>启用前台 Chat Widget</strong>
					</label>
					<p class="description">关闭只影响前台 Widget；Chat Boundary、Grounded AI、Usage Guard 等 Lab 页面和 Public REST Endpoint 都继续保留。</p>
					<?php submit_button( '保存 Chat UI 设置' ); ?>
				</form>
			</div>

			<div class="wpaic-card">
				<h2>Stage 2 验收路线 · Passed ✅</h2>
				<p><strong>建议：</strong>开启 Widget 后使用一个新的无痕窗口测试，避免 Stage 1 已存在的 Visitor Cookie / Daily Counter 干扰。Stage 2 重点验证 UI，不重复验证底层评分。</p>
				<ol class="wpaic-validation-list">
					<li><strong>打开 / 关闭：</strong>右下角按钮能打开 Panel，关闭后恢复 Launcher；仅开关窗口不应产生 AI 请求。</li>
					<li><strong>第一次 Strong：</strong>输入 <code>CWC-610 dimension</code>，显示用户气泡 → Typing → AI answer。</li>
					<li><strong>同一会话第二次 Strong：</strong>再次发送同一问题，应继续当前 Conversation，并再次得到 answer。</li>
					<li><strong>clarify / no_answer：</strong><code>dimension</code> 应显示澄清提示；<code>ZXQ-99999-NOMATCH</code> 应显示无可靠资料提示。</li>
					<li><strong>Conversation blocked：</strong>Conversation Limit = 2 时，第三次 Strong 应显示额度提示，输入框停止继续发送；“新会话”仍可用。</li>
					<li><strong>新会话：</strong>点击 Header 的“＋”后清空本标签页消息与 Conversation ID，但服务器 Visitor Cookie 保持不变。</li>
					<li><strong>刷新：</strong>同一标签页刷新后，本地 sessionStorage 中的 Conversation 与当前聊天气泡应恢复。</li>
					<li><strong>手机端：</strong>Panel 在窄屏下正常铺开，输入框、Quick Questions、关闭与新会话按钮可点击。</li>
				</ol>
				<div class="notice notice-success inline"><p><strong>真实前台验收已通过：</strong>answer / clarify / no_answer / blocked、新会话、刷新连续性、重复发送保护、断网恢复、慢网与手机端均已验证。该路线永久保留用于回归测试。</p></div>
				<div class="notice notice-info inline"><p><strong>Stage 3 只验证剩余边界：</strong>Provider Error、Visitor / Site Daily Limit 在正式 Widget 中的行为，以及 Public Endpoint Request Rate Limit / Abuse Boundary 等 Public Hardening。</p></div>
			</div>
		</div>

		<div>
			<div class="wpaic-card">
				<h2>当前状态</h2>
				<dl class="wpaic-meta wpaic-meta-source">
					<div><dt>Plugin</dt><dd><?php echo esc_html( WPAIC_VERSION ); ?></dd></div>
					<div><dt>DB Version</dt><dd><?php echo esc_html( WPAIC_DB_VERSION ); ?></dd></div>
					<div><dt>Widget</dt><dd><?php echo $chat_ui_enabled ? 'Enabled' : 'Disabled'; ?></dd></div>
					<div><dt>New Tables</dt><dd>0</dd></div>
					<div class="wpaic-meta-wide"><dt>REST Endpoint</dt><dd><code><?php echo esc_html( $chat_endpoint ); ?></code></dd></div>
					<div class="wpaic-meta-wide"><dt>Front Page</dt><dd><a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer">打开网站前台 ↗</a></dd></div>
				</dl>
			</div>
			<div class="wpaic-card">
				<h2>前端状态映射</h2>
				<ul class="wpaic-plain-list">
					<li><code>answer</code> → 正常 AI 气泡</li>
					<li><code>clarify</code> → 请用户补充信息</li>
					<li><code>no_answer</code> → 当前知识不足</li>
					<li><code>blocked</code> → 额度提示；当前 Conversation 停止继续发送</li>
					<li><code>error</code> → 临时错误提示</li>
				</ul>
				<p class="description">前台不会显示 Provider / Model / Token / Retrieval Score / Reason Code；这些诊断继续留在后台 Lab。</p>
			</div>
			<div class="wpaic-card">
				<h2>Stage 2 明确不做</h2>
				<ul class="wpaic-plain-list">
					<li>Conversation / Message 数据表</li>
					<li>后台聊天历史 / Inbox</li>
					<li>Lead / Human Handoff / Agent</li>
					<li>Streaming / RAG / Vector / Cost Guard</li>
					<li>Public Endpoint Request Rate Limit（Stage 3 Public Hardening）</li>
				</ul>
			</div>
		</div>
	</div>
</div>
