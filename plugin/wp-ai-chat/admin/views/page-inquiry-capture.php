<?php
/** Permanent v0.9.0 Stage 1 Round 2 Inquiry Capture Lab. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap wpaic-wrap">
	<h1>WP AI Chat Lab — Inquiry Capture Lab</h1>
	<p class="description">v0.9.0 Stage 1 · Round 2 — Inquiry Capture Foundation Validation Passed / Sealed ✅。该页面长期保留，用真实 Public Inquiry REST 复测“提交 → 校验 → 防滥用 → 数据库写入”的完整边界。</p>
	<?php settings_errors( 'wpaic_inquiry_capture_messages' ); ?>

	<div class="wpaic-card">
		<h2>Round 2 — Validation Passed / Sealed ✅</h2>
		<div class="wpaic-flow-line"><code>POST /inquiry</code><span>→</span><code>Validation</code><span>→</span><code>Honeypot / Rate</code><span>→</span><code>Service</code><span>→</span><code>Repository</code><span>→</span><code>wp_wpaic_inquiries</code></div>
		<p>Round 2 已通过真实 WordPress 验收。这里的表单继续直接调用公开 REST Endpoint，不走另一套后台保存逻辑；下一步 Round 3 才把同一 Endpoint 接入正式 Chat Inline Form。</p>
	</div>

	<div class="wpaic-grid">
		<div>
			<div class="wpaic-card">
				<h2>Public Inquiry Test</h2>
				<p>填写一条真实测试询盘。成功响应只有 <code>type + message</code>，不会向公开前端暴露数据库 ID、Visitor Hash 或其它内部字段。</p>
				<form id="wpaic-inquiry-capture-form" novalidate>
					<table class="form-table" role="presentation">
						<tr><th><label for="wpaic-inquiry-name">Name *</label></th><td><input id="wpaic-inquiry-name" class="regular-text" type="text" value="Round 2 Test Visitor"></td></tr>
						<tr><th><label for="wpaic-inquiry-email">Email *</label></th><td><input id="wpaic-inquiry-email" class="regular-text" type="email" value="round2@example.com"></td></tr>
						<tr><th><label for="wpaic-inquiry-company">Company</label></th><td><input id="wpaic-inquiry-company" class="regular-text" type="text" value="Example Company"></td></tr>
						<tr><th><label for="wpaic-inquiry-phone">Phone / WhatsApp</label></th><td><input id="wpaic-inquiry-phone" class="regular-text" type="text" value="+1 555 0100"></td></tr>
						<tr><th><label for="wpaic-inquiry-message">Message *</label></th><td><textarea id="wpaic-inquiry-message" class="large-text" rows="5">Please send me a quotation for CWC-610, approximately 500 sqm.</textarea></td></tr>
						<tr>
							<th><label for="wpaic-inquiry-trigger">Trigger Type</label></th>
							<td><select id="wpaic-inquiry-trigger">
								<option value="commercial_intent">commercial_intent</option>
								<option value="manual">manual</option>
								<option value="no_answer">no_answer</option>
								<option value="usage_blocked">usage_blocked</option>
								<option value="invalid_test_value">invalid_test_value（仅用于验证拒绝）</option>
							</select></td>
						</tr>
						<tr><th><label for="wpaic-inquiry-conversation">Conversation ID</label></th><td><input id="wpaic-inquiry-conversation" class="large-text code" type="text" value="<?php echo esc_attr( $test_conversation_id ); ?>"><p class="description">可留空；如果提供，必须是 UUID v4。</p></td></tr>
						<tr><th><label for="wpaic-inquiry-question">Last Question</label></th><td><textarea id="wpaic-inquiry-question" class="large-text" rows="3">Can I get a quote for 500 sqm CWC-610?</textarea></td></tr>
						<tr><th><label for="wpaic-inquiry-source">Source URL</label></th><td><input id="wpaic-inquiry-source" class="large-text code" type="url" value="<?php echo esc_attr( $test_source_url ); ?>"><p class="description">Controller 只接受本站 URL；外部 URL 会被忽略并尝试使用本站 Referer。</p></td></tr>
						<tr><th>Honeypot Test</th><td><label><input id="wpaic-inquiry-honeypot" type="checkbox"> 模拟 Bot 填写隐藏 website 字段（预期拒绝且不写库）</label></td></tr>
					</table>
					<?php submit_button( '提交真实 Public Inquiry', 'primary', 'submit', false, array( 'id' => 'wpaic-inquiry-submit' ) ); ?>
				</form>
				<div id="wpaic-inquiry-result" class="wpaic-result" hidden></div>
			</div>

			<div class="wpaic-card">
				<h2>最近 10 条 Inquiry（仅 Lab 验证）</h2>
				<p class="description">这里只用于确认 Repository 实际写入；不提供搜索、状态编辑、删除或正式运营管理，这些属于 Stage 2。</p>
				<?php if ( empty( $recent_inquiries ) ) : ?>
					<p>当前还没有 Inquiry 数据。</p>
				<?php else : ?>
					<div class="wpaic-store-table-wrap"><table class="widefat striped wpaic-store-table">
						<thead><tr><th>ID</th><th>Time</th><th>Status</th><th>Contact</th><th>Message</th><th>Context</th></tr></thead>
						<tbody>
						<?php foreach ( $recent_inquiries as $row ) : ?>
							<tr>
								<td><?php echo esc_html( (int) $row['id'] ); ?></td>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
								<td><code><?php echo esc_html( $row['status'] ); ?></code></td>
								<td><strong><?php echo esc_html( $row['name'] ); ?></strong><br><?php echo esc_html( $row['email'] ); ?><br><span class="description"><?php echo esc_html( $row['company'] ); ?><?php echo $row['phone'] ? ' · ' . esc_html( $row['phone'] ) : ''; ?></span></td>
								<td><?php echo esc_html( $row['message'] ); ?></td>
								<td><code><?php echo esc_html( $row['trigger_type'] ); ?></code><br><span class="description">Conversation: <?php echo esc_html( $row['conversation_id'] ? $row['conversation_id'] : '—' ); ?></span><br><span class="description">Visitor Hash: <?php echo esc_html( $row['visitor_hash'] ? substr( $row['visitor_hash'], 0, 12 ) . '…' : '—' ); ?></span><br><?php if ( $row['source_url'] ) : ?><a href="<?php echo esc_url( $row['source_url'] ); ?>" target="_blank" rel="noopener noreferrer">Source ↗</a><?php endif; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php endif; ?>
			</div>
		</div>

		<div>
			<div class="wpaic-card">
				<h2>当前状态</h2>
				<dl class="wpaic-meta wpaic-meta-source">
					<div><dt>Plugin</dt><dd><?php echo esc_html( WPAIC_VERSION ); ?></dd></div>
					<div><dt>DB Version</dt><dd><?php echo esc_html( WPAIC_DB_VERSION ); ?></dd></div>
					<div class="wpaic-meta-wide"><dt>REST Endpoint</dt><dd><code><?php echo esc_html( $inquiry_endpoint ); ?></code></dd></div>
					<div><dt>Inquiry Table</dt><dd><?php echo ! empty( $inquiry_status['table_exists'] ) ? 'Ready' : 'Missing'; ?></dd></div>
					<div><dt>Rows</dt><dd><?php echo esc_html( (int) $inquiry_status['row_count'] ); ?></dd></div>
					<div><dt>Visitor Rate</dt><dd><?php echo esc_html( (int) $inquiry_rate_state['used'] ); ?> / <?php echo esc_html( (int) $inquiry_rate_state['limit'] ); ?> per hour</dd></div>
				</dl>
			</div>

			<div class="wpaic-card">
				<h2>Rate Test Reset</h2>
				<p>Submission Rate 与 Chat Request Guard / AI Usage Guard 完全独立。这里只重置当前浏览器 Visitor 的 Inquiry Rate，不删除任何已保存 Inquiry。</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpaic_inquiry_rate_reset">
					<?php wp_nonce_field( 'wpaic_inquiry_rate_reset' ); ?>
					<?php submit_button( '重置当前 Visitor Inquiry Rate', 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<div class="wpaic-card">
				<h2>Round 2 历史验收清单（已通过）</h2>
				<ol class="wpaic-validation-list">
					<li><strong>正常提交：</strong>默认表单 → HTTP 201 / <code>submitted</code> → 刷新后 Rows +1。</li>
					<li><strong>必填校验：</strong>清空 Name / Email / Message → HTTP 400 → 不写库。</li>
					<li><strong>Email：</strong>非法邮箱 → HTTP 400 → 不写库。</li>
					<li><strong>Conversation：</strong>非法 UUID → HTTP 400 → 不写库。</li>
					<li><strong>Trigger：</strong>非 allowlist 值 → HTTP 400 → 不写库。</li>
					<li><strong>Honeypot：</strong>勾选模拟 Bot → HTTP 400 → 不写库，且不消耗 Inquiry Rate。</li>
					<li><strong>Rate：</strong>先点击右侧 Rate Reset，再连续提交 6 次；前 5 次允许，第 6 次 HTTP 429，Rows 不增加。</li>
					<li><strong>隐私：</strong>数据库只有 Visitor Hash，没有原始 Visitor UUID / IP / 完整 Conversation。</li>
				</ol>
			</div>

			<div class="wpaic-card">
				<h2>Round 2 封板边界</h2>
				<ul class="wpaic-plain-list">
					<li>不修改正式 Chat Widget</li>
					<li>不增加 Inline Inquiry Form</li>
					<li>不做正式 Inquiry List / Detail / Status 管理</li>
					<li>不保存完整 Chat Transcript / Prompt / AI Answer</li>
					<li>不保存原始 IP 或 Visitor UUID</li>
					<li>不增加 Turnstile / CRM / Human Handoff</li>
				</ul>
			</div>
		</div>
	</div>
</div>
