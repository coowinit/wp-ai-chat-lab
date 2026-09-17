<?php
/**
 * Admin provider settings and minimal test UI.
 *
 * @var WPAIC_AI_Provider_Interface $provider
 * @var array<string,mixed>         $settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$models          = $provider->get_models();
$current_model   = isset( $settings['model'] ) && isset( $models[ $settings['model'] ] ) ? $settings['model'] : WPAIC_DeepSeek_Provider::DEFAULT_MODEL;
$key_source      = method_exists( $provider, 'get_api_key_source' ) ? $provider->get_api_key_source() : 'none';
$has_stored_key  = 'option' === $key_source;
$constant_key    = 'constant' === $key_source;
?>
<div class="wrap wpaic-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · AI Provider + Knowledge Source + Structured Source Validation</p>

	<?php settings_errors( 'wpaic_settings_messages' ); ?>

	<div class="wpaic-grid">
		<section class="wpaic-card">
			<h2>AI 设置</h2>
			<p>AI Provider Foundation 来自 v0.2.0：当前只接入 DeepSeek，但代码已经通过 Provider Interface 与 AI Manager 解耦。</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'wpaic_settings_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( WPAIC_OPTION_SETTINGS ); ?>[provider]" value="deepseek">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Provider</th>
						<td><strong><?php echo esc_html( $provider->get_name() ); ?></strong></td>
					</tr>

					<tr>
						<th scope="row"><label for="wpaic-api-key">API Key</label></th>
						<td>
							<?php if ( $constant_key ) : ?>
								<input id="wpaic-api-key" type="password" class="regular-text" value="Configured in wp-config.php" disabled>
								<p class="description"><strong>已通过 <code>WPAIC_DEEPSEEK_API_KEY</code> 配置。</strong> wp-config.php 优先于数据库设置。</p>
							<?php else : ?>
								<input id="wpaic-api-key" type="password" class="regular-text" name="<?php echo esc_attr( WPAIC_OPTION_SETTINGS ); ?>[api_key]" value="" autocomplete="new-password" placeholder="<?php echo $has_stored_key ? esc_attr( '已保存 API Key（留空则保持不变）' ) : esc_attr( 'sk-...' ); ?>">
								<p class="description">为了安全，已保存的 Key 不会重新输出到页面 HTML。</p>
								<?php if ( $has_stored_key ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( WPAIC_OPTION_SETTINGS ); ?>[clear_api_key]" value="1"> 清除数据库中已保存的 API Key</label>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="wpaic-model">Model</label></th>
						<td>
							<select id="wpaic-model" name="<?php echo esc_attr( WPAIC_OPTION_SETTINGS ); ?>[model]">
								<?php foreach ( $models as $model_id => $model_label ) : ?>
									<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $current_model, $model_id ); ?>><?php echo esc_html( $model_label . ' (' . $model_id . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">当前只暴露官方推荐的 <code>deepseek-flash</code>。Thinking 在本版本测试请求中明确关闭。</p>
						</td>
					</tr>
				</table>

				<?php submit_button( '保存设置' ); ?>
			</form>
		</section>

		<section class="wpaic-card">
			<h2>连接测试</h2>
			<p>只发送极短测试消息，不发送 WordPress 内容、产品数据或知识库内容。</p>
			<p><button type="button" class="button button-secondary wpaic-ajax-button" id="wpaic-test-connection">测试连接</button></p>
			<div id="wpaic-connection-result" class="wpaic-result" aria-live="polite"></div>
		</section>
	</div>

	<section class="wpaic-card wpaic-test-card">
		<h2>最小 AI 测试</h2>
		<p>这个测试只验证 <code>WordPress → AI Manager → DeepSeek Provider → DeepSeek API → Unified Response</code>。它不是后续的 Knowledge Playground。</p>

		<label for="wpaic-test-question"><strong>测试问题</strong></label>
		<textarea id="wpaic-test-question" rows="5" maxlength="1000" placeholder="例如：请只回复：测试成功"></textarea>
		<p class="description">最多 1000 个字符，避免测试阶段误贴长文产生不必要的 Token。</p>
		<p><button type="button" class="button button-primary wpaic-ajax-button" id="wpaic-test-chat">发送测试</button></p>
		<div id="wpaic-chat-result" class="wpaic-result" aria-live="polite"></div>
	</section>

	<section class="wpaic-card">
		<h2>AI Provider 边界</h2>
		<p>这一页继续只负责 Provider 设置和最小 AI 测试。v0.3.1 的 Structured Source Validation 位于“知识来源”菜单中，并且不会在知识预览阶段调用 DeepSeek。</p>
	</section>
</div>
