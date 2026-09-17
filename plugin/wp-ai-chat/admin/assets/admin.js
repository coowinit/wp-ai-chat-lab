(function () {
	'use strict';

	function escapeHtml(value) {
		var div = document.createElement('div');
		div.textContent = value == null ? '' : String(value);
		return div.innerHTML;
	}

	function metaHtml(data) {
		return '<dl class="wpaic-meta">' +
			'<div><dt>Provider</dt><dd>' + escapeHtml(data.provider) + '</dd></div>' +
			'<div><dt>Request Model</dt><dd>' + escapeHtml(data.request_model) + '</dd></div>' +
			'<div><dt>Response Model</dt><dd>' + escapeHtml(data.response_model) + '</dd></div>' +
			'<div><dt>Prompt Tokens</dt><dd>' + escapeHtml(data.prompt_tokens) + '</dd></div>' +
			'<div><dt>Completion Tokens</dt><dd>' + escapeHtml(data.completion_tokens) + '</dd></div>' +
			'<div><dt>Total Tokens</dt><dd>' + escapeHtml(data.total_tokens) + '</dd></div>' +
			'<div><dt>Elapsed</dt><dd>' + escapeHtml(data.elapsed_ms) + ' ms</dd></div>' +
			'<div><dt>Finish Reason</dt><dd>' + escapeHtml(data.finish_reason) + '</dd></div>' +
			'<div><dt>HTTP</dt><dd>' + escapeHtml(data.status_code) + '</dd></div>' +
		'</dl>';
	}

	function prettyJson(value) {
		try {
			return JSON.stringify(value || {}, null, 2);
		} catch (error) {
			return '{}';
		}
	}

	function knowledgePreviewHtml(data) {
		var eligibility = data.eligible
			? '<span class="wpaic-status is-eligible">正式 Knowledge：是</span>'
			: '<span class="wpaic-status is-ineligible">正式 Knowledge：否（当前状态不是 publish）</span>';

		return '<div class="wpaic-preview-summary">' + eligibility + '</div>' +
			'<dl class="wpaic-meta wpaic-meta-source">' +
				'<div><dt>Source ID</dt><dd>' + escapeHtml(data.source_id) + '</dd></div>' +
				'<div><dt>Source Type</dt><dd>' + escapeHtml(data.source_type) + '</dd></div>' +
				'<div><dt>Object ID</dt><dd>' + escapeHtml(data.object_id) + '</dd></div>' +
				'<div><dt>Post Type</dt><dd>' + escapeHtml(data.post_type) + '</dd></div>' +
				'<div><dt>Knowledge Type</dt><dd>' + escapeHtml(data.knowledge_type) + '</dd></div>' +
				'<div><dt>Status</dt><dd>' + escapeHtml(data.status) + '</dd></div>' +
				'<div><dt>Updated At</dt><dd>' + escapeHtml(data.updated_at) + '</dd></div>' +
				'<div class="wpaic-meta-wide"><dt>Source Hash</dt><dd><code>' + escapeHtml(data.source_hash) + '</code></dd></div>' +
			'</dl>' +
			'<div class="wpaic-preview-section"><h3>Title</h3><div class="wpaic-preview-box">' + escapeHtml(data.title || '') + '</div></div>' +
			'<div class="wpaic-preview-section"><h3>URL</h3><div class="wpaic-preview-box">' + escapeHtml(data.url || '(内部补充知识，无前台 URL)') + '</div></div>' +
			'<div class="wpaic-preview-section"><h3>Excerpt</h3><pre>' + escapeHtml(data.excerpt || '(empty)') + '</pre></div>' +
			'<div class="wpaic-preview-section"><h3>Taxonomies</h3><pre>' + escapeHtml(prettyJson(data.taxonomies)) + '</pre></div>' +
			'<div class="wpaic-preview-section"><h3>Structured Data</h3><pre>' + escapeHtml(prettyJson(data.structured_data)) + '</pre></div>' +
			'<div class="wpaic-preview-section"><h3>Normalized Content</h3><pre>' + escapeHtml(data.content || '(empty)') + '</pre></div>';
	}


	function storeSyncHtml(data) {
		var row = data.row || {};
		var actionClass = 'is-unchanged';
		if (data.action === 'created' || data.action === 'reactivated') {
			actionClass = 'is-created';
		} else if (data.action === 'updated') {
			actionClass = 'is-updated';
		} else if (data.action === 'deactivated') {
			actionClass = 'is-deactivated';
		}
		return '<div class="wpaic-preview-summary"><span class="wpaic-status ' + actionClass + '">Action: ' + escapeHtml(data.action || '') + '</span></div>' +
			'<dl class="wpaic-meta wpaic-meta-source">' +
				'<div><dt>Source ID</dt><dd>' + escapeHtml(data.source_id) + '</dd></div>' +
				'<div><dt>Object ID</dt><dd>' + escapeHtml(data.object_id) + '</dd></div>' +
				'<div><dt>Store Status</dt><dd>' + escapeHtml(data.store_status) + '</dd></div>' +
				'<div><dt>Inactive Reason</dt><dd>' + escapeHtml(data.inactive_reason || '—') + '</dd></div>' +
				'<div><dt>Elapsed</dt><dd>' + escapeHtml(data.elapsed_ms) + ' ms</dd></div>' +
				'<div class="wpaic-meta-wide"><dt>Old Hash</dt><dd><code>' + escapeHtml(data.old_hash || '(none)') + '</code></dd></div>' +
				'<div class="wpaic-meta-wide"><dt>New Hash</dt><dd><code>' + escapeHtml(data.new_hash || '') + '</code></dd></div>' +
			'</dl>' +
			'<div class="wpaic-preview-section"><h3>Saved Title</h3><div class="wpaic-preview-box">' + escapeHtml(row.title || '') + '</div></div>' +
			'<div class="wpaic-preview-section"><h3>Saved Taxonomies</h3><pre>' + escapeHtml(prettyJson(row.taxonomies)) + '</pre></div>' +
			'<div class="wpaic-preview-section"><h3>Saved Structured Data</h3><pre>' + escapeHtml(prettyJson(row.structured_data)) + '</pre></div>' +
			'<div class="wpaic-preview-section"><h3>Saved Normalized Content</h3><pre>' + escapeHtml(row.content || '(empty)') + '</pre></div>';
	}

	function fullSyncHtml(data) {
		var stats = data.stats || {};
		var phaseLabel = data.phase === 'reconcile' ? 'Reconciliation' : (data.phase === 'done' ? 'Done' : 'Source Sync');
		var progress = Math.max(0, Math.min(100, parseInt(data.percent, 10) || 0));
		var current = parseInt(data.phase_processed, 10) || 0;
		var total = parseInt(data.phase_total, 10) || 0;
		var errors = Array.isArray(data.errors) ? data.errors : [];
		var errorHtml = '';

		if (errors.length) {
			errorHtml = '<div class="wpaic-full-sync-errors"><strong>Recent Errors</strong><ul>' + errors.map(function (item) {
				return '<li>ID ' + escapeHtml(item.post_id) + ' · ' + escapeHtml(item.code || '') + ' · ' + escapeHtml(item.message || '') + '</li>';
			}).join('') + '</ul></div>';
		}

		return '<div class="wpaic-full-sync-head">' +
			'<strong>' + escapeHtml(phaseLabel) + '</strong>' +
			'<span>' + escapeHtml(current) + ' / ' + escapeHtml(total) + '</span>' +
		'</div>' +
		'<div class="wpaic-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + progress + '"><span style="width:' + progress + '%"></span></div>' +
		'<div class="wpaic-full-sync-stats">' +
			'<div><span>Processed</span><strong>' + escapeHtml(stats.processed || 0) + '</strong></div>' +
			'<div><span>Created</span><strong>' + escapeHtml(stats.created || 0) + '</strong></div>' +
			'<div><span>Updated</span><strong>' + escapeHtml(stats.updated || 0) + '</strong></div>' +
			'<div><span>Unchanged</span><strong>' + escapeHtml(stats.unchanged || 0) + '</strong></div>' +
			'<div><span>Reactivated</span><strong>' + escapeHtml(stats.reactivated || 0) + '</strong></div>' +
			'<div><span>Deactivated</span><strong>' + escapeHtml(stats.deactivated || 0) + '</strong></div>' +
			'<div><span>Errors</span><strong>' + escapeHtml(stats.errors || 0) + '</strong></div>' +
		'</div>' +
		'<p class="description">Eligible Sources: ' + escapeHtml(data.sync_total || 0) + ' · Reconcile Candidates: ' + escapeHtml(data.reconcile_total || 0) + '</p>' +
		(data.done ? '<p><strong>Full Sync 完成。</strong> 刷新页面可查看最新 Summary 与 Store Rows。</p>' : '') +
		errorHtml;
	}

	function retrievalHtml(data) {
		var terms = Array.isArray(data.terms) ? data.terms : [];
		var candidates = Array.isArray(data.candidates) ? data.candidates : [];
		var rows = '';

		if (!candidates.length) {
			rows = '<div class="wpaic-retrieval-empty"><strong>No Candidate Match</strong><p>当前 active Knowledge Store 中没有召回候选。Stage 1 不会为了“必须有结果”而伪造匹配。</p></div>';
		} else {
			rows = '<div class="wpaic-retrieval-table-wrap"><table class="widefat striped wpaic-retrieval-table">' +
				'<thead><tr><th>#</th><th>Title / Source</th><th>Matched Fields</th><th>Matched Terms</th><th>Snippet</th></tr></thead><tbody>' +
				candidates.map(function (item, index) {
					var source = '<code>' + escapeHtml(item.source_id || '') + '</code><br><span class="description">' + escapeHtml(item.post_type || '') + ' · ID ' + escapeHtml(item.object_id || 0) + '</span>';
					var title = '<strong>' + escapeHtml(item.title || '(Untitled)') + '</strong><br>' + source;
					if (item.url) {
						title += '<br><a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener noreferrer">打开来源 ↗</a>';
					}
					return '<tr>' +
						'<td>' + escapeHtml(index + 1) + '</td>' +
						'<td>' + title + '</td>' +
						'<td>' + escapeHtml((item.matched_fields || []).join(', ') || '—') + '</td>' +
						'<td>' + escapeHtml((item.matched_terms || []).join(', ') || '—') + '</td>' +
						'<td>' + escapeHtml(item.snippet || '') + '</td>' +
					'</tr>';
				}).join('') + '</tbody></table></div>';
		}

		return '<div class="wpaic-retrieval-summary">' +
			'<dl class="wpaic-meta wpaic-meta-source">' +
				'<div class="wpaic-meta-wide"><dt>Normalized Query</dt><dd><code>' + escapeHtml(data.normalized_query || '') + '</code></dd></div>' +
				'<div class="wpaic-meta-wide"><dt>Terms</dt><dd>' + escapeHtml(terms.join(', ') || '(none)') + '</dd></div>' +
				'<div><dt>Candidate Count</dt><dd>' + escapeHtml(data.candidate_count || 0) + '</dd></div>' +
				'<div><dt>Candidate Limit</dt><dd>' + escapeHtml(data.candidate_limit || 0) + '</dd></div>' +
				'<div><dt>Elapsed</dt><dd>' + escapeHtml(data.elapsed_ms || 0) + ' ms</dd></div>' +
			'</dl>' +
			'<p class="description">Stage 1 只表示“被召回”，当前顺序不是最终 Ranking。Weighted Scoring / Top-K 将在 Stage 2 加入。</p>' +
		'</div>' + rows;
	}

	function ajaxJson(action, payload, nonce) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', nonce);
		Object.keys(payload || {}).forEach(function (key) {
			body.append(key, payload[key]);
		});

		return fetch(WPAICAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		}).then(function (response) {
			return response.json().catch(function () {
				throw new Error(WPAICAdmin.i18n.error);
			});
		}).then(function (json) {
			if (!json.success) {
				var message = json.data && json.data.message ? json.data.message : WPAICAdmin.i18n.error;
				var error = new Error(message);
				error.code = json.data && json.data.code ? json.data.code : '';
				throw error;
			}
			return json.data || {};
		});
	}

	function runFullSync(button, resultEl) {
		if (!button || button.disabled || !resultEl) return;

		setBusy(button, true);
		resultEl.className = 'wpaic-result is-loading';
		resultEl.textContent = '正在准备 Full Sync…';

		function render(data) {
			resultEl.className = data.done ? 'wpaic-result is-success' : 'wpaic-result is-loading';
			resultEl.innerHTML = fullSyncHtml(data);
		}

		function next(data) {
			render(data);
			if (data.done) {
				setBusy(button, false);
				return;
			}
			return ajaxJson('wpaic_run_full_sync_batch', { token: data.token }, WPAICAdmin.storeNonce)
				.then(next);
		}

		ajaxJson('wpaic_start_full_sync', {}, WPAICAdmin.storeNonce)
			.then(next)
			.catch(function (error) {
				resultEl.className = 'wpaic-result is-error';
				resultEl.innerHTML = '<strong>Full Sync 失败</strong><p>' + escapeHtml(error.message || WPAICAdmin.i18n.error) + '</p>' + (error.code ? '<code>' + escapeHtml(error.code) + '</code>' : '');
				setBusy(button, false);
			});
	}

	function setBusy(button, busy) {
		if (!button) return;
		if (busy) {
			button.dataset.originalText = button.textContent;
			button.textContent = WPAICAdmin.i18n.working;
			button.disabled = true;
		} else {
			button.textContent = button.dataset.originalText || button.textContent;
			button.disabled = false;
		}
	}

	function postRequest(action, payload, nonce, button, resultEl, successRenderer) {
		if (!button || button.disabled || !resultEl) return;

		setBusy(button, true);
		resultEl.className = 'wpaic-result is-loading';
		resultEl.textContent = WPAICAdmin.i18n.working;

		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', nonce);
		Object.keys(payload || {}).forEach(function (key) {
			body.append(key, payload[key]);
		});

		fetch(WPAICAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json().catch(function () {
					throw new Error(WPAICAdmin.i18n.error);
				});
			})
			.then(function (json) {
				if (!json.success) {
					var message = json.data && json.data.message ? json.data.message : WPAICAdmin.i18n.error;
					var code = json.data && json.data.code ? json.data.code : '';
					resultEl.className = 'wpaic-result is-error';
					resultEl.innerHTML = '<strong>失败</strong><p>' + escapeHtml(message) + '</p>' + (code ? '<code>' + escapeHtml(code) + '</code>' : '');
					return;
				}

				resultEl.className = 'wpaic-result is-success';
				resultEl.innerHTML = successRenderer(json.data);
			})
			.catch(function (error) {
				resultEl.className = 'wpaic-result is-error';
				resultEl.innerHTML = '<strong>失败</strong><p>' + escapeHtml(error.message || WPAICAdmin.i18n.error) + '</p>';
			})
			.finally(function () {
				setBusy(button, false);
			});
	}

	function aiRequest(action, payload, button, resultEl) {
		postRequest(action, payload, WPAICAdmin.nonce, button, resultEl, function (data) {
			return '<strong>成功</strong>' +
				(data.content ? '<div class="wpaic-response-text">' + escapeHtml(data.content) + '</div>' : '') +
				metaHtml(data);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var connectionButton = document.getElementById('wpaic-test-connection');
		var connectionResult = document.getElementById('wpaic-connection-result');
		var chatButton = document.getElementById('wpaic-test-chat');
		var chatResult = document.getElementById('wpaic-chat-result');
		var question = document.getElementById('wpaic-test-question');
		var previewButton = document.getElementById('wpaic-preview-source');
		var previewResult = document.getElementById('wpaic-source-preview-result');
		var previewPostId = document.getElementById('wpaic-preview-post-id');
		var storeSyncButton = document.getElementById('wpaic-sync-store-source');
		var storeSyncResult = document.getElementById('wpaic-store-sync-result');
		var storePostId = document.getElementById('wpaic-store-post-id');
		var fullSyncButton = document.getElementById('wpaic-start-full-sync');
		var fullSyncResult = document.getElementById('wpaic-full-sync-result');
		var retrievalButton = document.getElementById('wpaic-run-retrieval');
		var retrievalResult = document.getElementById('wpaic-retrieval-result');
		var retrievalQuestion = document.getElementById('wpaic-retrieval-question');
		var retrievalLimit = document.getElementById('wpaic-retrieval-candidate-limit');

		if (connectionButton && connectionResult) {
			connectionButton.addEventListener('click', function () {
				aiRequest('wpaic_test_connection', {}, connectionButton, connectionResult);
			});
		}

		if (chatButton && chatResult && question) {
			chatButton.addEventListener('click', function () {
				var value = question.value.trim();
				if (!value) {
					chatResult.className = 'wpaic-result is-error';
					chatResult.innerHTML = '<strong>失败</strong><p>请输入测试问题。</p>';
					return;
				}
				aiRequest('wpaic_test_chat', { question: value }, chatButton, chatResult);
			});
		}

		function runPreview(button, postId) {
			if (!previewResult || !postId) return;
			postRequest(
				'wpaic_preview_source',
				{ post_id: postId },
				WPAICAdmin.knowledgeNonce,
				button,
				previewResult,
				knowledgePreviewHtml
			);
		}

		if (previewButton && previewResult && previewPostId) {
			previewButton.addEventListener('click', function () {
				var postId = parseInt(previewPostId.value, 10);
				if (!postId || postId < 1) {
					previewResult.className = 'wpaic-result is-error';
					previewResult.innerHTML = '<strong>失败</strong><p>请输入有效的 WordPress 内容 ID。</p>';
					return;
				}
				runPreview(previewButton, postId);
			});
		}


		if (storeSyncButton && storeSyncResult && storePostId) {
			storeSyncButton.addEventListener('click', function () {
				var postId = parseInt(storePostId.value, 10);
				if (!postId || postId < 1) {
					storeSyncResult.className = 'wpaic-result is-error';
					storeSyncResult.innerHTML = '<strong>失败</strong><p>请输入有效的 WordPress 内容 ID。</p>';
					return;
				}
				postRequest(
					'wpaic_sync_store_source',
					{ post_id: postId },
					WPAICAdmin.storeNonce,
					storeSyncButton,
					storeSyncResult,
					storeSyncHtml
				);
			});
		}

		if (fullSyncButton && fullSyncResult) {
			fullSyncButton.addEventListener('click', function () {
				runFullSync(fullSyncButton, fullSyncResult);
			});
		}


		if (retrievalButton && retrievalResult && retrievalQuestion) {
			retrievalButton.addEventListener('click', function () {
				var value = retrievalQuestion.value.trim();
				var limit = retrievalLimit ? parseInt(retrievalLimit.value, 10) : 100;
				if (!value) {
					retrievalResult.className = 'wpaic-result is-error';
					retrievalResult.innerHTML = '<strong>失败</strong><p>请输入要检索的问题。</p>';
					return;
				}
				postRequest(
					'wpaic_retrieval_search',
					{ question: value, candidate_limit: limit || 100 },
					WPAICAdmin.retrievalNonce,
					retrievalButton,
					retrievalResult,
					retrievalHtml
				);
			});
		}

		document.querySelectorAll('.wpaic-preview-example').forEach(function (button) {
			button.addEventListener('click', function () {
				var postId = parseInt(button.getAttribute('data-post-id'), 10);
				if (previewPostId) {
					previewPostId.value = postId || '';
				}
				if (previewResult) {
					previewResult.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
				runPreview(button, postId);
			});
		});
	});
}());
