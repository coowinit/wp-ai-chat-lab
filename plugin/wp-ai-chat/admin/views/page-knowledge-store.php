<?php
/**
 * Knowledge Store diagnostics screen.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpaic-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Knowledge Store &amp; Lifecycle · Stage 2</p>

	<div class="wpaic-grid wpaic-store-summary-grid">
		<div class="wpaic-card">
			<h2>Knowledge Store Summary</h2>
			<div class="wpaic-store-stats">
				<div><span>Active</span><strong><?php echo esc_html( number_format_i18n( $store_summary['active'] ) ); ?></strong></div>
				<div><span>Inactive</span><strong><?php echo esc_html( number_format_i18n( $store_summary['inactive'] ) ); ?></strong></div>
				<div><span>Total</span><strong><?php echo esc_html( number_format_i18n( $store_summary['total'] ) ); ?></strong></div>
			</div>
			<p class="description">Stage 2 在单条 Sync 上验证 Eligibility Lifecycle：Active / Inactive / Reactivate。Full Sync 仍留到 Stage 3。</p>
		</div>

		<div class="wpaic-card">
			<h2>Stage 2 边界</h2>
			<ul class="wpaic-plain-list">
				<li>✓ Create / Unchanged / Update</li>
				<li>✓ Draft / Trash → inactive</li>
				<li>✓ Re-publish → reactivated</li>
				<li>✓ Disabled / Deleted → inactive</li>
				<li>✓ Inactive 保留最后 AI-visible Snapshot</li>
				<li>✓ 不调用 DeepSeek</li>
				<li>— Batch / Reconcile 留到 Stage 3</li>
				<li>— Incremental Hooks 留到 Stage 4</li>
				<li>— 不做 Retrieval / Chunk / Vector / RAG</li>
			</ul>
		</div>
	</div>

	<div class="wpaic-card wpaic-store-sync-card">
		<h2>Single-source Sync</h2>
		<p>输入一个 WordPress 内容 ID，执行单条 Lifecycle Sync。已发布且启用的 Source 会创建 / 更新 / 恢复；Draft、Trash、Disabled 或已删除 Source 会把已有 Store Row 软停用，并保留最后有效 Snapshot。</p>

		<div class="wpaic-preview-controls">
			<label for="wpaic-store-post-id"><strong>内容 ID</strong></label>
			<input id="wpaic-store-post-id" type="number" min="1" step="1" class="small-text" placeholder="2413">
			<button type="button" id="wpaic-sync-store-source" class="button button-primary">同步单条知识</button>
		</div>

		<div id="wpaic-store-sync-result" class="wpaic-result" aria-live="polite"></div>
	</div>

	<div class="wpaic-card">
		<h2>最近 Store Rows</h2>
		<?php if ( empty( $store_rows ) ) : ?>
			<p class="description">Knowledge Store 当前为空。先使用上方 Single-source Sync 创建第一条 Snapshot。</p>
		<?php else : ?>
			<table class="widefat striped wpaic-store-table">
				<thead>
					<tr>
						<th>Source ID</th>
						<th>Post Type</th>
						<th>Store</th>
						<th>Action</th>
						<th>Inactive Reason</th>
						<th>Hash</th>
						<th>Checked</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $store_rows as $row ) : ?>
					<tr>
						<td><code><?php echo esc_html( $row['source_id'] ); ?></code></td>
						<td><?php echo esc_html( $row['post_type'] ); ?> · ID <?php echo esc_html( $row['object_id'] ); ?></td>
						<td><?php echo esc_html( $row['store_status'] ); ?></td>
						<td><?php echo esc_html( $row['last_action'] ); ?></td>
						<td><?php echo esc_html( $row['inactive_reason'] ? $row['inactive_reason'] : '—' ); ?></td>
						<td><code class="wpaic-hash-short" title="<?php echo esc_attr( $row['source_hash'] ); ?>"><?php echo esc_html( substr( $row['source_hash'], 0, 12 ) ); ?>…</code></td>
						<td><?php echo esc_html( $row['last_checked_at'] ); ?> UTC</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
