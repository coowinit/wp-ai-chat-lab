<?php
/** v0.9.0 Stage 2 Round 2 — Inquiry read state, filters and trash lifecycle. */
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

$list_url = function( $view = 'all', $paged = 1 ) {
	$args = array( 'page' => 'wp-ai-chat-lab-inquiries' );
	if ( 'all' !== $view ) { $args['view'] = $view; }
	if ( $paged > 1 ) { $args['paged'] = $paged; }
	return add_query_arg( $args, admin_url( 'admin.php' ) );
};
$detail_url = function( $id ) use ( $current_view, $current_page ) {
	$args = array(
		'page'       => 'wp-ai-chat-lab-inquiries',
		'inquiry_id' => (int) $id,
		'view'       => $current_view,
		'paged'      => $current_page,
	);
	return add_query_arg( $args, admin_url( 'admin.php' ) );
};
?>
<div class="wrap wpaic-wrap wpaic-inquiry-admin-page">
	<h1>WP AI Chat Lab — 询盘管理</h1>
	<p class="description">v0.9.0 Stage 2 · Round 2 — Validation Passed / Sealed。查看详情自动标记已读；删除采用 WordPress 风格的回收站机制。</p>
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
						<p class="description">查看详情即自动标记为已读；无需手动修改状态。</p>
						<form class="wpaic-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('确定将这条询盘移至回收站吗？');">
							<input type="hidden" name="action" value="wpaic_inquiry_trash">
							<input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $inquiry['id'] ); ?>">
							<input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>">
							<input type="hidden" name="view" value="<?php echo esc_attr( $current_view ); ?>">
							<?php wp_nonce_field( 'wpaic_inquiry_trash' ); ?>
							<button type="submit" class="button button-link-delete">移至回收站</button>
						</form>
					<?php else : ?>
						<form class="wpaic-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="wpaic_inquiry_restore">
							<input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $inquiry['id'] ); ?>">
							<input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>">
							<input type="hidden" name="view" value="trash">
							<?php wp_nonce_field( 'wpaic_inquiry_restore' ); ?>
							<button type="submit" class="button">恢复</button>
						</form>
						<form class="wpaic-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('永久删除后无法恢复，确定继续吗？');">
							<input type="hidden" name="action" value="wpaic_inquiry_delete_permanently">
							<input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $inquiry['id'] ); ?>">
							<input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>">
							<input type="hidden" name="view" value="trash">
							<?php wp_nonce_field( 'wpaic_inquiry_delete_permanently' ); ?>
							<button type="submit" class="button button-link-delete">永久删除</button>
						</form>
					<?php endif; ?>
				</div>

				<div class="wpaic-card">
					<h2>Round 2 边界</h2>
					<ul class="wpaic-plain-list">
						<li>询盘运营状态只保留“未读 / 已读”。</li>
						<li>打开详情自动标记为已读，不提供手动状态下拉框。</li>
						<li>删除先进入回收站；只有回收站中的询盘允许永久删除。</li>
						<li>不编辑访客提交的原始内容，不做 CRM Sync。</li>
					</ul>
				</div>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="wpaic-card">
			<h2>Stage 2 Round 2 — Validation Passed / Sealed ✅</h2>
			<div class="wpaic-flow-line"><code>Inquiry DB</code><span>→</span><code>Unread / Read</code><span>→</span><code>Filter</code><span>→</span><code>Trash</code></div>
			<p>询盘状态简化为“未读 / 已读”；回收站作为独立生命周期，不再把“垃圾 / 已关闭”等业务状态混进询盘状态。</p>
		</div>

		<ul class="subsubsub wpaic-inquiry-filters">
			<?php $filter_index = 0; foreach ( $view_labels as $view_key => $view_label ) : ?>
				<?php if ( $filter_index > 0 ) : ?><li class="separator"> | </li><?php endif; ?>
				<li class="<?php echo esc_attr( $view_key ); ?>"><a href="<?php echo esc_url( $list_url( $view_key, 1 ) ); ?>" class="<?php echo $current_view === $view_key ? 'current' : ''; ?>"><?php echo esc_html( $view_label ); ?> <span class="count">(<?php echo esc_html( (int) $view_counts[ $view_key ] ); ?>)</span></a></li>
			<?php $filter_index++; endforeach; ?>
		</ul>
		<br class="clear">

		<div class="wpaic-card">
			<h2><?php echo esc_html( $view_labels[ $current_view ] ); ?>询盘</h2>
			<p class="description">默认每页 10 条。点击“查看详情”会自动将未读询盘标记为已读；“移至回收站”可在回收站中恢复或永久删除。</p>
			<?php if ( empty( $inquiries ) ) : ?>
				<p>当前筛选下没有询盘。</p>
			<?php else : ?>
				<div class="wpaic-store-table-wrap"><table class="wp-list-table widefat fixed striped table-view-list wpaic-inquiry-admin-table">
					<thead>
						<tr>
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
										<span class="trash"> | <form class="wpaic-row-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('确定将这条询盘移至回收站吗？');"><input type="hidden" name="action" value="wpaic_inquiry_trash"><input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $row['id'] ); ?>"><input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>"><input type="hidden" name="view" value="<?php echo esc_attr( $current_view ); ?>"><?php wp_nonce_field( 'wpaic_inquiry_trash' ); ?><button type="submit" class="button-link-delete">移至回收站</button></form></span>
									<?php else : ?>
										<span class="restore"> | <form class="wpaic-row-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wpaic_inquiry_restore"><input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $row['id'] ); ?>"><input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>"><input type="hidden" name="view" value="trash"><?php wp_nonce_field( 'wpaic_inquiry_restore' ); ?><button type="submit" class="button-link">恢复</button></form></span>
										<span class="delete"> | <form class="wpaic-row-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('永久删除后无法恢复，确定继续吗？');"><input type="hidden" name="action" value="wpaic_inquiry_delete_permanently"><input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $row['id'] ); ?>"><input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>"><input type="hidden" name="view" value="trash"><?php wp_nonce_field( 'wpaic_inquiry_delete_permanently' ); ?><button type="submit" class="button-link-delete">永久删除</button></form></span>
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

				<?php if ( $total_pages > 1 ) : ?>
					<?php $prev_url = $list_url( $current_view, max( 1, $current_page - 1 ) ); $next_url = $list_url( $current_view, min( $total_pages, $current_page + 1 ) ); ?>
					<div class="tablenav bottom wpaic-inquiry-tablenav">
						<div class="tablenav-pages">
							<span class="displaying-num"><?php echo esc_html( $current_total ); ?> 个项目</span>
							<span class="pagination-links wpaic-inquiry-pagination-links">
								<?php if ( $current_page > 1 ) : ?><a class="prev-page button" href="<?php echo esc_url( $prev_url ); ?>"><span class="screen-reader-text">上一页</span><span aria-hidden="true">‹</span></a><?php else : ?><span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span><?php endif; ?>
								<span class="paging-input"><span class="tablenav-paging-text">第 <strong><?php echo esc_html( $current_page ); ?></strong> / <?php echo esc_html( $total_pages ); ?> 页</span></span>
								<?php if ( $current_page < $total_pages ) : ?><a class="next-page button" href="<?php echo esc_url( $next_url ); ?>"><span class="screen-reader-text">下一页</span><span aria-hidden="true">›</span></a><?php else : ?><span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span><?php endif; ?>
							</span>
						</div>
						<br class="clear">
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
