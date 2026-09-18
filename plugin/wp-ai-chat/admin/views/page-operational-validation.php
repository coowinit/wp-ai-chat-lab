<?php
/** v0.9.0 Stage 3 Round 2 — Security & Data Boundary Validation — Passed / Sealed. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$table_ready = is_array( $inquiry_status ) && ! empty( $inquiry_status['table_exists'] );
?>
<div class="wrap wpaic-wrap">
	<h1>WP AI Chat Lab — 运营验收</h1>
	<p class="description">v0.9.0 Stage 3 · Round 2 — Security & Data Boundary Validation · Validation Passed / Sealed ✅。本页长期保留，只提供安全的验收入口与边界说明，不复制 Chat / Inquiry / Repository 业务逻辑。</p>

	<div class="wpaic-card-stack" style="margin-top:20px;">
		<div class="wpaic-card">
			<h2>Operational Preflight</h2>
			<div class="wpaic-flow-line"><code>Public Input</code><span>→</span><code>Validation</code><span>→</span><code>Persistence</code><span>→</span><code>Admin Output</code></div>
			<dl class="wpaic-meta wpaic-meta-source">
				<div><dt>Plugin</dt><dd><?php echo esc_html( WPAIC_VERSION ); ?></dd></div>
				<div><dt>DB Version</dt><dd><?php echo esc_html( WPAIC_DB_VERSION ); ?></dd></div>
				<div><dt>Inquiry Table</dt><dd><?php echo $table_ready ? 'Ready' : 'Not Ready'; ?></dd></div>
				<div><dt>Admin Capability</dt><dd><?php echo current_user_can( 'manage_options' ) ? 'manage_options ✅' : 'Unavailable'; ?></dd></div>
				<div><dt>Active</dt><dd><?php echo esc_html( (int) $view_counts['all'] ); ?></dd></div>
				<div><dt>Unread</dt><dd><?php echo esc_html( (int) $view_counts['unread'] ); ?></dd></div>
				<div><dt>Read</dt><dd><?php echo esc_html( (int) $view_counts['read'] ); ?></dd></div>
				<div><dt>Trash</dt><dd><?php echo esc_html( (int) $view_counts['trash'] ); ?></dd></div>
				<div><dt>WP Timezone</dt><dd><?php echo esc_html( $timezone_string ); ?></dd></div>
				<div class="wpaic-meta-wide"><dt>Chat REST</dt><dd><code><?php echo esc_html( $chat_endpoint ); ?></code></dd></div>
				<div class="wpaic-meta-wide"><dt>Inquiry REST</dt><dd><code><?php echo esc_html( $inquiry_endpoint ); ?></code></dd></div>
			</dl>
		</div>

		<div class="wpaic-card">
			<h2>本轮安全边界</h2>
			<ul class="wpaic-plain-list">
				<li><strong>详情自动已读：</strong>列表生成的详情链接现在携带每条 Inquiry 独立 nonce；缺失或伪造 nonce 仍可查看详情，但不能改变未读状态。</li>
				<li><strong>生命周期操作：</strong>移入回收站、恢复、永久删除、批量已读/未读、清空回收站继续要求 <code>manage_options + nonce</code>。</li>
				<li><strong>后台输出：</strong>Name / Email / Company / Phone / Message / Context 均经过 WordPress escaping 后输出。</li>
				<li><strong>搜索：</strong>仅 Name / Email / Company / Message；LIKE 参数使用 <code>esc_like()</code> + prepared query。</li>
				<li><strong>Public Inquiry：</strong>字段先做长度与格式校验，再 sanitize；Source URL 只接受本站 Host。</li>
				<li><strong>隐私：</strong>数据库仍不保存原始 Visitor UUID、原始 IP、完整 Chat Transcript、Prompt 或 AI Answer。</li>
			</ul>
		</div>

		<div class="wpaic-card">
			<h2>Round 2 历史验收清单（已通过）</h2>
			<ol class="wpaic-plain-list">
				<li><strong>Auto-read nonce：</strong>先用“无 nonce 查看”打开同一条未读 Inquiry，返回后确认未读数量不变；再用正常受保护链接打开，确认未读 -1 / 已读 +1。</li>
				<li><strong>非法 ID：</strong>打开不存在的 Inquiry ID，应只显示“没有找到这条询盘”，不 Fatal、不修改任何数据。</li>
				<li><strong>XSS / HTML：</strong>在“询盘采集实验”用 <code>&lt;script&gt;alert(1)&lt;/script&gt;&lt;b&gt;报价&lt;/b&gt;</code> 等输入提交；后台不得执行脚本，标签应被清洗/转义。</li>
				<li><strong>外部 Source：</strong>把 Source URL 改成 <code>https://example.com/test</code>；数据库不得保存该外部 Host，只能回退到本站 Referer 或空值。</li>
				<li><strong>搜索边界：</strong>分别搜索中文、<code>%</code>、<code>_</code>、单引号等字符；不得出现 SQL 错误，特殊字符按普通搜索输入处理。</li>
				<li><strong>组合回归：</strong>状态 + 来源 + 搜索 + 分页仍应正常组合，不影响 Trash / Bulk 行为。</li>
			</ol>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-chat-lab-inquiry-capture' ) ); ?>">打开询盘采集实验</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-chat-lab-inquiries' ) ); ?>">打开询盘管理</a>
			</p>
		</div>

		<div class="wpaic-card">
			<h2>安全测试入口</h2>
			<?php if ( is_array( $unread_sample ) ) : ?>
				<p>当前选择未读 Inquiry #<?php echo esc_html( (int) $unread_sample['id'] ); ?> 作为 Auto-read nonce 样本。请按顺序测试：</p>
				<p>
					<a class="button" href="<?php echo esc_url( $unprotected_read_url ); ?>">1. 无 nonce 查看（不应自动已读）</a>
					<a class="button button-primary" href="<?php echo esc_url( $protected_read_url ); ?>">2. 受保护详情（应自动已读）</a>
				</p>
			<?php else : ?>
				<p>当前没有未读 Inquiry。先提交一条测试 Inquiry，或把一条已读记录批量标记为未读，再回来测试 Auto-read nonce。</p>
			<?php endif; ?>
			<p><a class="button" href="<?php echo esc_url( $invalid_id_url ); ?>">测试不存在的 Inquiry ID</a></p>
		</div>

		<div class="wpaic-card">
			<h2>v0.9.0 封板边界 / Future Hardening</h2>
			<ul class="wpaic-plain-list">
				<li>不新增 CRM / Agent / Human Handoff。</li>
				<li>不改变 Inquiry Schema；DB Version 保持 1.3。</li>
				<li>不创建第二套测试 Repository 或假的 REST 路径。</li>
				<li>Dedicated DB 1.2 → 1.3 Migration Validation Lab 与 Inquiry Repository DB Failure Injection Lab 延后到后续 Hardening / Regression 版本，不阻塞 v0.9.0 Release。</li>
			</ul>
		</div>
	</div>
</div>
