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

	function request(action, payload, button, resultEl) {
		if (!button || button.disabled) return;

		setBusy(button, true);
		resultEl.className = 'wpaic-result is-loading';
		resultEl.textContent = WPAICAdmin.i18n.working;

		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', WPAICAdmin.nonce);
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
				resultEl.innerHTML = '<strong>成功</strong>' +
					(json.data.content ? '<div class="wpaic-response-text">' + escapeHtml(json.data.content) + '</div>' : '') +
					metaHtml(json.data);
			})
			.catch(function (error) {
				resultEl.className = 'wpaic-result is-error';
				resultEl.innerHTML = '<strong>失败</strong><p>' + escapeHtml(error.message || WPAICAdmin.i18n.error) + '</p>';
			})
			.finally(function () {
				setBusy(button, false);
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var connectionButton = document.getElementById('wpaic-test-connection');
		var connectionResult = document.getElementById('wpaic-connection-result');
		var chatButton = document.getElementById('wpaic-test-chat');
		var chatResult = document.getElementById('wpaic-chat-result');
		var question = document.getElementById('wpaic-test-question');

		if (connectionButton && connectionResult) {
			connectionButton.addEventListener('click', function () {
				request('wpaic_test_connection', {}, connectionButton, connectionResult);
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
				request('wpaic_test_chat', { question: value }, chatButton, chatResult);
			});
		}
	});
}());
