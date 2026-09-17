<?php
/**
 * Knowledge Sources admin screen.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpaic-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Knowledge Store &amp; Lifecycle</p>

	<?php settings_errors( 'wpaic_knowledge_messages' ); ?>

	<div class="wpaic-card">
		<h2>知识来源</h2>
		<p>选择允许进入 AI Knowledge Source 管道的 WordPress 内容类型。默认全部关闭，只有管理员明确启用的内容类型才会被 Generic Extractor 使用。</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'wpaic_knowledge_settings_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( WPAIC_OPTION_KNOWLEDGE_SOURCES ); ?>[]" value="">

			<table class="widefat striped wpaic-source-table">
				<thead>
					<tr>
						<th class="check-column">启用</th>
						<th>内容类型</th>
						<th>Slug</th>
						<th>已发布</th>
						<th>最近内容</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $source_types ) ) : ?>
					<tr><td colspan="5">当前没有发现可用的公开内容类型。</td></tr>
				<?php else : ?>
					<?php foreach ( $source_types as $post_type => $object ) : ?>
						<?php
						$is_enabled = in_array( $post_type, $enabled_sources, true );
						$count      = $this->discovery->get_published_count( $post_type );
						$example    = isset( $source_examples[ $post_type ] ) ? $source_examples[ $post_type ] : array();
						?>
						<tr>
							<th scope="row" class="check-column">
								<input
									type="checkbox"
									name="<?php echo esc_attr( WPAIC_OPTION_KNOWLEDGE_SOURCES ); ?>[]"
									value="<?php echo esc_attr( $post_type ); ?>"
									<?php checked( $is_enabled ); ?>
								>
							</th>
							<td><strong><?php echo esc_html( $object->labels->singular_name ); ?></strong></td>
							<td><code><?php echo esc_html( $post_type ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
							<td>
								<?php if ( ! empty( $example ) ) : ?>
									<span><?php echo esc_html( $example['title'] ? $example['title'] : '(无标题)' ); ?> · ID <?php echo esc_html( $example['id'] ); ?></span>
									<?php if ( $is_enabled ) : ?>
										<button type="button" class="button-link wpaic-preview-example" data-post-id="<?php echo esc_attr( $example['id'] ); ?>">预览 AI 数据</button>
									<?php endif; ?>
								<?php else : ?>
									<span class="description">暂无已发布内容</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<?php submit_button( '保存知识来源' ); ?>
		</form>
	</div>

	<div class="wpaic-grid">
		<div class="wpaic-card">
			<h2>AI 补充知识</h2>
			<p>用于保存网站原有 Product、Solution、FAQ 等内容体系无法自然表达的业务规则、政策与流程。不要重复复制网站已有业务内容。</p>
			<p><strong>已发布：</strong><?php echo esc_html( number_format_i18n( $manual_count ) ); ?> 条</p>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . WPAIC_Manual_Knowledge::POST_TYPE ) ); ?>">管理 AI 补充知识</a>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . WPAIC_Manual_Knowledge::POST_TYPE ) ); ?>">添加补充知识</a>
			</p>
			<?php if ( ! empty( $manual_example ) ) : ?>
				<p class="description">最近：<?php echo esc_html( $manual_example['title'] ? $manual_example['title'] : '(无标题)' ); ?> · ID <?php echo esc_html( $manual_example['id'] ); ?> <button type="button" class="button-link wpaic-preview-example" data-post-id="<?php echo esc_attr( $manual_example['id'] ); ?>">预览 AI 数据</button></p>
			<?php endif; ?>
		</div>

		<div class="wpaic-card">
			<h2>v<?php echo esc_html( WPAIC_VERSION ); ?> 边界</h2>
			<p>v0.4.0 在现有 Source 基础上加入可重建 Knowledge Store；本页面仍只负责 Source 配置与 Preview。</p>
			<ul class="wpaic-plain-list">
				<li>✓ Source Preview 继续不调用 DeepSeek，也不直接持久化</li>
				<li>✓ Knowledge Store 使用独立派生表，不改变 WordPress Source of Truth</li>
				<li>✓ Generic 默认不读取任意 Post Meta</li>
				<li>✓ Legacy / WEM 仅通过显式 AI Allowlist 进入 Structured Data</li>
				<li>— Stage 1 只做 Single-source Store Sync</li>
				<li>— 不做 Chunk / Retrieval / RAG / Vector</li>
			</ul>
		</div>
	</div>

	<div class="wpaic-card wpaic-preview-card">
		<h2>Knowledge Source Preview</h2>
		<p>输入一个已启用内容类型的 WordPress ID，查看经过 Generic Extractor + Content Normalizer 后，AI 后续真正能够使用的数据。预览本身不会调用 DeepSeek，也不会保存 Source。</p>

		<div class="wpaic-preview-controls">
			<label for="wpaic-preview-post-id"><strong>内容 ID</strong></label>
			<input id="wpaic-preview-post-id" type="number" min="1" step="1" class="small-text" placeholder="123">
			<button type="button" id="wpaic-preview-source" class="button button-primary">预览 AI 数据</button>
		</div>

		<div id="wpaic-source-preview-result" class="wpaic-result" aria-live="polite"></div>
	</div>
</div>
