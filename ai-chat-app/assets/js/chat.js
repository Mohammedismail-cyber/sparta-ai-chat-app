(function () {
  'use strict';

  const el = (id) => document.getElementById(id);

  const historyList = el('history-list');
  const historyLabel = el('history-label');
  const clearFilterBtn = el('clear-filter-btn');
  const projectList = el('project-list');
  const addProjectBtn = el('add-project-btn');
  const streamEl = el('stream');
  const greetingEl = el('greeting');
  const composerForm = el('composer-form');
  const composerInput = el('composer-input');
  const sendBtn = el('send-btn');
  const replyModeBtn = el('reply-mode');
  const newChatBtn = el('new-chat-btn');
  const convTitleEl = el('conv-title');
  const logoutBtn = el('logout-btn');
  const menuBtn = el('menu-btn');
  const studio = el('studio');
  const studioKind = el('studio-kind');
  const studioTitle = el('studio-title');
  const studioFrame = el('studio-frame');
  const studioIframe = el('studio-iframe');
  const studioOpen = el('studio-open');
  const studioClose = el('studio-close');
  const sidebar = el('sidebar');
  const scrim = el('scrim');
  const kebabBtn = el('kebab-btn');
  const moveMenu = el('move-menu');
  const moveMenuList = el('move-menu-list');
  const attachBtn = el('attach-btn');
  const screenshotBtn = el('screenshot-btn');
  const fileInput = el('file-input');
  const pendingAttachmentsEl = el('pending-attachments');
  const projectModal = el('project-modal');
  const projectNameInput = el('project-name-input');
  const lightbox = el('lightbox');
  const lightboxImg = el('lightbox-img');

  let currentConversationId = null;
  let currentProjectFilter = null;
  let conversations = [];
  let projects = [];
  let pendingAttachments = [];
  let sending = false;
  let replyMode = localStorage.getItem('sparta-reply-mode') === 'fast' ? 'fast' : 'think';

  function setReplyMode(mode) {
    replyMode = mode === 'fast' ? 'fast' : 'think';
    localStorage.setItem('sparta-reply-mode', replyMode);
    if (replyModeBtn) {
      replyModeBtn.dataset.mode = replyMode;
      replyModeBtn.textContent = replyMode === 'fast' ? 'Fast' : 'Thinking';
      replyModeBtn.title = replyMode === 'fast' ? 'Fast reply — click for thinking' : 'Thinking reply — click for fast';
    }
  }
  setReplyMode(replyMode);
  replyModeBtn?.addEventListener('click', () => setReplyMode(replyMode === 'fast' ? 'think' : 'fast'));

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  menuBtn?.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    scrim.classList.toggle('open');
  });
  scrim?.addEventListener('click', () => {
    sidebar.classList.remove('open');
    scrim.classList.remove('open');
    closePopovers();
  });

  function closePopovers() {
    moveMenu.classList.remove('open');
  }

  document.querySelectorAll('.nav-group-h').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      if (e.target.closest('.clear-filter-btn')) return;
      const group = btn.closest('.nav-group');
      group.classList.toggle('open');
      btn.setAttribute('aria-expanded', group.classList.contains('open') ? 'true' : 'false');
    });
  });

  document.addEventListener('click', (e) => {
    const t = e.target;
    if (moveMenu.classList.contains('open') && !moveMenu.contains(t) && t !== kebabBtn && !kebabBtn.contains(t)) {
      moveMenu.classList.remove('open');
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closePopovers();
      closeStudio();
    }
  });

  function openStudio(artifact) {
    if (!artifact || !studio) return;
    const labels = { website: 'Website', mobile: 'Mobile app', image: 'Image', video: 'Motion', document: 'Document', slides: 'Slides' };
    studio.hidden = false;
    document.querySelector('.app-shell')?.classList.add('has-studio');
    studioKind.textContent = labels[artifact.kind] || 'Preview';
    studioTitle.textContent = artifact.title || '';
    studioOpen.href = artifact.url;
    studioFrame.classList.toggle('phone', artifact.kind === 'mobile');
    studioIframe.src = artifact.url;
  }

  function closeStudio() {
    if (!studio) return;
    studio.hidden = true;
    document.querySelector('.app-shell')?.classList.remove('has-studio');
    studioIframe.src = 'about:blank';
  }

  studioClose?.addEventListener('click', closeStudio);

  logoutBtn.addEventListener('click', async (e) => {
    e.stopPropagation();
    logoutBtn.disabled = true;
    const label = logoutBtn.querySelector('span');
    if (label) label.textContent = 'Signing out…';
    try {
      await fetch('api/logout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: window.CSRF_TOKEN }),
      });
    } catch (_) { /* still leave */ }
    window.location.href = 'login.php';
  });

  async function loadProjects() {
    const res = await fetch('api/projects.php');
    const data = await res.json();
    if (!data.ok) return;
    projects = data.projects;
    renderProjects();
    renderMoveMenu();
  }

  function renderProjects() {
    projectList.innerHTML = '';
    if (!projects.length) return;
    projects.forEach((p, i) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'project-pill' + (currentProjectFilter === p.id ? ' active' : '');
      item.style.setProperty('--i', String(i));
      item.innerHTML =
        '<span class="pname">' + escapeHtml(p.name) + '</span>' +
        '<span class="count">' + p.chat_count + '</span>' +
        '<span class="del" data-id="' + p.id + '" title="Remove">✕</span>';
      item.addEventListener('click', (e) => {
        if (e.target.closest('.del')) return;
        setProjectFilter(currentProjectFilter === p.id ? null : p.id);
      });
      item.querySelector('.del').addEventListener('click', (e) => {
        e.stopPropagation();
        deleteProject(p.id);
      });
      projectList.appendChild(item);
    });
  }

  function renderMoveMenu() {
    let html = '<button class="menu-row" data-project="">Inbox<span class="check">' +
      (!getCurrentConversation()?.project_id ? '✓' : '') + '</span></button>';
    projects.forEach((p) => {
      const isCurrent = getCurrentConversation()?.project_id === p.id;
      html += '<button class="menu-row" data-project="' + p.id + '">' +
        escapeHtml(p.name) + '<span class="check">' + (isCurrent ? '✓' : '') + '</span></button>';
    });
    moveMenuList.innerHTML = html;
    moveMenuList.querySelectorAll('.menu-row').forEach((btn) => {
      btn.addEventListener('click', () => {
        const pid = btn.dataset.project ? parseInt(btn.dataset.project, 10) : null;
        moveConversationToProject(pid);
      });
    });
  }

  function getCurrentConversation() {
    return conversations.find((c) => c.id === currentConversationId) || null;
  }

  async function createProject(name) {
    const res = await fetch('api/projects.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, csrf: window.CSRF_TOKEN }),
    });
    const data = await res.json();
    if (data.ok) {
      await loadProjects();
    }
    return data;
  }

  async function deleteProject(id) {
    const params = new URLSearchParams({ id, csrf: window.CSRF_TOKEN });
    await fetch('api/projects.php?' + params.toString(), { method: 'DELETE' });
    if (currentProjectFilter === id) {
      setProjectFilter(null);
    }
    await loadProjects();
    await loadConversations();
  }

  function setProjectFilter(projectId) {
    currentProjectFilter = projectId;
    renderProjects();
    if (projectId) {
      const proj = projects.find((p) => p.id === projectId);
      historyLabel.textContent = proj ? proj.name : 'Chats';
      clearFilterBtn.style.display = 'inline-block';
    } else {
      historyLabel.textContent = 'Chats';
      clearFilterBtn.style.display = 'none';
    }
    loadConversations();
  }

  clearFilterBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    setProjectFilter(null);
  });
  addProjectBtn.addEventListener('click', () => openProjectModal());

  function openProjectModal() {
    projectNameInput.value = '';
    projectModal.classList.add('open');
    setTimeout(() => projectNameInput.focus(), 50);
  }
  function closeProjectModal() {
    projectModal.classList.remove('open');
  }
  el('project-modal-cancel').addEventListener('click', closeProjectModal);
  projectModal.addEventListener('click', (e) => { if (e.target === projectModal) closeProjectModal(); });
  el('project-modal-confirm').addEventListener('click', async () => {
    const name = projectNameInput.value.trim();
    if (!name) return;
    await createProject(name);
    closeProjectModal();
  });
  projectNameInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); el('project-modal-confirm').click(); }
  });

  kebabBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const willOpen = !moveMenu.classList.contains('open');
    closePopovers();
    if (willOpen) {
      renderMoveMenu();
      moveMenu.classList.add('open');
    }
  });

  async function moveConversationToProject(projectId) {
    if (!currentConversationId) {
      closePopovers();
      return;
    }
    await fetch('api/conversations.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'move', id: currentConversationId, project_id: projectId, csrf: window.CSRF_TOKEN }),
    });
    closePopovers();
    await loadProjects();
    await loadConversations();
  }

  async function loadConversations() {
    let url = 'api/conversations.php';
    if (currentProjectFilter) {
      url += '?project_id=' + currentProjectFilter;
    }
    const res = await fetch(url);
    const data = await res.json();
    if (!data.ok) return;
    conversations = data.conversations;
    renderHistory();
  }

  function renderHistory() {
    if (!conversations.length) {
      historyList.innerHTML = '<div class="history-empty">Your conversations will show up here.</div>';
      return;
    }
    historyList.innerHTML = '';
    conversations.forEach((c, i) => {
      const item = document.createElement('div');
      item.className = 'history-item' + (c.id === currentConversationId ? ' active' : '');
      item.style.setProperty('--i', String(i));
      item.innerHTML =
        '<span class="title">' + escapeHtml(c.title) + '</span>' +
        '<button class="del" data-id="' + c.id + '" title="Delete">✕</button>';
      item.addEventListener('click', (e) => {
        if (e.target.closest('.del')) return;
        openConversation(c.id, c.title);
        sidebar.classList.remove('open');
        scrim.classList.remove('open');
      });
      item.querySelector('.del').addEventListener('click', (e) => {
        e.stopPropagation();
        deleteConversation(c.id);
      });
      historyList.appendChild(item);
    });
  }

  async function deleteConversation(id) {
    const params = new URLSearchParams({ id, csrf: window.CSRF_TOKEN });
    await fetch('api/conversations.php?' + params.toString(), { method: 'DELETE' });
    conversations = conversations.filter((c) => c.id !== id);
    if (currentConversationId === id) {
      startNewChat();
    }
    renderHistory();
  }

  async function openConversation(id, title) {
    currentConversationId = id;
    convTitleEl.textContent = title || 'Chat';
    renderHistory();
    clearPendingAttachments();

    const res = await fetch('api/get_history.php?conversation_id=' + id);
    const data = await res.json();
    if (!data.ok) return;

    streamEl.innerHTML = '';
    if (data.messages.length) {
      setGreetingVisible(false);
      data.messages.forEach((m) => appendMessage(m.role, m.content, m.attachments || [], m.artifact || null));
    } else {
      setGreetingVisible(true);
    }
  }

  function startNewChat() {
    currentConversationId = null;
    convTitleEl.textContent = 'New chat';
    streamEl.innerHTML = '';
    setGreetingVisible(true);
    clearPendingAttachments();
    renderHistory();
    composerInput.focus();
  }

  newChatBtn.addEventListener('click', startNewChat);

  function iconForMime(mime) {
    if (mime === 'application/pdf') return 'PDF';
    if (mime && mime.startsWith('text/')) return 'TXT';
    return 'FILE';
  }

  function renderPendingAttachments() {
    pendingAttachmentsEl.innerHTML = '';
    pendingAttachments.forEach((att) => {
      const chip = document.createElement('div');
      chip.className = 'pending-chip' + (att.uploading ? ' uploading' : '');
      const thumb = att.kind === 'image' && att.url
        ? '<img class="thumb" src="' + att.url + '" alt="">'
        : '<div class="thumb file-icon">' + iconForMime(att.mime_type || '') + '</div>';
      chip.innerHTML = thumb +
        '<span class="pname">' + escapeHtml(att.uploading ? 'Uploading…' : att.original_name) + '</span>' +
        '<button class="remove" title="Remove">✕</button>';
      chip.querySelector('.remove').addEventListener('click', () => {
        pendingAttachments = pendingAttachments.filter((a) => a !== att);
        renderPendingAttachments();
      });
      pendingAttachmentsEl.appendChild(chip);
    });
  }

  function clearPendingAttachments() {
    pendingAttachments = [];
    renderPendingAttachments();
  }

  async function uploadFile(file) {
    const placeholder = { uploading: true, original_name: file.name, kind: file.type.startsWith('image/') ? 'image' : 'file' };
    pendingAttachments.push(placeholder);
    renderPendingAttachments();

    const form = new FormData();
    form.append('file', file);
    form.append('csrf', window.CSRF_TOKEN);
    if (currentConversationId) form.append('conversation_id', currentConversationId);

    try {
      const res = await fetch('api/upload.php', { method: 'POST', body: form });
      const data = await res.json();
      const idx = pendingAttachments.indexOf(placeholder);
      if (idx === -1) return;

      if (!data.ok) {
        pendingAttachments.splice(idx, 1);
        renderPendingAttachments();
        appendError(data.error || 'Could not upload that file.');
        return;
      }
      pendingAttachments[idx] = data.attachment;
      renderPendingAttachments();
    } catch (e) {
      pendingAttachments = pendingAttachments.filter((a) => a !== placeholder);
      renderPendingAttachments();
      appendError("Couldn't upload — check that PHP is running.");
    }
  }

  attachBtn.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => {
    Array.from(fileInput.files || []).forEach(uploadFile);
    fileInput.value = '';
  });

  screenshotBtn.addEventListener('click', async () => {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
      appendError('Screenshot capture is not supported in this browser.');
      return;
    }
    let stream;
    try {
      stream = await navigator.mediaDevices.getDisplayMedia({ video: true });
    } catch (e) {
      return;
    }

    try {
      const video = document.createElement('video');
      video.srcObject = stream;
      video.muted = true;
      await video.play();
      await new Promise((resolve) => {
        if (video.readyState >= 2) return resolve();
        video.addEventListener('loadeddata', resolve, { once: true });
      });

      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0);

      stream.getTracks().forEach((t) => t.stop());

      canvas.toBlob((blob) => {
        if (!blob) return;
        const file = new File([blob], 'screenshot-' + Date.now() + '.png', { type: 'image/png' });
        uploadFile(file);
      }, 'image/png');
    } catch (e) {
      stream.getTracks().forEach((t) => t.stop());
      appendError("Couldn't capture the screenshot.");
    }
  });

  function setGreetingVisible(visible) {
    if (visible) {
      greetingEl.classList.remove('hidden');
      streamEl.classList.add('hidden');
    } else {
      greetingEl.classList.add('hidden');
      streamEl.classList.remove('hidden');
    }
  }

  function renderAttachmentsHtml(attachments) {
    if (!attachments || !attachments.length) return '';
    let html = '<div class="msg-attachments">';
    attachments.forEach((att) => {
      if (att.kind === 'image') {
        html += '<img class="msg-attachment-img" src="' + att.url + '" data-full="' + att.url + '" alt="' + escapeHtml(att.original_name) + '">';
      } else {
        html += '<a class="msg-attachment-file" href="' + att.url + '" target="_blank" rel="noopener">' +
          '<span class="fbadge">' + iconForMime(att.mime_type || '') + '</span>' +
          '<span class="fname">' + escapeHtml(att.original_name) + '</span></a>';
      }
    });
    html += '</div>';
    return html;
  }

  function appendMessage(role, content, attachments, artifact) {
    const row = document.createElement('div');
    row.className = 'msg-row ' + role;

    if (role === 'assistant') {
      const avatar = document.createElement('div');
      avatar.className = 'avatar-sm';
      avatar.textContent = 'S';
      row.appendChild(avatar);
    }

    const bubble = document.createElement('div');
    bubble.className = 'bubble' + (role === 'assistant' ? ' assistant' : '');

    const attHtml = renderAttachmentsHtml(attachments);
    const textHtml = content ? '<div class="msg-text"></div>' : '';
    bubble.innerHTML = attHtml + textHtml;
    if (content) {
      fillAssistantText(bubble.querySelector('.msg-text'), content, role);
    }

    bubble.querySelectorAll('.msg-attachment-img').forEach((img) => {
      img.addEventListener('click', () => openLightbox(img.dataset.full));
    });

    if (artifact && artifact.url) {
      const card = document.createElement('button');
      card.type = 'button';
      card.className = 'artifact-card';
      const kindLabel = { website: 'Website', mobile: 'Mobile app', image: 'Image', video: 'Motion', document: 'Document', slides: 'Slides' }[artifact.kind] || 'Preview';
      card.innerHTML = '<span class="akind">' + escapeHtml(kindLabel) + '</span><span class="atitle">' + escapeHtml(artifact.title || 'Open preview') + '</span>';
      card.addEventListener('click', () => openStudio(artifact));
      bubble.appendChild(card);
      if (role === 'assistant') openStudio(artifact);
    }

    row.appendChild(bubble);
    streamEl.appendChild(row);
    streamEl.scrollTop = streamEl.scrollHeight;
    return row;
  }

  function openLightbox(url) {
    lightboxImg.src = url;
    lightbox.classList.add('open');
  }
  lightbox.addEventListener('click', () => lightbox.classList.remove('open'));

  function visibleReply(content) {
    if (!content) return '';
    let t = String(content);
    const marked = t.match(/<<<ANSWER>>>\s*([\s\S]*)$/);
    if (marked) t = marked[1];
    t = t.replace(/<<<THINK>>>[\s\S]*?(?=<<<ANSWER>>>|$)/g, '');
    if (/^\s*(THINKING|Want:|Name:|Audience:|Look:|Build:)/i.test(t)) {
      const parts = t.split(/\n\n+/);
      t = parts[parts.length - 1] || '';
      if (/^\s*(THINKING|Want:|Name:|Audience:|Look:|Build:)/i.test(t)) {
        t = '';
      }
    }
    return t.trim();
  }

  function fillAssistantText(el, content, role) {
    if (!el) return;
    el.textContent = role === 'assistant' ? visibleReply(content) : content;
  }

  const thinkSteps = [
    'Reading what you asked…',
    'Working it through…',
    'Writing the reply…',
  ];
  let thinkTimer = null;

  function appendTyping() {
    const row = document.createElement('div');
    row.className = 'msg-row assistant';
    row.id = 'typing-row';
    row.innerHTML =
      '<div class="avatar-sm">S</div>' +
      '<div class="bubble assistant"><div class="think-line" id="think-line">Thinking…</div>' +
      '<span class="typing"><span></span><span></span><span></span></span></div>';
    streamEl.appendChild(row);
    streamEl.scrollTop = streamEl.scrollHeight;
    let i = 0;
    const line = row.querySelector('#think-line');
    if (thinkTimer) clearInterval(thinkTimer);
    thinkTimer = setInterval(() => {
      if (!line) return;
      line.textContent = thinkSteps[i % thinkSteps.length];
      i += 1;
    }, 1600);
  }

  function removeTyping() {
    if (thinkTimer) {
      clearInterval(thinkTimer);
      thinkTimer = null;
    }
    const row = el('typing-row');
    if (row) row.remove();
  }

  function appendError(message) {
    setGreetingVisible(false);
    const wrap = document.createElement('div');
    wrap.className = 'error-note';
    wrap.textContent = message;
    streamEl.appendChild(wrap);
    streamEl.scrollTop = streamEl.scrollHeight;
  }

  async function sendMessage(text) {
    setGreetingVisible(false);

    const attachmentIds = pendingAttachments.filter((a) => a.id).map((a) => a.id);
    const attachmentsForDisplay = pendingAttachments.filter((a) => a.id);

    appendMessage('user', text, attachmentsForDisplay);
    clearPendingAttachments();
    appendTyping();
    sending = true;
    sendBtn.disabled = true;

    try {
      const res = await fetch('api/send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: text,
          conversation_id: currentConversationId,
          attachment_ids: attachmentIds,
          csrf: window.CSRF_TOKEN,
          mode: replyMode,
        }),
      });
      const data = await res.json();
      removeTyping();

      if (!data.ok) {
        appendError(data.error || 'Something went wrong talking to the local model.');
        return;
      }

      if (!currentConversationId) {
        currentConversationId = data.conversation_id;
        await loadConversations();
        const conv = conversations.find((c) => c.id === currentConversationId);
        convTitleEl.textContent = conv ? conv.title : 'Chat';
      } else {
        loadConversations();
      }

      appendMessage('assistant', data.reply, [], data.artifact || null);
    } catch (e) {
      removeTyping();
      appendError("Couldn't reach the server. Check that PHP is running.");
    } finally {
      sending = false;
      sendBtn.disabled = false;
    }
  }

  composerForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = composerInput.value.trim();
    const hasReadyAttachment = pendingAttachments.some((a) => a.id);
    const stillUploading = pendingAttachments.some((a) => a.uploading);
    if (stillUploading) return;
    if ((!text && !hasReadyAttachment) || sending) return;
    composerInput.value = '';
    composerInput.style.height = 'auto';
    sendMessage(text);
  });

  composerInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      composerForm.requestSubmit();
    }
  });

  composerInput.addEventListener('input', () => {
    composerInput.style.height = 'auto';
    composerInput.style.height = Math.min(composerInput.scrollHeight, 160) + 'px';
  });

  (async function init() {
    await Promise.all([loadProjects(), loadConversations()]);
    setGreetingVisible(true);
    if (window.SPARTA_HIDE_SPLASH) window.SPARTA_HIDE_SPLASH();
  })();
})();
