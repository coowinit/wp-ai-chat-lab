<?php
/**
 * v0.7.0 Stage 3 Usage Guard calibration playground.
 *
 * @var array<string,mixed> $usage_settings
 * @var string              $db_version
 * @var string              $usage_table
 * @var string              $wp_timezone
 * @var string              $wp_local_day
 * @var string              $wp_local_time
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$conversation_limit = isset( $usage_settings['conversation_limit'] ) ? absint( $usage_settings['conversation_limit'] ) : 10;
$visitor_limit      = isset( $usage_settings['visitor_daily_limit'] ) ? absint( $usage_settings['visitor_daily_limit'] ) : 20;
$site_limit         = isset( $usage_settings['site_daily_limit'] ) ? absint( $usage_settings['site_daily_limit'] ) : 200;
?>
<div class="wrap wpaic-wrap wpaic-usage-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Stage 3 — Limit Calibration &amp; Operational Validation</p>

	<div class="wpaic-grid">
		<section class="wpaic-card">
			<h2>Usage Guard Limits</h2>
			<p>Stage 3 不增加新的 Guard 类型，只校准现有 Conversation / Visitor / Site 三层边界。最终验收期间继续保持 <code>2 / 3 / 5</code>。</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'wpaic_usage_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="wpaic-conversation-limit">Conversation AI Call Limit</label></th><td><input id="wpaic-conversation-limit" type="number" min="0" class="small-text" name="<?php echo esc_attr( WPAIC_OPTION_USAGE_SETTINGS ); ?>[conversation_limit]" value="<?php echo esc_attr( $conversation_limit ); ?>"> <span class="description">conversation lifetime；0 = 不限制</span></td></tr>
					<tr><th scope="row"><label for="wpaic-visitor-limit">Visitor Daily AI Call Limit</label></th><td><input id="wpaic-visitor-limit" type="number" min="0" class="small-text" name="<?php echo esc_attr( WPAIC_OPTION_USAGE_SETTINGS ); ?>[visitor_daily_limit]" value="<?php echo esc_attr( $visitor_limit ); ?>"> <span class="description">WordPress local day；0 = 不限制</span></td></tr>
					<tr><th scope="row"><label for="wpaic-site-limit">Site Daily AI Call Limit</label></th><td><input id="wpaic-site-limit" type="number" min="0" class="small-text" name="<?php echo esc_attr( WPAIC_OPTION_USAGE_SETTINGS ); ?>[site_daily_limit]" value="<?php echo esc_attr( $site_limit ); ?>"> <span class="description">WordPress local day；0 = 不限制</span></td></tr>
				</table>
				<?php submit_button( '保存 Usage Limits' ); ?>
			</form>
		</section>

		<aside class="wpaic-card">
			<h2>Operational Snapshot</h2>
			<p><strong>DB Version:</strong> <?php echo esc_html( $db_version ); ?></p>
			<p><strong>Table:</strong> <code><?php echo esc_html( $usage_table ); ?></code></p>
			<p><strong>WordPress Timezone:</strong> <code><?php echo esc_html( $wp_timezone ); ?></code></p>
			<p><strong>Local Time:</strong> <code><?php echo esc_html( $wp_local_time ); ?></code></p>
			<p><strong>Daily Period Key:</strong> <code><?php echo esc_html( $wp_local_day ); ?></code></p>
			<p><strong>Scope Precedence:</strong> Conversation → Visitor → Site</p>
			<hr>
			<p class="description">Conversation 使用 <code>lifetime</code>；Visitor / Site 使用 WordPress 本地日期。Stage 3 不修改 DB Schema，DB Version 继续保持 1.1。</p>
		</aside>
	</div>

	<section class="wpaic-card wpaic-pass-banner">
		<div class="wpaic-pass-head">
			<div>
				<p class="wpaic-scene-kicker">已完成的验收</p>
				<h2>Stage 3 Round 2 — Validation Passed ✅</h2>
				<p>这一轮已经真实测试完成，<strong>现在不需要再重复操作下面的 6 个场景</strong>。当前应前往 <strong>Grounded AI</strong> 页面执行 Final Operational Validation。</p>
			</div>
			<span class="wpaic-pass-badge">PASSED</span>
		</div>
		<div class="wpaic-pass-grid">
			<div><strong>Conversation Isolation</strong><span>新会话不重置 Visitor / Site</span></div>
			<div><strong>Visitor Isolation</strong><span>新访客不重置 Site</span></div>
			<div><strong>Scope Precedence</strong><span>Conversation → Visitor → Site</span></div>
			<div><strong>Atomic BLOCK</strong><span>阻断后其他 Scope 不增加</span></div>
		</div>
	</section>

	<section class="wpaic-card wpaic-history-card">
		<details class="wpaic-history-details">
			<summary><strong>展开 Round 2 历史验收工具</strong> <span>仅复测 / 排查时使用</span></summary>
			<div class="wpaic-history-body">
				<h2>Round 2 历史场景测试</h2>
				<p class="description">以下工具保留用于复测。正常开发流程中，本轮已经 Passed，不需要再次执行。</p>

				<div class="wpaic-scope-meaning" aria-label="Usage Guard 三层 Scope 含义">
					<div><strong>Conversation</strong><span>当前聊天</span><small>重新开一个聊天 = 新 Conversation</small></div>
					<div><strong>Visitor</strong><span>当天同一访客</span><small>同一个人换聊天，Visitor 仍然相同</small></div>
					<div><strong>Site</strong><span>当天整个网站</span><small>换会话、换访客，Site 都继续累计</small></div>
				</div>

				<div class="wpaic-round2-goals">
					<p><strong>① Scope Isolation：</strong>换 Conversation 不能把 Visitor / Site 清零；换 Visitor 不能把 Site 清零。</p>
					<p><strong>② Scope Precedence：</strong>多个 Scope 同时达到上限时，固定按 <code>Conversation → Visitor → Site</code> 判断。</p>
				</div>

				<div class="wpaic-usage-presets">
					<h3>选择一个历史测试场景</h3>
					<p class="description">选择场景只会自动填写三个测试 Key，不会增加、删除或重置任何 Counter。</p>
					<div class="wpaic-usage-preset-buttons wpaic-scenario-grid">
						<button type="button" class="button wpaic-usage-preset wpaic-scenario-card is-active" data-title="场景 1：建立基准计数" data-summary="同一会话 / 同一访客 / 同一网站" data-change="这是第二轮测试的起点，不更换任何身份。" data-action="全选 Manual Lab Reset，然后模拟 2 次 Provider Call。" data-expect="完成后应为 C=2/2 · V=2/3 · S=2/5。" data-conversation="lab-stage3-r2-c-a" data-visitor="lab-stage3-r2-v-a" data-site="lab-stage3-r2-site-a"><strong>1. 建立基准计数</strong><span>同一会话 · 同一访客 · 同一网站</span></button>
						<button type="button" class="button wpaic-usage-preset wpaic-scenario-card" data-title="场景 2：同一访客，新会话" data-summary="只更换 Conversation；Visitor / Site 保持不变" data-change="相当于同一个访客关闭旧聊天，又打开了一个新聊天。" data-action="先点“检查 Usage Guard”；确认后再模拟 1 次 Provider Call。" data-expect="检查时应为 C=0/V=2/S=2；模拟后应为 C=1/V=3/S=3。" data-conversation="lab-stage3-r2-c-b" data-visitor="lab-stage3-r2-v-a" data-site="lab-stage3-r2-site-a"><strong>2. 同一访客，新会话</strong><span>只换 Conversation</span></button>
						<button type="button" class="button wpaic-usage-preset wpaic-scenario-card" data-title="场景 3：新访客，同一网站" data-summary="Conversation + Visitor 都更换；Site 保持不变" data-change="相当于另一位访客来到同一个网站，并开始自己的新聊天。" data-action="先点“检查 Usage Guard”；确认后连续模拟 2 次 Provider Call。" data-expect="检查时应为 C=0/V=0/S=3；两次模拟后应为 C=2/V=2/S=5。" data-conversation="lab-stage3-r2-c-c" data-visitor="lab-stage3-r2-v-b" data-site="lab-stage3-r2-site-a"><strong>3. 新访客，同一网站</strong><span>换 Conversation + Visitor</span></button>
						<button type="button" class="button wpaic-usage-preset wpaic-scenario-card" data-title="场景 4：Conversation 与 Site 同时满" data-summary="继续使用场景 3 的身份，不更换 Key" data-change="场景 3 完成后，Conversation 已 2/2，Site 已 5/5，两者同时达到上限。" data-action="再模拟 1 次 Provider Call。" data-expect="必须 BLOCK，Reason Code = conversation_limit_reached；三个 Counter 都不能增加。" data-conversation="lab-stage3-r2-c-c" data-visitor="lab-stage3-r2-v-b" data-site="lab-stage3-r2-site-a"><strong>4. Conversation + Site 同时满</strong><span>验证 Conversation 优先</span></button>
						<button type="button" class="button wpaic-usage-preset wpaic-scenario-card" data-title="场景 5：Visitor 与 Site 同时满" data-summary="新 Conversation；复用场景 1/2 已满的 Visitor 与 Site" data-change="会话是新的，但 Visitor 已 3/3、Site 已 5/5。" data-action="模拟 1 次 Provider Call。" data-expect="必须 BLOCK，Reason Code = visitor_daily_limit_reached；Conversation 保持 0，Site 不增加。" data-conversation="lab-stage3-r2-c-d" data-visitor="lab-stage3-r2-v-a" data-site="lab-stage3-r2-site-a"><strong>5. Visitor + Site 同时满</strong><span>验证 Visitor 优先于 Site</span></button>
						<button type="button" class="button wpaic-usage-preset wpaic-scenario-card" data-title="场景 6：只有 Site 已满" data-summary="Conversation / Visitor 都是新 Key；只复用已满的 Site" data-change="相当于一位全新访客进入一个今天已经用满全站 AI 额度的网站。" data-action="模拟 1 次 Provider Call。" data-expect="必须 BLOCK，Reason Code = site_daily_limit_reached；Conversation / Visitor 都保持 0。" data-conversation="lab-stage3-r2-c-e" data-visitor="lab-stage3-r2-v-c" data-site="lab-stage3-r2-site-a"><strong>6. 只有 Site 已满</strong><span>验证 Site 自己的阻断</span></button>
					</div>
				</div>

				<div class="wpaic-scene-guide" id="wpaic-usage-scene-guide" aria-live="polite">
					<p class="wpaic-scene-kicker">当前历史测试场景</p>
					<h3 id="wpaic-usage-scene-title">场景 1：建立基准计数</h3>
					<p class="wpaic-scene-summary" id="wpaic-usage-scene-summary">同一会话 / 同一访客 / 同一网站</p>
					<dl>
						<div><dt>发生了什么</dt><dd id="wpaic-usage-scene-change">这是第二轮测试的起点，不更换任何身份。</dd></div>
						<div><dt>现在怎么做</dt><dd id="wpaic-usage-scene-action">全选 Manual Lab Reset，然后模拟 2 次 Provider Call。</dd></div>
						<div><dt>预期结果</dt><dd id="wpaic-usage-scene-expect">完成后应为 C=2/2 · V=2/3 · S=2/5。</dd></div>
					</dl>
				</div>

				<h3 class="wpaic-subheading">当前测试身份</h3>
				<p class="description">选择场景后自动填写。原始 Key 不写入数据库，持久化的是 HMAC-SHA256 Hash。</p>
				<div class="wpaic-usage-fields">
					<label><strong>当前会话 Key <span>(Conversation)</span></strong><input type="text" id="wpaic-usage-conversation-key" class="regular-text" value="lab-stage3-r2-c-a"><small>代表“这一段聊天”</small></label>
					<label><strong>当前访客 Key <span>(Visitor)</span></strong><input type="text" id="wpaic-usage-visitor-key" class="regular-text" value="lab-stage3-r2-v-a"><small>代表“今天的这个访客”</small></label>
					<label><strong>当前网站 Key <span>(Site)</span></strong><input type="text" id="wpaic-usage-site-key" class="regular-text" value="lab-stage3-r2-site-a"><small>代表“今天的整个网站”</small></label>
				</div>
				<p><button type="button" class="button button-secondary" id="wpaic-usage-check">检查 Usage Guard</button> <button type="button" class="button button-primary" id="wpaic-usage-simulate">模拟 1 次 Provider Call</button></p>
				<div id="wpaic-usage-result" class="wpaic-result" aria-live="polite"></div>

				<hr class="wpaic-history-separator">
				<h3>Manual Lab Reset</h3>
				<p>只删除当前场景三个 Key 对应的计数，不会清空整张表。</p>
				<div class="wpaic-reset-scopes">
					<label><input type="checkbox" id="wpaic-reset-conversation" checked> Conversation</label>
					<label><input type="checkbox" id="wpaic-reset-visitor" checked> Visitor Today</label>
					<label><input type="checkbox" id="wpaic-reset-site" checked> Site Today</label>
				</div>
				<p><button type="button" class="button" id="wpaic-usage-reset">重置所选 Lab Counters</button></p>

				<hr class="wpaic-history-separator">
				<h3>历史测试路线</h3>
				<ol class="wpaic-stage3-checklist">
					<li>场景 1：Reset → 模拟 2 次 → <code>C=2 / V=2 / S=2</code></li>
					<li>场景 2：检查 <code>C=0 / V=2 / S=2</code> → 模拟 1 次 → <code>C=1 / V=3 / S=3</code></li>
					<li>场景 3：检查 <code>C=0 / V=0 / S=3</code> → 模拟 2 次 → <code>C=2 / V=2 / S=5</code></li>
					<li>场景 4：<code>conversation_limit_reached</code></li>
					<li>场景 5：<code>visitor_daily_limit_reached</code></li>
					<li>场景 6：<code>site_daily_limit_reached</code></li>
				</ol>
			</div>
		</details>
	</section>

	<section class="wpaic-card">
		<h2>Stage 3 当前验收状态</h2>
		<ol class="wpaic-stage3-checklist">
			<li><strong>Round 1 — Boundary / Reset / Local Day：</strong>Passed ✅</li>
			<li><strong>Round 2 — Scope Isolation / Precedence：</strong>Passed ✅</li>
			<li><strong>Final Operational Validation：</strong>Pending — 请到 Grounded AI 页面执行。</li>
		</ol>
		<p><strong>明确不包含：</strong>Chat UI、Lead、Human Handoff、Agent、Cost Guard、Embedding、Vector、RAG。</p>
	</section>
</div>
