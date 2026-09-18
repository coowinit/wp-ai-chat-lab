(() => {
  'use strict';

  const config = window.WPAICChat || {};
  if (!config.endpoint || !config.inquiryEndpoint) return;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }

  function boot() {
    document.querySelectorAll('[data-wpaic-chat]').forEach((root) => {
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
    const contact = root.querySelector('[data-cw-contact]');
    const contactRow = contact ? contact.closest('.cw-contact-row') : null;
    const form = root.querySelector('[data-cw-form]');
    const input = root.querySelector('[data-cw-input]');
    const sendButton = root.querySelector('[data-cw-send]');
    const inputPlaceholder = input ? (input.getAttribute('placeholder') || '') : '';
    if (!panel || !launcher || !messages || !form || !input) return;

    let sending = false;
    let inquirySending = false;
    let typingNode = null;
    let inquiryFormNode = null;
    let inquiryFormSequence = 0;
    let state = loadState();

    restoreMessages();

    launcher.addEventListener('click', () => openChat(true));
    close && close.addEventListener('click', () => openChat(false));
    newChat && newChat.addEventListener('click', startNewConversation);
    contact && contact.addEventListener('click', () => {
      if (sending || inquirySending) return;
      openInquiryForm(
        { trigger_type: 'manual', cta_label: text('contactSales', 'Contact Sales'), placement: 'primary' },
        getLastUserQuestion(),
        null,
        text('manualMessage', 'I would like to contact your sales team.')
      );
    });

    if (quick) {
      quick.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button || sending || inquirySending) return;

        if (button.hasAttribute('data-cw-inquiry-manual')) {
          openInquiryForm(
            { trigger_type: 'manual', cta_label: text('requestQuote', 'Request a Quote'), placement: 'primary' },
            getLastUserQuestion(),
            button,
            text('quoteMessage', 'I would like to request a quote.')
          );
          return;
        }

        const value = (button.textContent || '').trim();
        if (value) sendQuestion(value);
      });
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const value = input.value.trim();
      if (!value || sending || inquirySending) return;
      input.value = '';
      sendQuestion(value);
    });

    function openChat(open) {
      panel.hidden = !open;
      launcher.hidden = open;
      launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open && window.matchMedia('(min-width: 601px)').matches && !state.blocked) input.focus();
      if (open) scrollToBottom();
    }

    function loadState() {
      const fallback = { conversationId: '', messages: [], blocked: false, pendingLeadQuestion: '' };
      try {
        const raw = sessionStorage.getItem(config.storageKey || 'wpaic_chat_session_v1');
        if (!raw) return fallback;
        const parsed = JSON.parse(raw);
        return {
          conversationId: typeof parsed.conversationId === 'string' ? parsed.conversationId : '',
          messages: Array.isArray(parsed.messages) ? parsed.messages.slice(-40).map(normalizeStoredMessage) : [],
          blocked: !!parsed.blocked,
          pendingLeadQuestion: typeof parsed.pendingLeadQuestion === 'string' ? parsed.pendingLeadQuestion.slice(0, 1000) : ''
        };
      } catch (e) {
        return fallback;
      }
    }

    function normalizeStoredMessage(item) {
      item = item && typeof item === 'object' ? item : {};
      return {
        text: typeof item.text === 'string' ? item.text : '',
        mine: !!item.mine,
        type: safeType(item.type || (item.mine ? 'user' : 'answer')),
        lead: sanitizeLead(item.lead),
        lastQuestion: typeof item.lastQuestion === 'string' ? item.lastQuestion.slice(0, 1000) : ''
      };
    }

    function saveState() {
      try {
        sessionStorage.setItem(config.storageKey || 'wpaic_chat_session_v1', JSON.stringify({
          conversationId: state.conversationId,
          messages: state.messages.slice(-40),
          blocked: state.blocked,
          pendingLeadQuestion: String(state.pendingLeadQuestion || '').slice(0, 1000)
        }));
      } catch (e) {
        // Chat remains usable even when browser storage is unavailable.
      }
    }

    function restoreMessages() {
      state.messages.forEach((item) => {
        addMessage(item.text, {
          mine: item.mine,
          type: item.type,
          persist: false,
          lead: item.lead,
          lastQuestion: item.lastQuestion
        });
      });
      updateQuickVisibility();
      updateControls();
      scrollToBottom();
    }

    function startNewConversation() {
      if (sending || inquirySending) return;
      state = { conversationId: '', messages: [], blocked: false, pendingLeadQuestion: '' };
      saveState();
      closeInquiryForm(false);
      messages.querySelectorAll('.cw-message:not(.cw-welcome), .cw-lead-offer').forEach((node) => node.remove());
      if (quick) messages.appendChild(quick);
      updateQuickVisibility();
      updateControls();
      input.value = '';
      input.focus();
    }

    function addMessage(value, options = {}) {
      const mine = !!options.mine;
      const type = safeType(options.type || (mine ? 'user' : 'answer'));
      const persist = options.persist !== false;
      const lead = sanitizeLead(options.lead);
      const lastQuestion = typeof options.lastQuestion === 'string' ? options.lastQuestion.slice(0, 1000) : '';

      const el = document.createElement('div');
      el.className = 'cw-message' + (mine ? ' cw-me' : '') + (!mine && type ? ' cw-' + type : '');
      const p = document.createElement('p');
      const small = document.createElement('small');
      p.textContent = String(value || '');
      small.textContent = mine ? text('you', 'You') : (config.supportName || 'AI Support');
      el.append(p, small);
      insertBeforeQuick(el);

      if (lead) {
        insertBeforeQuick(createLeadOffer(lead, lastQuestion));
      }

      if (persist) {
        state.messages.push({
          text: String(value || ''),
          mine,
          type,
          lead,
          lastQuestion
        });
        state.messages = state.messages.slice(-40);
        saveState();
      }

      updateQuickVisibility();
      scrollToBottom();
      return el;
    }

    function createLeadOffer(lead, lastQuestion) {
      const wrap = document.createElement('div');
      wrap.className = 'cw-lead-offer cw-lead-' + (lead.placement === 'secondary' ? 'secondary' : 'primary');

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'cw-lead-button';
      button.textContent = lead.cta_label || text('contactSales', 'Contact Sales');
      button.addEventListener('click', () => {
        if (sending || inquirySending) return;
        openInquiryForm(lead, lastQuestion, button);
      });
      wrap.append(button);
      return wrap;
    }

    function insertBeforeQuick(node) {
      if (quick && quick.parentNode === messages) messages.insertBefore(node, quick);
      else messages.append(node);
    }

    function showTyping() {
      hideTyping();
      const el = document.createElement('div');
      el.className = 'cw-message cw-typing';
      el.setAttribute('aria-label', text('typing', 'Thinking…'));
      const p = document.createElement('p');
      for (let i = 0; i < 3; i++) {
        const dot = document.createElement('span');
        dot.className = 'cw-typing-dot';
        p.append(dot);
      }
      el.append(p);
      insertBeforeQuick(el);
      typingNode = el;
      scrollToBottom();
    }

    function hideTyping() {
      if (typingNode) typingNode.remove();
      typingNode = null;
    }

    async function sendQuestion(value) {
      const question = String(value || '').trim();
      if (!question || sending || inquirySending || state.blocked) return;

      closeInquiryForm(false);
      addMessage(question, { mine: true, type: 'user' });
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
            conversation_id: state.conversationId || '',
            lead_context_question: state.pendingLeadQuestion || ''
          })
        });

        let payload = null;
        try { payload = await response.json(); } catch (e) { payload = null; }
        hideTyping();

        if (payload && typeof payload.conversation_id === 'string' && payload.conversation_id) {
          state.conversationId = payload.conversation_id;
        }

        if (!response.ok || !payload || payload.success === false) {
          const message = payload && payload.message ? payload.message : text('networkError', 'The chat is temporarily unavailable. Please try again.');
          addMessage(message, { mine: false, type: 'error' });
          saveState();
          return;
        }

        const type = safeType(payload.type || 'error');
        const message = payload.message || text('networkError', 'The chat is temporarily unavailable. Please try again.');
        const priorPendingLeadQuestion = String(state.pendingLeadQuestion || '');
        const pendingLead = payload.lead_pending && payload.lead_pending.trigger_type === 'commercial_intent';
        const lead = sanitizeLead(payload.lead);
        const leadQuestion = lead && lead.trigger_type === 'commercial_intent' && priorPendingLeadQuestion
          ? priorPendingLeadQuestion
          : question;

        addMessage(message, { mine: false, type, lead, lastQuestion: leadQuestion });

        if (pendingLead) {
          // Preserve the original commercial question across one or more clarify
          // turns. The server re-validates this text before emitting a real CTA.
          if (!priorPendingLeadQuestion) state.pendingLeadQuestion = question.slice(0, 1000);
        } else if (lead || type !== 'clarify') {
          // Pending intent is one clarification chain, not a conversation-wide
          // permanent flag. Network / HTTP errors return above and keep it.
          state.pendingLeadQuestion = '';
        }

        if (type === 'blocked') state.blocked = true;
        saveState();
      } catch (error) {
        hideTyping();
        addMessage(text('networkError', 'The chat is temporarily unavailable. Please try again.'), { mine: false, type: 'error' });
      } finally {
        setSending(false);
      }
    }

    function openInquiryForm(rawLead, rawLastQuestion, sourceButton = null, defaultMessage = '') {
      const lead = sanitizeLead(rawLead) || {
        trigger_type: 'manual',
        cta_label: text('contactSales', 'Contact Sales'),
        placement: 'primary'
      };
      const lastQuestion = String(rawLastQuestion || getLastUserQuestion() || '').trim().slice(0, 1000);

      closeInquiryForm(false);

      const card = document.createElement('section');
      card.className = 'cw-inquiry-card';
      card.setAttribute('aria-label', lead.cta_label || text('contactSales', 'Contact Sales'));

      const header = document.createElement('div');
      header.className = 'cw-inquiry-head';
      const title = document.createElement('strong');
      title.textContent = lead.cta_label || text('contactSales', 'Contact Sales');
      const cancel = document.createElement('button');
      cancel.type = 'button';
      cancel.className = 'cw-inquiry-close';
      cancel.setAttribute('aria-label', text('inquiryCancel', 'Cancel'));
      cancel.textContent = '×';
      cancel.addEventListener('click', () => closeInquiryForm(true));
      header.append(title, cancel);

      const inquiryForm = document.createElement('form');
      inquiryForm.className = 'cw-inquiry-form';
      inquiryForm.noValidate = true;
      inquiryForm.autocomplete = 'off';
      const formToken = 'wpaic_inquiry_' + Date.now() + '_' + (++inquiryFormSequence);

      const name = createField(text('inquiryName', 'Name'), 'text', true, 120, 'name', formToken);
      const email = createField(text('inquiryEmail', 'Email'), 'email', true, 191, 'email', formToken);
      const company = createField(text('inquiryCompany', 'Company'), 'text', false, 191, 'company', formToken);
      const phone = createField(text('inquiryPhone', 'Phone / WhatsApp'), 'text', false, 80, 'phone', formToken);
      const message = createTextarea(text('inquiryMessage', 'Message'), true, 5000, 'message', formToken);
      message.input.value = lastQuestion || String(defaultMessage || '').trim() || text('manualMessage', 'I would like to contact your sales team.');

      const honeypot = document.createElement('label');
      honeypot.className = 'cw-honeypot';
      honeypot.setAttribute('aria-hidden', 'true');
      honeypot.textContent = 'Website';
      const website = document.createElement('input');
      website.type = 'text';
      website.name = 'website';
      website.tabIndex = -1;
      website.autocomplete = 'off';
      honeypot.append(website);

      const error = document.createElement('div');
      error.className = 'cw-inquiry-error';
      error.setAttribute('role', 'alert');
      error.hidden = true;

      const actions = document.createElement('div');
      actions.className = 'cw-inquiry-actions';
      const submit = document.createElement('button');
      submit.type = 'submit';
      submit.className = 'cw-inquiry-submit';
      submit.textContent = text('inquirySubmit', 'Submit Request');
      const cancelButton = document.createElement('button');
      cancelButton.type = 'button';
      cancelButton.className = 'cw-inquiry-cancel';
      cancelButton.textContent = text('inquiryCancel', 'Cancel');
      cancelButton.addEventListener('click', () => closeInquiryForm(true));
      actions.append(submit, cancelButton);

      inquiryForm.append(
        name.wrap,
        email.wrap,
        company.wrap,
        phone.wrap,
        message.wrap,
        honeypot,
        error,
        actions
      );
      card.append(header, inquiryForm);
      insertBeforeQuick(card);
      inquiryFormNode = card;
      root.classList.add('cw-inquiry-open');
      if (quick) quick.hidden = true;
      updateControls();
      scrollToBottom();

      if (window.matchMedia('(min-width: 601px)').matches) name.input.focus();

      inquiryForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (inquirySending) return;

        const nameValue = name.input.value.trim();
        const emailValue = email.input.value.trim();
        const messageValue = message.input.value.trim();
        if (!nameValue) {
          showInquiryError(error, text('inquiryNameRequired', 'Please enter your name.'));
          return;
        }
        if (!emailValue) {
          showInquiryError(error, text('inquiryEmailRequired', 'Please enter your email address.'));
          return;
        }
        if (!email.input.checkValidity()) {
          showInquiryError(error, text('inquiryEmailInvalid', 'Please enter a valid email address.'));
          return;
        }
        if (!messageValue) {
          showInquiryError(error, text('inquiryMessageRequired', 'Please enter your message.'));
          return;
        }

        setInquirySending(true, inquiryForm);
        cancel.disabled = true;
        showInquiryError(error, '');

        try {
          const response = await fetch(config.inquiryEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              name: nameValue,
              email: emailValue,
              company: company.input.value.trim(),
              phone: phone.input.value.trim(),
              message: messageValue,
              trigger_type: lead.trigger_type,
              conversation_id: state.conversationId || '',
              last_question: lastQuestion,
              source_url: window.location.href,
              website: website.value.trim()
            })
          });

          let payload = null;
          try { payload = await response.json(); } catch (e) { payload = null; }

          if (!response.ok || !payload || payload.type !== 'submitted') {
            showInquiryError(error, payload && payload.message ? payload.message : text('inquiryError', 'The inquiry could not be submitted. Please try again.'));
            return;
          }

          if (sourceButton) {
            sourceButton.disabled = true;
            sourceButton.classList.add('cw-lead-submitted');
          }
          closeInquiryForm(false);
          addMessage(payload.message, { mine: false, type: 'answer' });
        } catch (e) {
          showInquiryError(error, text('inquiryError', 'The inquiry could not be submitted. Please try again.'));
        } finally {
          cancel.disabled = false;
          setInquirySending(false, inquiryForm);
        }
      });
    }

    function createField(labelText, type, required, maxLength, fieldKey, formToken) {
      const wrap = document.createElement('label');
      wrap.className = 'cw-inquiry-field';
      const label = document.createElement('span');
      label.textContent = labelText + (required ? ' *' : '');
      const inputEl = document.createElement('input');
      inputEl.type = type;
      inputEl.required = required;
      inputEl.maxLength = maxLength;
      inputEl.autocomplete = 'off';
      inputEl.name = formToken + '_' + fieldKey;
      wrap.append(label, inputEl);
      return { wrap, input: inputEl };
    }

    function createTextarea(labelText, required, maxLength, fieldKey, formToken) {
      const wrap = document.createElement('label');
      wrap.className = 'cw-inquiry-field';
      const label = document.createElement('span');
      label.textContent = labelText + (required ? ' *' : '');
      const inputEl = document.createElement('textarea');
      inputEl.required = required;
      inputEl.maxLength = maxLength;
      inputEl.rows = 4;
      inputEl.autocomplete = 'off';
      inputEl.name = formToken + '_' + fieldKey;
      wrap.append(label, inputEl);
      return { wrap, input: inputEl };
    }

    function closeInquiryForm(restoreFocus) {
      if (inquiryFormNode) {
        const openForm = inquiryFormNode.querySelector('form');
        if (openForm) {
          openForm.reset();
          openForm.querySelectorAll('input, textarea').forEach((node) => { node.value = ''; });
        }
        inquiryFormNode.remove();
      }
      inquiryFormNode = null;
      root.classList.remove('cw-inquiry-open');
      updateQuickVisibility();
      updateControls();
      if (restoreFocus && !state.blocked && window.matchMedia('(min-width: 601px)').matches) input.focus();
    }

    function showInquiryError(node, message) {
      node.textContent = String(message || '');
      node.hidden = !message;
      if (message) scrollToBottom();
    }

    function setInquirySending(value, inquiryForm) {
      inquirySending = !!value;
      root.classList.toggle('cw-inquiry-busy', inquirySending);
      if (inquiryForm) {
        inquiryForm.querySelectorAll('input, textarea, button').forEach((node) => {
          node.disabled = inquirySending;
        });
      }
      updateControls();
    }

    function setSending(value) {
      sending = !!value;
      root.classList.toggle('cw-busy', sending);
      updateControls();
    }

    function updateControls() {
      const inquiryOpen = !!inquiryFormNode;
      const disabled = sending || inquirySending || state.blocked || inquiryOpen;
      input.disabled = disabled;
      input.setAttribute('aria-disabled', disabled ? 'true' : 'false');
      input.placeholder = inquiryOpen ? text('inquiryOpenPlaceholder', 'Inquiry form is open') : inputPlaceholder;
      if (sendButton) {
        sendButton.disabled = disabled;
        sendButton.setAttribute('aria-disabled', disabled ? 'true' : 'false');
      }
      form.classList.toggle('cw-form-disabled', inquiryOpen);
      form.setAttribute('aria-disabled', inquiryOpen ? 'true' : 'false');
      if (quick) quick.querySelectorAll('button').forEach((button) => { button.disabled = disabled || inquirySending; });
      if (contact) {
        contact.disabled = sending || inquirySending || inquiryOpen;
        contact.hidden = inquiryOpen;
      }
      if (contactRow) {
        contactRow.hidden = inquiryOpen;
        contactRow.classList.toggle('cw-hidden-by-inquiry', inquiryOpen);
      }
    }

    function updateQuickVisibility() {
      if (!quick) return;
      const hasUserMessage = state.messages.some((item) => item && item.mine);
      quick.hidden = hasUserMessage || !!inquiryFormNode;
    }

    function getLastUserQuestion() {
      for (let i = state.messages.length - 1; i >= 0; i--) {
        const item = state.messages[i];
        if (item && item.mine && typeof item.text === 'string' && item.text.trim()) return item.text.trim();
      }
      return '';
    }

    function sanitizeLead(raw) {
      if (!raw || typeof raw !== 'object') return null;
      const trigger = String(raw.trigger_type || '');
      const allowedTriggers = ['manual', 'commercial_intent', 'no_answer', 'usage_blocked'];
      if (!allowedTriggers.includes(trigger)) return null;
      const placement = raw.placement === 'secondary' ? 'secondary' : 'primary';
      const label = String(raw.cta_label || '').trim().slice(0, 80);
      return {
        trigger_type: trigger,
        cta_label: label || text('contactSales', 'Contact Sales'),
        placement
      };
    }

    function safeType(type) {
      const allowed = ['answer', 'clarify', 'no-answer', 'blocked', 'error', 'user'];
      type = String(type || '').replace('_', '-');
      return allowed.includes(type) ? type : 'answer';
    }

    function text(key, fallback) {
      return config.i18n && config.i18n[key] ? config.i18n[key] : fallback;
    }

    function scrollToBottom() {
      messages.scrollTop = messages.scrollHeight;
    }
  }
})();
