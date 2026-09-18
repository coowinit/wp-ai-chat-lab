<?php
/** Permanent v0.8.0 Stage 3 Public Hardening Lab. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap wpaic-wrap">
	<h1>WP AI Chat Lab — Public Hardening</h1>
	<p class="description">v0.8.0 Stage 3 — Public Hardening &amp; Remaining Operational Boundaries。该页面长期保留，用来理解 Public REST 的滥用保护、Visitor / Site 限额与 Provider Failure Boundary。</p>
	<?php settings_errors( 'wpaic_public_guard_messages' ); ?>

	<div class="wpaic-card">
		<h2>Stage 3 架构边界</h2>
		<div class="wpaic-flow-line"><code>Public Request</code><span>→</span><code>Request Guard</code><span>→</span><code>Chat Context</code><span>→</span><code>Grounded Answer</code><span>→</span><code>Usage Guard</code><span>→</span><code>Provider</code></div>
		<p><strong>三层职责分开：</strong>Request Guard 控制公开 REST 请求频率；Usage Guard 控制允许跨越 Provider Boundary 的 AI Call；Provider Failure Lab 只用于管理员回归测试，不改变真实 Provider 设置。</p>
	</div>

	<div class="wpaic-card wpaic-stage-pass">
		<h2>Stage 3 — Validation Passed / Sealed ✅</h2>
		<p>Round 1 与 Round 2 均已通过真实 WordPress + 正式 Chat Widget 验收。该页面及所有 Guard / Reset / Failure Lab 工具继续永久保留，用于理解、诊断与版本回归。</p>
	</div>

	<div class="wpaic-card wpaic-stage-pass">
		<h2>Stage 3 Round 1 — Validation Passed ✅</h2>
		<div class="wpaic-status-grid">
			<div><strong>Visitor Daily Limit</strong><span>Passed</span></div>
			<div><strong>Site Daily Limit</strong><span>Passed</span></div>
			<div><strong>Request Rate Limit</strong><span>Passed</span></div>
			<div><strong>60s Auto Recovery</strong><span>Passed</span></div>
			<div><strong>Rate / Usage Isolation</strong><span>Passed</span></div>
		</div>
		<p class="description">Round 1 已通过真实 Chat Widget 验收。下面的 Guard 设置与 Reset 仍永久保留，供后续诊断与回归测试使用。</p>
	</div>

	<div class="wpaic-grid">
		<div class="wpaic-card-stack">
			<div class="wpaic-card">
				<h2>Public Request Guard</h2>
				<p>使用 60 秒固定窗口的短期 WordPress Transient。只保存 Visitor / IP 的哈希桶，不保存原始 IP；它是应用层第一道软保护，不替代 Cloudflare / WAF 的边缘限流。</p>
				<form method="post" action="options.php">
					<?php settings_fields( 'wpaic_public_guard_settings_group' ); ?>
					<p><label><input type="checkbox" name="<?php echo esc_attr( WPAIC_OPTION_PUBLIC_GUARD_SETTINGS ); ?>[enabled]" value="1" <?php checked( ! empty( $public_guard_settings['enabled'] ) ); ?>> <strong>启用 Public Request Guard</strong></label></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wpaic-public-visitor-limit">Visitor / Minute</label></th>
							<td><input id="wpaic-public-visitor-limit" type="number" min="0" max="1000" class="small-text" name="<?php echo esc_attr( WPAIC_OPTION_PUBLIC_GUARD_SETTINGS ); ?>[visitor_per_minute]" value="<?php echo esc_attr( (int) $public_guard_settings['visitor_per_minute'] ); ?>"> <span class="description">0 = 不限制；默认 20。</span></td>
						</tr>
						<tr>
							<th scope="row"><label for="wpaic-public-ip-limit">IP / Minute</label></th>
							<td><input id="wpaic-public-ip-limit" type="number" min="0" max="5000" class="small-text" name="<?php echo esc_attr( WPAIC_OPTION_PUBLIC_GUARD_SETTINGS ); ?>[ip_per_minute]" value="<?php echo esc_attr( (int) $public_guard_settings['ip_per_minute'] ); ?>"> <span class="description">0 = 不限制；默认 0（关闭）。仅在确认 REMOTE_ADDR 是真实访客 IP 时再启用；只保存哈希。</span></td>
						</tr>
					</table>
					<?php submit_button( '保存 Public Guard 设置' ); ?>
				</form>
				<div class="notice notice-warning inline"><p><strong>边界：</strong>Transient Rate Limit 是 best-effort，不用于账单或精确配额；高流量生产站应继续在 Cloudflare / WAF 配置边缘限流。</p></div>
			</div>

			<div class="wpaic-card">
				<h2>Stage 3 Round 2 — Provider Failure Boundary · Validation Passed ✅</h2>
				<p>这一轮不破坏 API Key，也不改 DeepSeek Endpoint。管理员只为<strong>当前 Visitor</strong>武装一次短期、一次性的 Provider Timeout；它只有在请求真正通过 Grounding 与 Usage Guard、进入 AI Manager 时才会被消费。</p>
				<div class="wpaic-flow-line"><code>Strong</code><span>→</span><code>Usage ALLOW / Reserve</code><span>→</span><code>Injected Provider Failure</code><span>→</span><code>HTTP 503</code><span>→</span><code>Widget error</code></div>
				<ol class="wpaic-validation-list">
					<li><strong>Weak 不消费 Failure：</strong>武装后发送 <code>dimension</code>，真实 Widget 正常 <code>clarify</code>；Failure 保持 Armed，Usage / Token 均不增加。✅</li>
					<li><strong>Strong 命中 Failure：</strong>在 Arm 有效期内立即发送 <code>CWC-610 dimension</code>，Strong 通过 Grounding + Usage Reserve 后命中模拟 timeout；Widget 显示临时不可用。✅</li>
					<li><strong>Accounting：</strong>失败尝试计入 Visitor / Site Provider Calls，但失败请求 Token 保持 0。✅</li>
					<li><strong>One-shot：</strong>Failure 命中后自动回到 Idle，不污染后续请求。✅</li>
					<li><strong>恢复：</strong>不重新武装再次发送同一 Strong，真实 Provider 恢复正常 <code>answer</code>；Calls 继续累计，Token 只在成功响应后增加（本次真实验收为 580）。✅</li>
					<li><strong>TTL：</strong>180 秒自动过期行为已确认；过期后请求安全回到正常 Provider 路径。✅</li>
				</ol>
				<div class="notice notice-info inline"><p><strong>为什么失败也占 1 次 Provider Call？</strong>Usage Guard 统计的是“已获准跨越 Provider Boundary 的尝试”。真实 timeout 时无法可靠知道上游是否已收到/处理请求，因此采用保守计数；Token 只有拿到可信成功响应后才累计。</p></div>
			</div>
		</div>

		<div class="wpaic-card-stack">
			<div class="wpaic-card">
				<h2>当前 Public 状态</h2>
				<dl class="wpaic-meta wpaic-meta-source">
					<div class="wpaic-meta-wide"><dt>REST Endpoint</dt><dd><code><?php echo esc_html( $chat_endpoint ); ?></code></dd></div>
					<div><dt>Request Guard</dt><dd><?php echo ! empty( $rate_snapshot['enabled'] ) ? 'Enabled' : 'Disabled'; ?></dd></div>
					<div><dt>Window</dt><dd><?php echo esc_html( (int) $rate_snapshot['window_seconds'] ); ?>s</dd></div>
					<div><dt>Visitor Cookie</dt><dd><?php echo esc_html( $rate_snapshot['visitor_cookie'] ); ?></dd></div>
					<div><dt>Visitor Rate</dt><dd><?php echo esc_html( (int) $rate_snapshot['visitor_used'] ); ?> / <?php echo esc_html( (int) $rate_snapshot['visitor_limit'] ); ?></dd></div>
					<div><dt>IP Rate</dt><dd><?php echo esc_html( (int) $rate_snapshot['ip_used'] ); ?> / <?php echo esc_html( (int) $rate_snapshot['ip_limit'] ); ?></dd></div>
					<div><dt>Visitor Daily</dt><dd><?php echo esc_html( (int) $usage_states['visitor']['used'] ); ?> / <?php echo esc_html( (int) $usage_states['visitor']['limit'] ); ?></dd></div>
					<div><dt>Visitor Tokens</dt><dd><?php echo esc_html( (int) $usage_states['visitor']['total_tokens'] ); ?></dd></div>
					<div><dt>Site Daily</dt><dd><?php echo esc_html( (int) $usage_states['site']['used'] ); ?> / <?php echo esc_html( (int) $usage_states['site']['limit'] ); ?></dd></div>
					<div><dt>Site Tokens</dt><dd><?php echo esc_html( (int) $usage_states['site']['total_tokens'] ); ?></dd></div>
				</dl>
				<p class="description">该页不会显示 Visitor UUID 或原始 IP。Visitor 状态来自当前浏览器 Cookie；如在无痕窗口测试，请在对应浏览器会话中理解其状态。</p>
			</div>

			<div class="wpaic-card">
				<h2>Provider Failure Lab</h2>
				<dl class="wpaic-meta">
					<div><dt>Status</dt><dd><strong><?php echo ! empty( $provider_failure_status['armed'] ) ? 'Armed' : 'Idle'; ?></strong></dd></div>
					<div><dt>Mode</dt><dd><code><?php echo esc_html( ! empty( $provider_failure_status['mode'] ) ? $provider_failure_status['mode'] : '—' ); ?></code></dd></div>
					<div><dt>Expires In</dt><dd><?php echo esc_html( (int) $provider_failure_status['expires_in'] ); ?>s</dd></div>
				</dl>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpaic-inline-form">
					<input type="hidden" name="action" value="wpaic_provider_failure_lab">
					<input type="hidden" name="operation" value="arm_timeout">
					<?php wp_nonce_field( 'wpaic_provider_failure_lab' ); ?>
					<?php submit_button( '武装下一次 Provider Timeout', 'primary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpaic-inline-form">
					<input type="hidden" name="action" value="wpaic_provider_failure_lab">
					<input type="hidden" name="operation" value="clear">
					<?php wp_nonce_field( 'wpaic_provider_failure_lab' ); ?>
					<?php submit_button( '清除 Failure Test', 'secondary', 'submit', false ); ?>
				</form>
				<p class="description">只对与当前后台共享同一个 <code>wpaic_visitor</code> Cookie 的前台 Visitor 生效；一次性消费，180 秒自动过期。它不会修改 API Key、Provider Endpoint 或正式配置。</p>
			</div>

			<div class="wpaic-card">
				<h2>安全测试重置</h2>
				<p>只重置当前站点 / 当前浏览器对应的测试状态，不清空整张表。</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpaic-inline-form">
					<input type="hidden" name="action" value="wpaic_public_hardening_reset">
					<input type="hidden" name="target" value="visitor_usage">
					<?php wp_nonce_field( 'wpaic_public_hardening_reset' ); ?>
					<?php submit_button( '重置当前 Visitor Today', 'secondary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpaic-inline-form">
					<input type="hidden" name="action" value="wpaic_public_hardening_reset">
					<input type="hidden" name="target" value="site_usage">
					<?php wp_nonce_field( 'wpaic_public_hardening_reset' ); ?>
					<?php submit_button( '重置当前 Site Today', 'secondary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpaic-inline-form">
					<input type="hidden" name="action" value="wpaic_public_hardening_reset">
					<input type="hidden" name="target" value="request_rate">
					<?php wp_nonce_field( 'wpaic_public_hardening_reset' ); ?>
					<?php submit_button( '重置当前 Request Rate', 'secondary', 'submit', false ); ?>
				</form>
				<p class="description">Visitor 重置只对与当前后台浏览器共享同一个 <code>wpaic_visitor</code> Cookie 的前台测试有效；Site 重置始终针对当前 WordPress Site。</p>
			</div>

			<div class="wpaic-card">
				<h2>Stage 3 明确不做</h2>
				<ul class="wpaic-plain-list">
					<li>新增 Conversation / Message 数据表</li>
					<li>把 Request Rate 写进 Provider Usage Counter</li>
					<li>修改真实 API Key 或 DeepSeek Endpoint 来制造故障</li>
					<li>Lead / Human Handoff / Agent</li>
					<li>RAG / Vector / Cost Guard</li>
					<li>用插件 Rate Limit 取代 Cloudflare / WAF</li>
				</ul>
			</div>
		</div>
	</div>
</div>
