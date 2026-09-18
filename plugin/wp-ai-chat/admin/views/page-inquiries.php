<?php
/** v0.9.0 Stage 2 Round 3 — Inquiry management efficiency. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$status_labels = array(
	'unread' => '未读',
	'read'   => '已读',
);
$trigger_labels = array(
	'manual'            => '主动联系',
	'commercial_intent' => '商业需求',
	'no_answer'         => 'AI 无法回答',
	'usage_blocked'     => 'AI 使用受限',
);
$view_labels = array(
	'all'    => '全部',
	'unread' => '未读',
	'read'   => '已读',
	'trash'  => '回收站',
);

$list_url = function( $view = 'all', $paged = 1, $trigger = null, $search = null ) use ( $trigger_filter, $search_query ) {
	$args = array( 'page' => 'wp-ai-chat-lab-inquiries' );
	if ( 'all' !== $view ) { $args['view'] = $view; }
	if ( $paged > 1 ) { $args['paged'] = $paged; }
	$trigger = null === $trigger ? $trigger_filter : $trigger;
	$search  = null === $search ? $search_query : $search;
	if ( '' !== $trigger ) { $args['trigger'] = $trigger; }
	if ( '' !== $search ) { $args['s'] = $search; }
	return add_query_arg( $args, admin_url( 'admin.php' ) );
};
$detail_url = function( $id ) use ( $current_view, $current_page, $trigger_filter, $search_query ) {
	$args = array(
		'page'             => 'wp-ai-chat-lab-inquiries',
		'inquiry_id'       => (int) $id,
		'view'             => $current_view,
		'paged'            => $current_page,
		'wpaic_read_nonce' => wp_create_nonce( 'wpaic_inquiry_read_' . (int) $id ),
	);
	if ( '' !== $trigger_filter ) { $args['trigger'] = $trigger_filter; }
	if ( '' !== $search_query ) { $args['s'] = $search_query; }
	return add_query_arg( $args, admin_url( 'admin.php' ) );
};
?>
<div class="wrap wpaic-wrap wpaic-inquiry-admin-page">
	<h1>WP AI Chat Lab — 询盘管理</h1>
	<p class="description">v0.9.0 Stage 2 · Round 3 — Validation Passed / Sealed ✅。Bulk Read / Unread + Source Filter + Search + Empty Trash 已完成真实后台验收；本页长期保留用于日常管理与回归。</p>
	<?php settings_errors( 'wpaic_inquiry_admin_messages' ); ?>

	<?php if ( $inquiry_id ) : ?>
		<?php if ( empty( $inquiry ) ) : ?>
			<div class="notice notice-error"><p>没有找到这条询盘。</p></div>
			<p><a class="button" href="<?php echo esc_url( $list_url( $current_view, $current_page ) ); ?>">← 返回询盘列表</a></p>
		<?php else : ?>
			<p><a href="<?php echo esc_url( $list_url( $current_view, $current_page ) ); ?>">← 返回询盘列表</a></p>

			<div class="wpaic-inquiry-detail-stack">
				<div class="wpaic-card">
					<div class="wpaic-inquiry-detail-heading">
						<h2>Contact</h2>
						<?php if ( ! empty( $inquiry['trashed_at'] ) ) : ?>
							<span class="wpaic-inquiry-trash-badge">回收站</span>
						<?php else : ?>
							<span class="wpaic-inquiry-status is-read">已读</span>
						<?php endif; ?>
					</div>
					<dl class="wpaic-meta wpaic-meta-source">
						<div><dt>Name</dt><dd><?php echo esc_html( $inquiry['name'] ); ?></dd></div>
						<div><dt>Email</dt><dd><a href="mailto:<?php echo esc_attr( $inquiry['email'] ); ?>"><?php echo esc_html( $inquiry['email'] ); ?></a></dd></div>
						<div><dt>Company</dt><dd><?php echo esc_html( $inquiry['company'] ? $inquiry['company'] : '—' ); ?></dd></div>
						<div><dt>Phone / WhatsApp</dt><dd><?php echo esc_html( $inquiry['phone'] ? $inquiry['phone'] : '—' ); ?></dd></div>
					</dl>
				</div>

				<div class="wpaic-card">
					<h2>Inquiry</h2>
					<div class="wpaic-inquiry-message"><?php echo nl2br( esc_html( $inquiry['message'] ) ); ?></div>
				</div>

				<div class="wpaic-card">
					<h2>Context</h2>
					<dl class="wpaic-meta wpaic-meta-source">
						<div><dt>Trigger</dt><dd><?php echo esc_html( isset( $trigger_labels[ $inquiry['trigger_type'] ] ) ? $trigger_labels[ $inquiry['trigger_type'] ] : $inquiry['trigger_type'] ); ?> <code><?php echo esc_html( $inquiry['trigger_type'] ); ?></code></dd></div>
						<div><dt>Conversation</dt><dd><code><?php echo esc_html( $inquiry['conversation_id'] ? $inquiry['conversation_id'] : '—' ); ?></code></dd></div>
						<div class="wpaic-meta-wide"><dt>Last Question</dt><dd><?php echo esc_html( $inquiry['last_question'] ? $inquiry['last_question'] : '—' ); ?></dd></div>
						<div class="wpaic-meta-wide"><dt>Source URL</dt><dd><?php if ( $inquiry['source_url'] ) : ?><a href="<?php echo esc_url( $inquiry['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $inquiry['source_url'] ); ?> ↗</a><?php else : ?>—<?php endif; ?></dd></div>
						<div><dt>Visitor Hash</dt><dd><code><?php echo esc_html( $inquiry['visitor_hash'] ? substr( $inquiry['visitor_hash'], 0, 12 ) . '…' : '—' ); ?></code></dd></div>
						<div><dt>Submitted</dt><dd><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $inquiry['created_at'] ) ); ?></dd></div>
						<div><dt>Updated</dt><dd><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $inquiry['updated_at'] ) ); ?></dd></div>
						<?php if ( ! empty( $inquiry['trashed_at'] ) ) : ?><div><dt>Trashed</dt><dd><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $inquiry['trashed_at'] ) ); ?></dd></div><?php endif; ?>
					</dl>
				</div>

				<div class="wpaic-card">
					<h2>管理</h2>
					<?php if ( empty( $inquiry['trashed_at'] ) ) : ?>
						<p class="description">查看详情即自动标记为已读；访客提交内容保持只读。</p>
						<form class="wpaic-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('确定将这条询盘移至回收站吗？');">
							<input type="hidden" name="action" value="wpaic_inquiry_trash">
							<input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $inquiry['id'] ); ?>">
							<input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>">
							<input type="hidden" name="view" value="<?php echo esc_attr( $current_view ); ?>">
							<input type="hidden" name="trigger" value="<?php echo esc_attr( $trigger_filter ); ?>">
							<input type="hidden" name="s" value="<?php echo esc_attr( $search_query ); ?>">
							<?php wp_nonce_field( 'wpaic_inquiry_trash' ); ?>
							<button type="submit" class="button button-link-delete">移至回收站</button>
						</form>
					<?php else : ?>
						<form class="wpaic-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="wpaic_inquiry_restore">
							<input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $inquiry['id'] ); ?>">
							<input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>">
							<input type="hidden" name="view" value="trash">
							<input type="hidden" name="trigger" value="<?php echo esc_attr( $trigger_filter ); ?>">
							<input type="hidden" name="s" value="<?php echo esc_attr( $search_query ); ?>">
							<?php wp_nonce_field( 'wpaic_inquiry_restore' ); ?>
							<button type="submit" class="button">恢复</button>
						</form>
						<form class="wpaic-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('永久删除后无法恢复，确定继续吗？');">
							<input type="hidden" name="action" value="wpaic_inquiry_delete_permanently">
							<input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $inquiry['id'] ); ?>">
							<input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>">
							<input type="hidden" name="view" value="trash">
							<input type="hidden" name="trigger" value="<?php echo esc_attr( $trigger_filter ); ?>">
							<input type="hidden" name="s" value="<?php echo esc_attr( $search_query ); ?>">
							<?php wp_nonce_field( 'wpaic_inquiry_delete_permanently' ); ?>
							<button type="submit" class="button button-link-delete">永久删除</button>
						</form>
					<?php endif; ?>
				</div>

				<div class="wpaic-card">
					<h2>Stage 2 Round 3 边界</h2>
					<ul class="wpaic-plain-list">
						<li>批量操作只提供“标记为已读 / 标记为未读”，不批量删除。</li>
						<li>来源筛选只使用四种已验证 Trigger；搜索只覆盖 Name / Email / Company / Message。</li>
						<li>“清空回收站”只永久删除已经在回收站中的询盘。</li>
						<li>不编辑访客原始内容，不加入 CRM、销售阶段或负责人分配。</li>
					</ul>
				</div>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="wpaic-card">
			<h2>Stage 2 Round 3 — Validation Passed / Sealed ✅</h2>
			<div class="wpaic-flow-line"><code>Inquiry DB</code><span>→</span><code>Bulk Read / Unread</code><span>→</span><code>Source Filter</code><span>→</span><code>Search</code><span>→</span><code>Empty Trash</code></div>
			<p>本轮已完成真实后台验收并封板：只提升日常处理效率，不扩展成 CRM；DB Version 保持 1.3，不新增数据表或字段。</p>
		</div>

		<ul class="subsubsub wpaic-inquiry-filters">
			<?php $filter_index = 0; foreach ( $view_labels as $view_key => $view_label ) : ?>
				<?php if ( $filter_index > 0 ) : ?><li class="separator"> | </li><?php endif; ?>
				<li class="<?php echo esc_attr( $view_key ); ?>"><a href="<?php echo esc_url( $list_url( $view_key, 1 ) ); ?>" class="<?php echo $current_view === $view_key ? 'current' : ''; ?>"><?php echo esc_html( $view_label ); ?> <span class="count">(<?php echo esc_html( (int) $view_counts[ $view_key ] ); ?>)</span></a></li>
			<?php $filter_index++; endforeach; ?>
		</ul>
		<br class="clear">

		<div class="wpaic-card">
			<div class="wpaic-inquiry-list-heading">
				<div>
					<h2><?php echo esc_html( $view_labels[ $current_view ] ); ?>询盘</h2>
					<p class="description">默认每页 10 条；筛选、搜索与分页可以组合使用。点击详情仍会把正常询盘自动标记为已读。</p>
				</div>
				<?php if ( 'trash' === $current_view && ! empty( $view_counts['trash'] ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('确定永久删除回收站中的全部询盘吗？此操作无法撤销。');">
						<input type="hidden" name="action" value="wpaic_inquiry_empty_trash">
						<input type="hidden" name="trigger" value="<?php echo esc_attr( $trigger_filter ); ?>">
						<input type="hidden" name="s" value="<?php echo esc_attr( $search_query ); ?>">
						<?php wp_nonce_field( 'wpaic_inquiry_empty_trash' ); ?>
						<button type="submit" class="button button-link-delete">清空全部回收站</button>
					</form>
				<?php endif; ?>
			</div>

			<form class="wpaic-inquiry-query-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="wp-ai-chat-lab-inquiries">
				<?php if ( 'all' !== $current_view ) : ?><input type="hidden" name="view" value="<?php echo esc_attr( $current_view ); ?>"><?php endif; ?>
				<div class="tablenav top wpaic-inquiry-query-tablenav">
					<div class="alignleft actions">
						<label class="screen-reader-text" for="wpaic-inquiry-trigger-filter">按来源筛选</label>
						<select name="trigger" id="wpaic-inquiry-trigger-filter">
							<option value="">全部来源</option>
							<?php foreach ( $trigger_labels as $trigger_key => $trigger_label ) : ?>
								<option value="<?php echo esc_attr( $trigger_key ); ?>" <?php selected( $trigger_filter, $trigger_key ); ?>><?php echo esc_html( $trigger_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button">筛选</button>
						<?php if ( '' !== $trigger_filter || '' !== $search_query ) : ?><a class="button" href="<?php echo esc_url( $list_url( $current_view, 1, '', '' ) ); ?>">重置</a><?php endif; ?>
					</div>
					<p class="search-box">
						<label class="screen-reader-text" for="wpaic-inquiry-search-input">搜索询盘:</label>
						<input type="search" id="wpaic-inquiry-search-input" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="姓名 / Email / 公司 / 留言">
						<input type="submit" class="button" value="搜索询盘">
					</p>
					<br class="clear">
				</div>
			</form>

			<?php if ( 'trash' !== $current_view ) : ?>
				<form id="wpaic-inquiry-bulk-form" class="wpaic-inquiry-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpaic_inquiry_bulk_status">
					<input type="hidden" name="view" value="<?php echo esc_attr( $current_view ); ?>">
					<input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>">
					<input type="hidden" name="trigger" value="<?php echo esc_attr( $trigger_filter ); ?>">
					<input type="hidden" name="s" value="<?php echo esc_attr( $search_query ); ?>">
					<?php wp_nonce_field( 'wpaic_inquiry_bulk_status' ); ?>
					<div class="tablenav top wpaic-inquiry-bulk-tablenav">
						<div class="alignleft actions bulkactions">
							<label for="wpaic-bulk-action-selector" class="screen-reader-text">选择批量操作</label>
							<select name="bulk_action" id="wpaic-bulk-action-selector">
								<option value="">批量操作</option>
								<option value="mark_read">标记为已读</option>
								<option value="mark_unread">标记为未读</option>
							</select>
							<button type="submit" class="button action">应用</button>
						</div>
						<br class="clear">
					</div>
				</form>
			<?php endif; ?>

			<?php if ( empty( $inquiries ) ) : ?>
				<p>当前条件下没有询盘。</p>
			<?php else : ?>
				<div class="wpaic-store-table-wrap"><table class="wp-list-table widefat fixed striped table-view-list wpaic-inquiry-admin-table">
					<thead>
						<tr>
							<?php if ( 'trash' !== $current_view ) : ?><td class="manage-column column-cb check-column"><input type="checkbox"><span class="screen-reader-text">全选</span></td><?php endif; ?>
							<th scope="col" class="manage-column column-created_at">时间</th>
							<th scope="col" class="manage-column column-status">状态</th>
							<th scope="col" class="manage-column column-contact column-primary">联系人</th>
							<th scope="col" class="manage-column column-message">留言摘要</th>
							<th scope="col" class="manage-column column-source">来源</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $inquiries as $row ) : ?>
						<?php $is_trash = ! empty( $row['trashed_at'] ); $is_unread = 'unread' === $row['status'] && ! $is_trash; ?>
						<tr class="<?php echo $is_unread ? 'wpaic-inquiry-row-unread' : ''; ?>">
							<?php if ( 'trash' !== $current_view ) : ?><th scope="row" class="check-column"><input form="wpaic-inquiry-bulk-form" type="checkbox" name="inquiry_ids[]" value="<?php echo esc_attr( (int) $row['id'] ); ?>"><span class="screen-reader-text">选择 <?php echo esc_html( $row['name'] ); ?></span></th><?php endif; ?>
							<td class="column-created_at" data-colname="时间"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ) ); ?></td>
							<td class="column-status" data-colname="状态">
								<?php if ( $is_trash ) : ?><span class="wpaic-inquiry-trash-badge">回收站</span><?php else : ?><span class="wpaic-inquiry-status is-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $status_labels[ $row['status'] ] ); ?></span><?php endif; ?>
							</td>
							<td class="column-contact column-primary" data-colname="联系人">
								<strong><a href="<?php echo esc_url( $detail_url( $row['id'] ) ); ?>"><?php echo esc_html( $row['name'] ); ?></a></strong><br>
								<a href="mailto:<?php echo esc_attr( $row['email'] ); ?>"><?php echo esc_html( $row['email'] ); ?></a>
								<?php if ( $row['company'] ) : ?><br><span class="description"><?php echo esc_html( $row['company'] ); ?></span><?php endif; ?>
								<div class="row-actions">
									<span class="view"><a href="<?php echo esc_url( $detail_url( $row['id'] ) ); ?>">查看详情</a></span>
									<?php if ( ! $is_trash ) : ?>
										<span class="trash"> | <form class="wpaic-row-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('确定将这条询盘移至回收站吗？');"><input type="hidden" name="action" value="wpaic_inquiry_trash"><input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $row['id'] ); ?>"><input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>"><input type="hidden" name="view" value="<?php echo esc_attr( $current_view ); ?>"><input type="hidden" name="trigger" value="<?php echo esc_attr( $trigger_filter ); ?>"><input type="hidden" name="s" value="<?php echo esc_attr( $search_query ); ?>"><?php wp_nonce_field( 'wpaic_inquiry_trash' ); ?><button type="submit" class="button-link-delete">移至回收站</button></form></span>
									<?php else : ?>
										<span class="restore"> | <form class="wpaic-row-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wpaic_inquiry_restore"><input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $row['id'] ); ?>"><input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>"><input type="hidden" name="view" value="trash"><input type="hidden" name="trigger" value="<?php echo esc_attr( $trigger_filter ); ?>"><input type="hidden" name="s" value="<?php echo esc_attr( $search_query ); ?>"><?php wp_nonce_field( 'wpaic_inquiry_restore' ); ?><button type="submit" class="button-link">恢复</button></form></span>
										<span class="delete"> | <form class="wpaic-row-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('永久删除后无法恢复，确定继续吗？');"><input type="hidden" name="action" value="wpaic_inquiry_delete_permanently"><input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $row['id'] ); ?>"><input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>"><input type="hidden" name="view" value="trash"><input type="hidden" name="trigger" value="<?php echo esc_attr( $trigger_filter ); ?>"><input type="hidden" name="s" value="<?php echo esc_attr( $search_query ); ?>"><?php wp_nonce_field( 'wpaic_inquiry_delete_permanently' ); ?><button type="submit" class="button-link-delete">永久删除</button></form></span>
									<?php endif; ?>
								</div>
								<button type="button" class="toggle-row"><span class="screen-reader-text">显示更多详情</span></button>
							</td>
							<td class="column-message" data-colname="留言摘要"><?php echo esc_html( wp_html_excerpt( $row['message'], 120, '…' ) ); ?></td>
							<td class="column-source" data-colname="来源"><?php echo esc_html( isset( $trigger_labels[ $row['trigger_type'] ] ) ? $trigger_labels[ $row['trigger_type'] ] : $row['trigger_type'] ); ?><br><code><?php echo esc_html( $row['trigger_type'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
			<?php endif; ?>

			<div class="tablenav bottom wpaic-inquiry-tablenav">
				<div class="tablenav-pages">
					<span class="displaying-num"><?php echo esc_html( $current_total ); ?> 个项目</span>
					<?php if ( $total_pages > 1 ) : ?>
						<?php $prev_url = $list_url( $current_view, max( 1, $current_page - 1 ) ); $next_url = $list_url( $current_view, min( $total_pages, $current_page + 1 ) ); ?>
						<span class="pagination-links wpaic-inquiry-pagination-links">
							<?php if ( $current_page > 1 ) : ?><a class="prev-page button" href="<?php echo esc_url( $prev_url ); ?>"><span class="screen-reader-text">上一页</span><span aria-hidden="true">‹</span></a><?php else : ?><span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span><?php endif; ?>
							<span class="paging-input"><span class="tablenav-paging-text">第 <strong><?php echo esc_html( $current_page ); ?></strong> / <?php echo esc_html( $total_pages ); ?> 页</span></span>
							<?php if ( $current_page < $total_pages ) : ?><a class="next-page button" href="<?php echo esc_url( $next_url ); ?>"><span class="screen-reader-text">下一页</span><span aria-hidden="true">›</span></a><?php else : ?><span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span><?php endif; ?>
						</span>
					<?php endif; ?>
				</div>
				<br class="clear">
			</div>
		</div>
	<?php endif; ?>
</div>
