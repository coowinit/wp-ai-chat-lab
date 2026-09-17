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

	function retrievalBreakdownHtml(breakdown) {
		breakdown = breakdown || {};
		var parts = [];
		var fieldScores = breakdown.field_scores || {};
		Object.keys(fieldScores).forEach(function (field) {
			var score = parseInt(fieldScores[field], 10) || 0;
			if (score > 0) {
				parts.push('<span><code>' + escapeHtml(field) + '</code> +' + escapeHtml(score) + '</span>');
			}
		});

		var exact = breakdown.exact_match || {};
		var exactTerms = Array.isArray(exact.terms) ? exact.terms : [];
		if ((parseInt(exact.boost, 10) || 0) > 0) {
			parts.push('<span>Exact ' + escapeHtml(exactTerms.join(', ')) + ' +' + escapeHtml(exact.boost) + '</span>');
		}

		var phrase = breakdown.phrase_match || {};
		var phraseFields = Array.isArray(phrase.fields) ? phrase.fields : [];
		if ((parseInt(phrase.boost, 10) || 0) > 0) {
			parts.push('<span>Phrase ' + escapeHtml(phraseFields.join(', ')) + ' +' + escapeHtml(phrase.boost) + '</span>');
		}

		var coverage = breakdown.coverage || {};
		if ((parseInt(coverage.total, 10) || 0) > 0) {
			parts.push('<span>Coverage ' + escapeHtml(coverage.matched || 0) + '/' + escapeHtml(coverage.total || 0) + ' +' + escapeHtml(coverage.bonus || 0) + '</span>');
		}

		return parts.length ? '<div class="wpaic-score-breakdown">' + parts.join('') + '</div>' : '—';
	}

	function retrievalStrengthHtml(strength) {
		strength = strength || {};
		var label = String(strength.strength || 'none').toLowerCase();
		var topScore = parseInt(strength.top_score, 10) || 0;
		var secondScore = parseInt(strength.second_score, 10) || 0;
		var scoreGap = parseInt(strength.score_gap, 10) || 0;
		var coverage = Math.round((parseFloat(strength.top_coverage) || 0) * 100);
		var thresholds = strength.thresholds || {};
		var reliable = !!strength.reliable_match;
		var titleMap = {
			strong: 'Strong',
			medium: 'Medium',
			weak: 'Weak',
			none: 'None'
		};
		var noteMap = {
			strong: 'Top Result 在分数、覆盖率与领先差距上都较明确。',
			medium: '存在较可信的本地匹配，但仍建议继续用真实 Query Set 校准。',
			weak: '存在候选，但相关性或区分度不足；当前不视为可靠本地匹配。',
			none: '没有达到当前 v0.5.0 校准基线的可靠本地匹配。'
		};
		var html = '<div class="wpaic-retrieval-strength is-' + escapeHtml(label) + '">' +
			'<div class="wpaic-strength-head"><strong>Retrieval Strength</strong><span class="wpaic-strength-badge">' + escapeHtml(titleMap[label] || 'None') + '</span></div>' +
			'<p>' + escapeHtml(noteMap[label] || noteMap.none) + ' <strong>' + (reliable ? 'Reliable Match: Yes' : 'Reliable Match: No') + '</strong></p>' +
			'<dl class="wpaic-strength-stats">' +
				'<div><dt>Top Score</dt><dd>' + escapeHtml(topScore) + '</dd></div>' +
				'<div><dt>Second Score</dt><dd>' + escapeHtml(secondScore) + '</dd></div>' +
				'<div><dt>Score Gap</dt><dd>' + escapeHtml(scoreGap) + '</dd></div>' +
				'<div><dt>Top Coverage</dt><dd>' + escapeHtml(coverage) + '%</dd></div>' +
			'</dl>' +
			'<p class="description">Calibration Thresholds: Minimum ' + escapeHtml(thresholds.minimum_score || 0) +
			' · Medium ' + escapeHtml(thresholds.medium_score || 0) +
			' · Strong ' + escapeHtml(thresholds.strong_score || 0) +
			' · Gap ' + escapeHtml(thresholds.medium_gap || 0) + '/' + escapeHtml(thresholds.strong_gap || 0) +
			' · Coverage ' + escapeHtml(Math.round((parseFloat(thresholds.medium_coverage) || 0) * 100)) + '%/' + escapeHtml(Math.round((parseFloat(thresholds.strong_coverage) || 0) * 100)) + '%</p>' +
		'</div>';

		if (!reliable && label !== 'none') {
			html += '<div class="wpaic-retrieval-unreliable"><strong>No Reliable Local Match</strong><p>候选仍保留在下方用于检索质量校准；这不是 v0.6.0 Grounding Gate 的最终“可回答 / 不可回答”判断。</p></div>';
		}
		return html;
	}

	function retrievalHtml(data) {
		var terms = Array.isArray(data.terms) ? data.terms : [];
		var results = Array.isArray(data.results) ? data.results : [];
		var rows = '';
		var strength = data.strength || {};

		if (!results.length) {
			rows = '<div class="wpaic-retrieval-empty"><strong>No Candidate Match</strong><p>当前 active Knowledge Store 中没有召回候选。v0.5.0 不会为了“必须有结果”而伪造匹配。</p></div>';
		} else {
			rows = '<div class="wpaic-retrieval-table-wrap"><table class="widefat striped wpaic-retrieval-table wpaic-retrieval-ranked-table">' +
				'<thead><tr><th>Rank</th><th>Score</th><th>Title / Source</th><th>Matched Fields</th><th>Matched Terms</th><th>Score Breakdown</th><th>Snippet</th></tr></thead><tbody>' +
				results.map(function (item, index) {
					var source = '<code>' + escapeHtml(item.source_id || '') + '</code><br><span class="description">' + escapeHtml(item.post_type || '') + ' · ID ' + escapeHtml(item.object_id || 0) + '</span>';
					var title = '<strong>' + escapeHtml(item.title || '(Untitled)') + '</strong><br>' + source;
					if (item.url) {
						title += '<br><a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener noreferrer">打开来源 ↗</a>';
					}
					return '<tr>' +
						'<td><strong>' + escapeHtml(item.rank || (index + 1)) + '</strong></td>' +
						'<td><strong class="wpaic-retrieval-score">' + escapeHtml(item.score || 0) + '</strong></td>' +
						'<td>' + title + '</td>' +
						'<td>' + escapeHtml((item.matched_fields || []).join(', ') || '—') + '</td>' +
						'<td>' + escapeHtml((item.matched_terms || []).join(', ') || '—') + '</td>' +
						'<td>' + retrievalBreakdownHtml(item.score_breakdown || {}) + '</td>' +
						'<td>' + escapeHtml(item.snippet || '') + '</td>' +
					'</tr>';
				}).join('') + '</tbody></table></div>';
		}

		return '<div class="wpaic-retrieval-summary">' +
			'<dl class="wpaic-meta wpaic-meta-source">' +
				'<div class="wpaic-meta-wide"><dt>Normalized Query</dt><dd><code>' + escapeHtml(data.normalized_query || '') + '</code></dd></div>' +
				'<div class="wpaic-meta-wide"><dt>Terms</dt><dd>' + escapeHtml(terms.join(', ') || '(none)') + '</dd></div>' +
				'<div><dt>Candidate Count</dt><dd>' + escapeHtml(data.candidate_count || 0) + '</dd></div>' +
				'<div><dt>Scored Count</dt><dd>' + escapeHtml(data.scored_count || 0) + '</dd></div>' +
				'<div><dt>Top K</dt><dd>' + escapeHtml(data.top_k || 0) + '</dd></div>' +
				'<div><dt>Candidate Limit</dt><dd>' + escapeHtml(data.candidate_limit || 0) + '</dd></div>' +
				'<div><dt>Elapsed</dt><dd>' + escapeHtml(data.elapsed_ms || 0) + ' ms</dd></div>' +
			'</dl>' +
			'<p class="description">Strong / Medium / Weak / None 是 v0.5.0 的诊断级校准结果，不等同于 AI Grounding Gate。</p>' +
		'</div>' + retrievalStrengthHtml(strength) + rows;
	}


	function evidencePackHtml(evidence) {
		evidence = evidence || {};
		var status = String(evidence.status || 'skipped');
		var limits = evidence.limits || {};
		var sources = Array.isArray(evidence.sources) ? evidence.sources : [];
		if (status !== 'built') {
			return '<div class="wpaic-evidence-pack is-skipped"><h3>Evidence Pack</h3><p><strong>Skipped</strong> — Grounding Gate 未允许进入 Evidence Pipeline。</p></div>';
		}
		var sourceHtml = sources.map(function (source) {
			var coverage = Math.round((parseFloat(source.coverage) || 0) * 100);
			var meta = '<code>' + escapeHtml(source.evidence_id || '') + '</code> · Score ' + escapeHtml(source.score || 0) + ' · Coverage ' + escapeHtml(coverage) + '% · ' + escapeHtml(source.char_count || 0) + ' chars';
			var links = source.url ? '<p><a href="' + escapeHtml(source.url) + '" target="_blank" rel="noopener noreferrer">打开来源 ↗</a></p>' : '';
			return '<div class="wpaic-evidence-source">' +
				'<div class="wpaic-evidence-source-head"><strong>' + escapeHtml(source.evidence_id || '') + ' · ' + escapeHtml(source.title || '(Untitled)') + '</strong><span>' + meta + '</span></div>' +
				'<p class="description"><code>' + escapeHtml(source.source_id || '') + '</code> · Matched: ' + escapeHtml((source.matched_terms || []).join(', ') || '—') + (source.truncated ? ' · Truncated' : '') + '</p>' +
				'<pre class="wpaic-evidence-text">' + escapeHtml(source.text || '') + '</pre>' + links +
			'</div>';
		}).join('');
		return '<div class="wpaic-evidence-pack"><h3>Evidence Pack</h3>' +
			'<dl class="wpaic-strength-stats wpaic-evidence-stats">' +
				'<div><dt>Sources</dt><dd>' + escapeHtml(evidence.source_count || 0) + '</dd></div>' +
				'<div><dt>Total Chars</dt><dd>' + escapeHtml(evidence.total_chars || 0) + '</dd></div>' +
				'<div><dt>Max Sources</dt><dd>' + escapeHtml(limits.max_sources || 0) + '</dd></div>' +
				'<div><dt>Evidence Budget</dt><dd>' + escapeHtml(limits.max_total_chars || 0) + '</dd></div>' +
			'</dl>' + sourceHtml + '</div>';
	}

	function promptPreviewHtml(prompt) {
		prompt = prompt || {};
		if (String(prompt.status || 'skipped') !== 'built') {
			return '<div class="wpaic-prompt-preview is-skipped"><h3>Prompt Preview</h3><p><strong>Skipped</strong> — 没有可发送给 Provider 的 Prompt。</p></div>';
		}
		return '<div class="wpaic-prompt-preview"><h3>Prompt Preview</h3>' +
			'<p class="description">Evidence IDs: ' + escapeHtml((prompt.evidence_ids || []).join(', ') || '—') + ' · Prompt Chars: ' + escapeHtml(prompt.char_count || 0) + '</p>' +
			'<h4>System Prompt</h4><pre>' + escapeHtml(prompt.system_prompt || '') + '</pre>' +
			'<h4>User Prompt</h4><pre>' + escapeHtml(prompt.user_prompt || '') + '</pre>' +
		'</div>';
	}

	function groundedUsageGuardHtml(guard) {
		guard = guard || {};
		if (String(guard.status || 'skipped') !== 'evaluated') {
			return '<div class="wpaic-usage-decision is-skipped"><h3>Usage Guard</h3><p><strong>Skipped</strong> — Grounding Gate 未允许越过 Provider Boundary，因此不检查也不消耗 AI Quota。</p></div>';
		}
		var decision = String(guard.decision || (guard.reserved ? 'allow' : 'block')).toLowerCase();
		var scopes = guard.scopes || {};
		var order = ['conversation', 'visitor', 'site'];
		var labels = { conversation: 'Conversation', visitor: 'Visitor Today', site: 'Site Today' };
		var scopeHtml = order.map(function (scope) {
			var item = scopes[scope] || {};
			var remaining = item.remaining === null || item.remaining === undefined ? 'Unlimited' : item.remaining;
			return '<div class="wpaic-usage-scope">' +
				'<h4>' + escapeHtml(labels[scope]) + '</h4>' +
				'<dl>' +
					'<dt>Calls</dt><dd>' + escapeHtml(item.provider_calls !== undefined ? item.provider_calls : (item.used || 0)) + '</dd>' +
					'<dt>Limit</dt><dd>' + escapeHtml(item.limit || 0) + '</dd>' +
					'<dt>Remaining</dt><dd>' + escapeHtml(remaining) + '</dd>' +
					'<dt>Prompt Tokens</dt><dd>' + escapeHtml(item.prompt_tokens || 0) + '</dd>' +
					'<dt>Completion Tokens</dt><dd>' + escapeHtml(item.completion_tokens || 0) + '</dd>' +
					'<dt>Total Tokens</dt><dd>' + escapeHtml(item.total_tokens || 0) + '</dd>' +
				'</dl>' +
			'</div>';
		}).join('');
		return '<div class="wpaic-usage-decision is-' + escapeHtml(decision) + '">' +
			'<div class="wpaic-strength-head"><strong>Usage Guard</strong><span class="wpaic-answer-badge">' + escapeHtml(decision.toUpperCase()) + '</span></div>' +
			'<p>Reason Code: <code>' + escapeHtml(guard.reason_code || '') + '</code>' +
			(guard.blocked_scope ? ' · Blocked Scope: <strong>' + escapeHtml(guard.blocked_scope) + '</strong>' : '') +
			(guard.reserved ? ' · Provider Call Reserved: <strong>Yes</strong>' : '') + '</p>' +
			(guard.accounting_warning ? '<p class="description">Token Accounting Warning: <code>' + escapeHtml(guard.accounting_warning) + '</code></p>' : '') +
			'<div class="wpaic-usage-scopes">' + scopeHtml + '</div>' +
		'</div>';
	}

	function groundedAnswerHtml(data) {
		data = data || {};
		var aiCalled = !!data.ai_called;
		var usage = data.usage || {};
		var answer = String(data.answer || '');
		var answerType = String(data.answer_type || (aiCalled ? 'ai' : 'deterministic'));
		var trace = Array.isArray(data.source_trace) ? data.source_trace : [];
		var answerBody = answer ? '<div class="wpaic-grounded-answer-text">' + escapeHtml(answer).replace(/\n/g, '<br>') + '</div>' : '<p class="description">没有可显示的回答。</p>';
		var traceHtml = trace.map(function (source) {
			var coverage = Math.round((parseFloat(source.coverage) || 0) * 100);
			var link = source.url ? '<a href="' + escapeHtml(source.url) + '" target="_blank" rel="noopener noreferrer">打开来源 ↗</a>' : '无 URL';
			return '<div class="wpaic-source-trace-item">' +
				'<strong><code>' + escapeHtml(source.evidence_id || '') + '</code> · ' + escapeHtml(source.title || '(Untitled)') + '</strong>' +
				'<p class="description"><code>' + escapeHtml(source.source_id || '') + '</code> · Score ' + escapeHtml(source.score || 0) + ' · Coverage ' + escapeHtml(coverage) + '% · ' + link + '</p>' +
			'</div>';
		}).join('');

		return '<div class="wpaic-grounded-answer ' + (aiCalled ? 'is-ai' : 'is-deterministic') + '">' +
			'<div class="wpaic-strength-head"><strong>Grounded Answer</strong><span class="wpaic-answer-badge">' + escapeHtml(aiCalled ? 'AI ANSWER' : answerType.toUpperCase()) + '</span></div>' +
			answerBody +
			'<dl class="wpaic-strength-stats wpaic-answer-stats">' +
				'<div><dt>AI Called</dt><dd>' + (aiCalled ? 'Yes' : 'No') + '</dd></div>' +
				'<div><dt>Provider</dt><dd>' + escapeHtml(data.provider || '—') + '</dd></div>' +
				'<div><dt>Model</dt><dd>' + escapeHtml(data.model || '—') + '</dd></div>' +
				'<div><dt>Total Tokens</dt><dd>' + escapeHtml(usage.total_tokens || data.token_usage || 0) + '</dd></div>' +
				'<div><dt>Prompt Tokens</dt><dd>' + escapeHtml(usage.prompt_tokens || 0) + '</dd></div>' +
				'<div><dt>Completion Tokens</dt><dd>' + escapeHtml(usage.completion_tokens || 0) + '</dd></div>' +
				'<div><dt>Provider Elapsed</dt><dd>' + escapeHtml(data.provider_elapsed_ms || 0) + ' ms</dd></div>' +
				'<div><dt>Pipeline Elapsed</dt><dd>' + escapeHtml(data.elapsed_ms || 0) + ' ms</dd></div>' +
			'</dl>' +
			(traceHtml ? '<div class="wpaic-source-trace"><h4>Source Trace</h4>' + traceHtml + '</div>' : '') +
		'</div>';
	}

	function groundingGateHtml(data) {
		data = data || {};
		var retrieval = data.retrieval || {};
		var gate = data.gate || {};
		var decision = String(gate.decision || 'no_answer').toLowerCase();
		var allowAi = !!gate.allow_ai;
		var aiCalled = !!data.ai_called;
		var coverage = Math.round((parseFloat(gate.top_coverage) || 0) * 100);
		var labelMap = {
			allow_answer: 'ALLOW ANSWER',
			clarify: 'CLARIFY',
			no_answer: 'NO ANSWER'
		};
		var gateHtml = '<div class="wpaic-grounding-gate is-' + escapeHtml(decision) + '">' +
			'<div class="wpaic-strength-head"><strong>Grounding Gate Decision</strong><span class="wpaic-gate-badge">' + escapeHtml(labelMap[decision] || decision) + '</span></div>' +
			'<p>' + escapeHtml(gate.reason_message || '') + '</p>' +
			'<dl class="wpaic-strength-stats wpaic-gate-stats">' +
				'<div><dt>Allow AI (Policy)</dt><dd>' + (allowAi ? 'Yes' : 'No') + '</dd></div>' +
				'<div><dt>AI Called</dt><dd>' + (aiCalled ? 'Yes' : 'No') + '</dd></div>' +
				'<div><dt>Token Usage</dt><dd>' + escapeHtml(data.token_usage || 0) + '</dd></div>' +
				'<div><dt>Retrieval Strength</dt><dd>' + escapeHtml(String(gate.retrieval_strength || 'none').toUpperCase()) + '</dd></div>' +
				'<div><dt>Reliable Match</dt><dd>' + (gate.reliable_match ? 'Yes' : 'No') + '</dd></div>' +
				'<div><dt>Top Score</dt><dd>' + escapeHtml(gate.top_score || 0) + '</dd></div>' +
				'<div><dt>Score Gap</dt><dd>' + escapeHtml(gate.score_gap || 0) + '</dd></div>' +
				'<div><dt>Top Coverage</dt><dd>' + escapeHtml(coverage) + '%</dd></div>' +
			'</dl>' +
			'<p class="description">Reason Code: <code>' + escapeHtml(gate.reason_code || '') + '</code> · allow_answer 只允许进入 Usage Guard；Usage Reserve 成功后才可越过 Provider Boundary。</p>' +
		'</div>';

		return gateHtml + evidencePackHtml(data.evidence || {}) + promptPreviewHtml(data.prompt || {}) + groundedUsageGuardHtml(data.usage_guard || {}) + groundedAnswerHtml(data) + '<h3>Retrieval Diagnostics</h3>' + retrievalHtml(retrieval);
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


	function usageGuardHtml(data) {
		data = data || {};
		var decision = String(data.decision || (data.reserved ? 'allow' : 'block')).toLowerCase();
		var scopes = data.scopes || {};
		var operational = data.operational || {};
		var order = ['conversation', 'visitor', 'site'];
		var labels = { conversation: 'Conversation', visitor: 'Visitor Today', site: 'Site Today' };
		var scopeHtml = order.map(function (scope) {
			var item = scopes[scope] || {};
			var remaining = item.remaining === null || item.remaining === undefined ? 'Unlimited' : item.remaining;
			return '<div class="wpaic-usage-scope">' +
				'<h4>' + escapeHtml(labels[scope]) + '</h4>' +
				'<dl>' +
					'<dt>Enabled</dt><dd>' + (item.enabled ? 'Yes' : 'No') + '</dd>' +
					'<dt>Calls</dt><dd>' + escapeHtml(item.provider_calls !== undefined ? item.provider_calls : (item.used || 0)) + '</dd>' +
					'<dt>Limit</dt><dd>' + escapeHtml(item.limit || 0) + '</dd>' +
					'<dt>Remaining</dt><dd>' + escapeHtml(remaining) + '</dd>' +
					'<dt>Prompt Tokens</dt><dd>' + escapeHtml(item.prompt_tokens || 0) + '</dd>' +
					'<dt>Completion Tokens</dt><dd>' + escapeHtml(item.completion_tokens || 0) + '</dd>' +
					'<dt>Total Tokens</dt><dd>' + escapeHtml(item.total_tokens || 0) + '</dd>' +
					'<dt>Period</dt><dd>' + escapeHtml(item.period_key || '') + '</dd>' +
				'</dl>' +
				'<p class="description wpaic-usage-hash">Hash: <code>' + escapeHtml(item.key_hash || '(missing)') + '</code></p>' +
			'</div>';
		}).join('');
		var ops = operational.wordpress_local_day ? '<p class="description"><strong>WordPress Local:</strong> ' + escapeHtml(operational.wordpress_local_time || '') + ' · ' + escapeHtml(operational.wordpress_timezone || '') + ' · Daily Period <code>' + escapeHtml(operational.wordpress_local_day) + '</code></p>' : '';
		var reset = data.reset ? '<p><strong>Lab Reset:</strong> ' + escapeHtml((data.reset_scopes || []).join(', ') || '—') + ' · Deleted Rows: ' + escapeHtml(JSON.stringify(data.deleted_rows || {})) + '</p>' : '';
		return '<div class="wpaic-usage-decision is-' + escapeHtml(decision) + '">' +
			'<p><strong>Decision: ' + escapeHtml(decision.toUpperCase()) + '</strong></p>' +
			'<p>Reason Code: <code>' + escapeHtml(data.reason_code || '') + '</code>' +
			(data.blocked_scope ? ' · Blocked Scope: <strong>' + escapeHtml(data.blocked_scope) + '</strong>' : '') + '</p>' +
			(data.simulated_provider_call ? '<p><strong>Simulation:</strong> Reserved 1 Provider Call. AI Called = No · Token Usage = 0.</p>' : '') + reset + ops +
			'<div class="wpaic-usage-scopes">' + scopeHtml + '</div>' +
		'</div>';
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
		var retrievalTopK = document.getElementById('wpaic-retrieval-top-k');
		var groundingButton = document.getElementById('wpaic-run-grounding-gate');
		var groundingResult = document.getElementById('wpaic-grounding-result');
		var groundingQuestion = document.getElementById('wpaic-grounding-question');
		var groundingLimit = document.getElementById('wpaic-grounding-candidate-limit');
		var groundingTopK = document.getElementById('wpaic-grounding-top-k');
		var groundingConversationKey = document.getElementById('wpaic-grounding-conversation-key');
		var groundingVisitorKey = document.getElementById('wpaic-grounding-visitor-key');
		var groundingSiteKey = document.getElementById('wpaic-grounding-site-key');
		var usageCheckButton = document.getElementById('wpaic-usage-check');
		var usageSimulateButton = document.getElementById('wpaic-usage-simulate');
		var usageResetButton = document.getElementById('wpaic-usage-reset');
		var usageResult = document.getElementById('wpaic-usage-result');
		var usageConversationKey = document.getElementById('wpaic-usage-conversation-key');
		var usageVisitorKey = document.getElementById('wpaic-usage-visitor-key');
		var usageSiteKey = document.getElementById('wpaic-usage-site-key');
		var resetConversation = document.getElementById('wpaic-reset-conversation');
		var resetVisitor = document.getElementById('wpaic-reset-visitor');
		var resetSite = document.getElementById('wpaic-reset-site');
		var usageSceneTitle = document.getElementById('wpaic-usage-scene-title');
		var usageSceneSummary = document.getElementById('wpaic-usage-scene-summary');
		var usageSceneChange = document.getElementById('wpaic-usage-scene-change');
		var usageSceneAction = document.getElementById('wpaic-usage-scene-action');
		var usageSceneExpect = document.getElementById('wpaic-usage-scene-expect');
		var usagePresetButtons = document.querySelectorAll('.wpaic-usage-preset');

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
				var topK = retrievalTopK ? parseInt(retrievalTopK.value, 10) : 5;
				if (!value) {
					retrievalResult.className = 'wpaic-result is-error';
					retrievalResult.innerHTML = '<strong>失败</strong><p>请输入要检索的问题。</p>';
					return;
				}
				postRequest(
					'wpaic_retrieval_search',
					{ question: value, candidate_limit: limit || 100, top_k: topK || 5 },
					WPAICAdmin.retrievalNonce,
					retrievalButton,
					retrievalResult,
					retrievalHtml
				);
			});
		}


		if (groundingButton && groundingResult && groundingQuestion) {
			groundingButton.addEventListener('click', function () {
				var value = groundingQuestion.value.trim();
				var limit = groundingLimit ? parseInt(groundingLimit.value, 10) : 100;
				var topK = groundingTopK ? parseInt(groundingTopK.value, 10) : 5;
				if (!value) {
					groundingResult.className = 'wpaic-result is-error';
					groundingResult.innerHTML = '<strong>失败</strong><p>请输入要测试的问题。</p>';
					return;
				}
				postRequest(
					'wpaic_grounding_gate_test',
					{
						question: value,
						candidate_limit: limit || 100,
						top_k: topK || 5,
						conversation_key: groundingConversationKey ? groundingConversationKey.value.trim() : '',
						visitor_key: groundingVisitorKey ? groundingVisitorKey.value.trim() : '',
						site_key: groundingSiteKey ? groundingSiteKey.value.trim() : 'site'
					},
					WPAICAdmin.groundingNonce,
					groundingButton,
					groundingResult,
					groundingGateHtml
				);
			});
		}


		if (usagePresetButtons.length && usageConversationKey && usageVisitorKey && usageSiteKey) {
			usagePresetButtons.forEach(function (button) {
				button.addEventListener('click', function () {
					usageConversationKey.value = button.getAttribute('data-conversation') || '';
					usageVisitorKey.value = button.getAttribute('data-visitor') || '';
					usageSiteKey.value = button.getAttribute('data-site') || 'site';
					usagePresetButtons.forEach(function (item) { item.classList.remove('is-active'); });
					button.classList.add('is-active');

					if (usageSceneTitle) usageSceneTitle.textContent = button.getAttribute('data-title') || '当前测试场景';
					if (usageSceneSummary) usageSceneSummary.textContent = button.getAttribute('data-summary') || '';
					if (usageSceneChange) usageSceneChange.textContent = button.getAttribute('data-change') || '';
					if (usageSceneAction) usageSceneAction.textContent = button.getAttribute('data-action') || '';
					if (usageSceneExpect) usageSceneExpect.textContent = button.getAttribute('data-expect') || '';
				});
			});
		}

		function usagePayload() {
			return {
				conversation_key: usageConversationKey ? usageConversationKey.value.trim() : '',
				visitor_key: usageVisitorKey ? usageVisitorKey.value.trim() : '',
				site_key: usageSiteKey ? usageSiteKey.value.trim() : 'site'
			};
		}

		if (usageCheckButton && usageResult) {
			usageCheckButton.addEventListener('click', function () {
				postRequest('wpaic_usage_guard_check', usagePayload(), WPAICAdmin.usageNonce, usageCheckButton, usageResult, usageGuardHtml);
			});
		}

		if (usageSimulateButton && usageResult) {
			usageSimulateButton.addEventListener('click', function () {
				postRequest('wpaic_usage_guard_simulate', usagePayload(), WPAICAdmin.usageNonce, usageSimulateButton, usageResult, usageGuardHtml);
			});
		}

		if (usageResetButton && usageResult) {
			usageResetButton.addEventListener('click', function () {
				var payload = usagePayload();
				payload.reset_conversation = resetConversation && resetConversation.checked ? '1' : '0';
				payload.reset_visitor = resetVisitor && resetVisitor.checked ? '1' : '0';
				payload.reset_site = resetSite && resetSite.checked ? '1' : '0';
				if (payload.reset_conversation === '0' && payload.reset_visitor === '0' && payload.reset_site === '0') {
					usageResult.className = 'wpaic-result is-error';
					usageResult.innerHTML = '<strong>失败</strong><p>请至少选择一个要重置的 Usage Scope。</p>';
					return;
				}
				if (!window.confirm('只重置当前测试 Key 对应的所选 Usage Counter，继续吗？')) return;
				postRequest('wpaic_usage_guard_reset', payload, WPAICAdmin.usageNonce, usageResetButton, usageResult, usageGuardHtml);
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
