<?php
/**
 * Knowledge Store diagnostics screen.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$last_completed = ! empty( $last_full_sync['completed_at'] ) ? (string) $last_full_sync['completed_at'] : '';
$last_stats     = ! empty( $last_full_sync['stats'] ) && is_array( $last_full_sync['stats'] ) ? $last_full_sync['stats'] : array();
$last_incremental_completed = ! empty( $last_incremental_sync['completed_at'] ) ? (string) $last_incremental_sync['completed_at'] : '';
$last_incremental_action    = ! empty( $last_incremental_sync['action'] ) ? (string) $last_incremental_sync['action'] : '';
$last_incremental_source    = ! empty( $last_incremental_sync['source_id'] ) ? (string) $last_incremental_sync['source_id'] : '';
$last_incremental_object_id = ! empty( $last_incremental_sync['object_id'] ) ? absint( $last_incremental_sync['object_id'] ) : 0;
$last_incremental_reason    = ! empty( $last_incremental_sync['inactive_reason'] ) ? (string) $last_incremental_sync['inactive_reason'] : '';
$last_incremental_error     = ! empty( $last_incremental_sync['error_message'] ) ? (string) $last_incremental_sync['error_message'] : '';
?>
<div class="wrap wpaic-wrap">
	<h1>WP AI Chat Lab</h1>
	<p class="description">v<?php echo esc_html( WPAIC_VERSION ); ?> · Knowledge Store &amp; Lifecycle · Stage 4</p>

	<div class="wpaic-grid wpaic-store-summary-grid">
		<div class="wpaic-card">
			<h2>Knowledge Store Summary</h2>
			<div class="wpaic-store-stats wpaic-store-stats-four">
				<div><span>Active</span><strong><?php echo esc_html( number_format_i18n( $store_summary['active'] ) ); ?></strong></div>
				<div><span>Inactive</span><strong><?php echo esc_html( number_format_i18n( $store_summary['inactive'] ) ); ?></strong></div>
				<div><span>Total</span><strong><?php echo esc_html( number_format_i18n( $store_summary['total'] ) ); ?></strong></div>
				<div><span>Last Full Sync</span><strong class="wpaic-store-last-sync"><?php echo $last_completed ? esc_html( $last_completed ) : '—'; ?></strong></div>
			</div>
			<?php if ( $last_completed ) : ?>
				<p class="description">最近 Full Sync：<?php echo esc_html( $last_completed ); ?> UTC<?php if ( $last_stats ) : ?> · Processed <?php echo esc_html( isset( $last_stats['processed'] ) ? (int) $last_stats['processed'] : 0 ); ?> · Errors <?php echo esc_html( isset( $last_stats['errors'] ) ? (int) $last_stats['errors'] : 0 ); ?><?php endif; ?></p>
			<?php else : ?>
				<p class="description">尚未执行 Full Sync。Full Sync 用于初次建库与整站 Reconciliation。</p>
			<?php endif; ?>

			<?php if ( $last_incremental_completed ) : ?>
				<p class="description"><strong>最近 Incremental Sync：</strong><?php echo esc_html( $last_incremental_completed ); ?> UTC · ID <?php echo esc_html( $last_incremental_object_id ); ?> · <?php echo esc_html( $last_incremental_action ? $last_incremental_action : '—' ); ?><?php if ( $last_incremental_source ) : ?> · <?php echo esc_html( $last_incremental_source ); ?><?php endif; ?><?php if ( $last_incremental_reason ) : ?> · <?php echo esc_html( $last_incremental_reason ); ?><?php endif; ?><?php if ( $last_incremental_error ) : ?> · Error: <?php echo esc_html( $last_incremental_error ); ?><?php endif; ?></p>
			<?php else : ?>
				<p class="description">尚未记录 Incremental Sync。Stage 4 会在相关 WordPress Source 保存或永久删除后，只同步当前这一条 Source。</p>
			<?php endif; ?>
		</div>

		<div class="wpaic-card">
			<h2>Stage 4 边界</h2>
			<ul class="wpaic-plain-list">
				<li>✓ Stage 1 Store Foundation</li>
				<li>✓ Stage 2 Lifecycle</li>
				<li>✓ Stage 3 Batch Sync &amp; Reconciliation</li>
				<li>✓ Incremental Save Hook</li>
				<li>✓ Permanent Delete Hook</li>
				<li>✓ 只同步当前 Source，不触发 Full Sync</li>
				<li>✓ 不调用 DeepSeek</li>
				<li>— 不做 Retrieval / Chunk / Vector / RAG</li>
			</ul>
		</div>
	</div>

	<div class="wpaic-card wpaic-full-sync-card">
		<h2>Full Sync</h2>
		<p>把当前已启用、已发布的 Knowledge Source 分批同步到 Knowledge Store；所有 Batch 完成后，再 Reconcile Store 中仍为 active、但当前已删除、未发布或已禁用的旧 Row。Stage 4 日常保存已改为 Incremental Sync，Full Sync 继续用于初次建库与整站校准。</p>
		<p class="description">默认每批处理 20 条。同步过程可见，不使用 Cron / Queue。页面中断后，在锁未过期时再次点击可继续已有任务。</p>
		<p>
			<button type="button" id="wpaic-start-full-sync" class="button button-primary">同步全部知识</button>
		</p>
		<div id="wpaic-full-sync-result" class="wpaic-result" aria-live="polite"></div>
	</div>

	<div class="wpaic-card wpaic-store-sync-card">
		<h2>Single-source Sync</h2>
		<p>保留单条 Lifecycle Sync 作为诊断工具。Full Sync、Incremental Sync 与单条 Sync 共用同一套 Extractor、Resolver、Source Hash 与 Lifecycle Manager。</p>

		<div class="wpaic-preview-controls">
			<label for="wpaic-store-post-id"><strong>内容 ID</strong></label>
			<input id="wpaic-store-post-id" type="number" min="1" step="1" class="small-text" placeholder="2413">
			<button type="button" id="wpaic-sync-store-source" class="button">同步单条知识</button>
		</div>

		<div id="wpaic-store-sync-result" class="wpaic-result" aria-live="polite"></div>
	</div>

	<div class="wpaic-card">
		<h2>Knowledge Store Rows</h2>
		<?php if ( empty( $store_rows ) ) : ?>
			<p class="description">Knowledge Store 当前为空。可以先运行 Full Sync，或使用 Single-source Sync 创建第一条 Snapshot。</p>
		<?php else : ?>
			<p class="description wpaic-store-row-summary">共 <?php echo esc_html( number_format_i18n( $store_summary['total'] ) ); ?> 条 · 每页 <?php echo esc_html( number_format_i18n( $store_per_page ) ); ?> 条</p>
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

			<?php if ( $store_total_pages > 1 ) : ?>
				<?php
				/*
				 * Build each admin pagination URL directly.
				 * Do not pre-escape a paginate_links() base: doing so can turn the query
				 * separator into a double-encoded entity (for example #038;) in some
				 * admin/browser combinations. esc_url() is applied only at href output.
				 */
				$store_page_url = static function ( $page_number ) {
					return add_query_arg(
						array(
							'page'        => 'wp-ai-chat-lab-store',
							'store_paged' => max( 1, absint( $page_number ) ),
						),
						admin_url( 'admin.php' )
					);
				};

				$visible_pages = array( 1, $store_total_pages );
				for ( $page_number = max( 1, $store_page - 2 ); $page_number <= min( $store_total_pages, $store_page + 2 ); $page_number++ ) {
					$visible_pages[] = $page_number;
				}
				$visible_pages = array_values( array_unique( $visible_pages ) );
				sort( $visible_pages );
				?>
				<div class="tablenav bottom wpaic-store-pagination">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo esc_html( sprintf( '第 %1$d / %2$d 页', $store_page, $store_total_pages ) ); ?></span>
						<span class="pagination-links">
							<?php if ( $store_page > 1 ) : ?>
								<a class="prev-page button" href="<?php echo esc_url( $store_page_url( $store_page - 1 ) ); ?>">‹ 上一页</a>
							<?php endif; ?>

							<?php $previous_page_number = 0; ?>
							<?php foreach ( $visible_pages as $page_number ) : ?>
								<?php if ( $previous_page_number && $page_number > ( $previous_page_number + 1 ) ) : ?>
									<span class="tablenav-pages-navspan button disabled" aria-hidden="true">…</span>
								<?php endif; ?>

								<?php if ( $page_number === $store_page ) : ?>
									<span class="tablenav-pages-navspan button current" aria-current="page"><?php echo esc_html( $page_number ); ?></span>
								<?php else : ?>
									<a class="button" href="<?php echo esc_url( $store_page_url( $page_number ) ); ?>"><?php echo esc_html( $page_number ); ?></a>
								<?php endif; ?>

								<?php $previous_page_number = $page_number; ?>
							<?php endforeach; ?>

							<?php if ( $store_page < $store_total_pages ) : ?>
								<a class="next-page button" href="<?php echo esc_url( $store_page_url( $store_page + 1 ) ); ?>">下一页 ›</a>
							<?php endif; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
