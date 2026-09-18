(() => {
  'use strict';

  const config = window.WPAICChat || {};
  if (!config.endpoint) return;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }

  function boot() {
    const roots = document.querySelectorAll('[data-wpaic-chat]');
    roots.forEach((root) => {
      if (root.dataset.wpaicReady === '1') return;
      root.dataset.wpaicReady = '1';
      initChat(root);
    });
  }

  function initChat(root) {
    const panel = root.querySelector('[data-cw-panel]');
    const launcher = root.querySelector('[data-cw-launcher]');
    const close = root.querySelector('[data-cw-close]');
    const newChat = root.querySelector('[data-cw-new]');
    const messages = root.querySelector('[data-cw-messages]');
    const quick = root.querySelector('[data-cw-quick]');
    const form = root.querySelector('[data-cw-form]');
    const input = root.querySelector('[data-cw-input]');
    const sendButton = root.querySelector('[data-cw-send]');
    const welcome = root.querySelector('.cw-welcome');
    if (!panel || !launcher || !messages || !form || !input) return;

    let sending = false;
    let typingNode = null;
    let state = loadState();

    restoreMessages();

    launcher.addEventListener('click', () => openChat(true));
    close && close.addEventListener('click', () => openChat(false));
    newChat && newChat.addEventListener('click', startNewConversation);

    if (quick) {
      quick.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button || sending) return;
        const text = (button.textContent || '').trim();
        if (text) sendQuestion(text);
      });
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const text = input.value.trim();
      if (!text || sending) return;
      input.value = '';
      sendQuestion(text);
    });

    function openChat(open) {
      panel.hidden = !open;
      launcher.hidden = open;
      launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open && window.matchMedia('(min-width: 601px)').matches) input.focus();
      if (open) scrollToBottom();
    }

    function loadState() {
      const fallback = { conversationId: '', messages: [], blocked: false };
      try {
        const raw = sessionStorage.getItem(config.storageKey || 'wpaic_chat_session_v1');
        if (!raw) return fallback;
        const parsed = JSON.parse(raw);
        return {
          conversationId: typeof parsed.conversationId === 'string' ? parsed.conversationId : '',
          messages: Array.isArray(parsed.messages) ? parsed.messages.slice(-40) : [],
          blocked: !!parsed.blocked
        };
      } catch (e) {
        return fallback;
      }
    }

    function saveState() {
      try {
        sessionStorage.setItem(config.storageKey || 'wpaic_chat_session_v1', JSON.stringify({
          conversationId: state.conversationId,
          messages: state.messages.slice(-40),
          blocked: state.blocked
        }));
      } catch (e) {
        // Chat remains usable even when storage is unavailable.
      }
    }

    function restoreMessages() {
      state.messages.forEach((item) => addMessage(item.text, item.mine, item.type, false));
      updateQuickVisibility();
      updateControls();
      scrollToBottom();
    }

    function startNewConversation() {
      if (sending) return;
      state = { conversationId: '', messages: [], blocked: false };
      saveState();
      messages.querySelectorAll('.cw-message:not(.cw-welcome)').forEach((node) => node.remove());
      if (quick) messages.appendChild(quick);
      updateQuickVisibility();
      updateControls();
      input.value = '';
      input.focus();
    }

    function addMessage(text, mine = false, type = 'answer', persist = true) {
      const el = document.createElement('div');
      el.className = 'cw-message' + (mine ? ' cw-me' : '') + (!mine && type ? ' cw-' + safeType(type) : '');
      const p = document.createElement('p');
      const small = document.createElement('small');
      p.textContent = String(text || '');
      small.textContent = mine ? ((config.i18n && config.i18n.you) || 'You') : (config.supportName || 'AI Support');
      el.append(p, small);
      if (quick && quick.parentNode === messages) messages.insertBefore(el, quick);
      else messages.append(el);
      if (persist) {
        state.messages.push({ text: String(text || ''), mine: !!mine, type: safeType(type) });
        state.messages = state.messages.slice(-40);
        saveState();
      }
      updateQuickVisibility();
      scrollToBottom();
      return el;
    }

    function showTyping() {
      hideTyping();
      const el = document.createElement('div');
      el.className = 'cw-message cw-typing';
      el.setAttribute('aria-label', (config.i18n && config.i18n.typing) || 'Thinking…');
      const p = document.createElement('p');
      for (let i = 0; i < 3; i++) {
        const dot = document.createElement('span');
        dot.className = 'cw-typing-dot';
        p.append(dot);
      }
      el.append(p);
      if (quick && quick.parentNode === messages) messages.insertBefore(el, quick);
      else messages.append(el);
      typingNode = el;
      scrollToBottom();
    }

    function hideTyping() {
      if (typingNode) typingNode.remove();
      typingNode = null;
    }

    async function sendQuestion(text) {
      const question = String(text || '').trim();
      if (!question || sending || state.blocked) return;

      addMessage(question, true, 'user');
      setSending(true);
      showTyping();

      try {
        const response = await fetch(config.endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            question,
            conversation_id: state.conversationId || ''
          })
        });

        let payload = null;
        try { payload = await response.json(); } catch (e) { payload = null; }
        hideTyping();

        if (payload && typeof payload.conversation_id === 'string' && payload.conversation_id) {
          state.conversationId = payload.conversation_id;
        }

        if (!response.ok || !payload || payload.success === false) {
          const message = payload && payload.message ? payload.message : ((config.i18n && config.i18n.networkError) || 'The chat is temporarily unavailable. Please try again.');
          addMessage(message, false, 'error');
          saveState();
          return;
        }

        const type = safeType(payload.type || 'error');
        const message = payload.message || ((config.i18n && config.i18n.networkError) || 'The chat is temporarily unavailable. Please try again.');
        addMessage(message, false, type);
        if (type === 'blocked') state.blocked = true;
        saveState();
      } catch (error) {
        hideTyping();
        addMessage((config.i18n && config.i18n.networkError) || 'The chat is temporarily unavailable. Please try again.', false, 'error');
      } finally {
        setSending(false);
      }
    }

    function setSending(value) {
      sending = !!value;
      root.classList.toggle('cw-busy', sending);
      updateControls();
    }

    function updateControls() {
      const disabled = sending || state.blocked;
      input.disabled = disabled;
      if (sendButton) sendButton.disabled = disabled;
      if (quick) quick.querySelectorAll('button').forEach((button) => { button.disabled = disabled; });
    }

    function updateQuickVisibility() {
      if (!quick) return;
      const hasUserMessage = state.messages.some((item) => item && item.mine);
      quick.hidden = hasUserMessage;
    }

    function safeType(type) {
      const allowed = ['answer', 'clarify', 'no_answer', 'no-answer', 'blocked', 'error', 'user'];
      type = String(type || '').replace('_', '-');
      return allowed.includes(type) ? type : 'answer';
    }

    function scrollToBottom() {
      messages.scrollTop = messages.scrollHeight;
    }
  }
})();
