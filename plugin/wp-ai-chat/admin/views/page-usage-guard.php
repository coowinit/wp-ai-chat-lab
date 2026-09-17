<?php
/**
 * v0.7.0 Stage 1 Usage Guard Playground.
 *
 * @var array<string,mixed> $usage_settings
 * @var string              $db_version
 * @var string              $usage_table
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$conversation_limit = isset( $usage_settings['conversation_limit'] ) ? absint( $usage_settings['conversation_limit'] ) : 10;
$visitor_limit      = isset( $usage_settings['visitor_daily_limit'] ) ? absint( $usage_settings['visitor_daily_limit'] ) : 20;
$site_limit         = isset( $usage_settings['site_daily_limit'] ) ? absint( $usage_settings['site_daily_limit'] ) : 200;
?>
<div class="wrap wpaic-wrap wpaic-usage-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Stage 1 — Usage Store &amp; Policy Foundation</p>

	<div class="wpaic-grid">
		<section class="wpaic-card">
			<h2>Usage Guard Limits</h2>
			<p>Stage 1 只建立 Usage Store、Scope Policy 与后台诊断；<strong>尚未接入真实 Grounded Answer Provider Boundary</strong>。</p>
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
			<h2>Stage 1 边界</h2>
			<p>✓ DB Version 1.1</p>
			<p>✓ Usage Counter Table</p>
			<p>✓ Conversation / Visitor / Site Scope</p>
			<p>✓ Hashed Scope Key</p>
			<p>✓ Fail Closed</p>
			<p>✓ Atomic Reservation Simulation</p>
			<p>— Stage 2 才接真实 Provider Boundary</p>
			<hr>
			<p><strong>DB Version:</strong> <?php echo esc_html( $db_version ); ?></p>
			<p><strong>Table:</strong> <code><?php echo esc_html( $usage_table ); ?></code></p>
		</aside>
	</div>

	<section class="wpaic-card wpaic-test-card">
		<h2>Usage Guard Playground</h2>
		<p>使用明确测试 Key 验证三层 Scope。原始 Key 不写入数据库，持久化的是 HMAC-SHA256 Hash。</p>
		<div class="wpaic-usage-fields">
			<label><strong>Conversation Key</strong><input type="text" id="wpaic-usage-conversation-key" class="regular-text" value="lab-conversation-1"></label>
			<label><strong>Visitor Key</strong><input type="text" id="wpaic-usage-visitor-key" class="regular-text" value="lab-visitor-1"></label>
			<label><strong>Site Key</strong><input type="text" id="wpaic-usage-site-key" class="regular-text" value="site"></label>
		</div>
		<p>
			<button type="button" class="button button-secondary" id="wpaic-usage-check">检查 Usage Guard</button>
			<button type="button" class="button button-primary" id="wpaic-usage-simulate">模拟 1 次 Provider Call</button>
		</p>
		<p class="description">“模拟 Provider Call”只做原子 Reservation +1，不调用 DeepSeek，不产生 Token。Stage 2 才把同一 Reserve 接到真实 Provider Boundary。</p>
		<div id="wpaic-usage-result" class="wpaic-result" aria-live="polite"></div>
	</section>
</div>
