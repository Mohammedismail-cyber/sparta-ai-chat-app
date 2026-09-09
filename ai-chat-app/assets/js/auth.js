(function () {
  'use strict';

  const tabs = document.querySelectorAll('.tab-btn');
  const panels = {
    login: document.getElementById('panel-login'),
    signup: document.getElementById('panel-signup'),
  };

  tabs.forEach((btn) => {
    btn.addEventListener('click', () => {
      tabs.forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      const target = btn.dataset.tab;
      Object.keys(panels).forEach((key) => {
        panels[key].classList.toggle('is-on', key === target);
      });
    });
  });

  function setBusy(form, busy) {
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = busy;
    btn.dataset.label = btn.dataset.label || btn.textContent;
    btn.textContent = busy ? 'Please wait…' : btn.dataset.label;
  }

  async function submitForm(form, endpoint, msgEl) {
    msgEl.textContent = '';
    setBusy(form, true);

    const body = Object.fromEntries(new FormData(form).entries());
    body.csrf = window.CSRF_TOKEN;

    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json();

      if (!data.ok) {
        msgEl.textContent = data.error || 'Something went wrong.';
        setBusy(form, false);
        return;
      }

      window.location.href = data.redirect || 'chat.php';
    } catch (err) {
      msgEl.textContent = "Couldn't reach the server. Is PHP running?";
      setBusy(form, false);
    }
  }

  document.getElementById('login-form').addEventListener('submit', (e) => {
    e.preventDefault();
    submitForm(e.target, 'api/login.php', document.getElementById('login-msg'));
  });

  document.getElementById('signup-form').addEventListener('submit', (e) => {
    e.preventDefault();
    submitForm(e.target, 'api/register.php', document.getElementById('signup-msg'));
  });

  const captions = [
    'A quiet canvas for local chat',
    'Conversations that never leave this machine',
    'Every model you have pulled, ready to switch',
  ];
  const shots = Array.from(document.querySelectorAll('.shot'));
  const dots = Array.from(document.querySelectorAll('#shot-dots button'));
  const caption = document.getElementById('shot-caption');
  let index = 0;
  let timer = null;

  function go(to) {
    if (!shots.length) return;
    index = (to + shots.length) % shots.length;
    shots.forEach((s, i) => s.classList.toggle('is-active', i === index));
    dots.forEach((d, i) => d.classList.toggle('is-on', i === index));
    if (caption) {
      caption.style.opacity = '0';
      caption.style.transform = 'translateY(6px)';
      setTimeout(() => {
        caption.textContent = captions[index] || '';
        caption.style.opacity = '1';
        caption.style.transform = 'translateY(0)';
      }, 180);
    }
  }

  function start() {
    stop();
    timer = setInterval(() => go(index + 1), 4200);
  }
  function stop() {
    if (timer) clearInterval(timer);
    timer = null;
  }

  dots.forEach((d, i) => {
    d.addEventListener('click', () => {
      go(i);
      start();
    });
  });

  const stage = document.getElementById('shot-stage');
  stage?.addEventListener('mouseenter', stop);
  stage?.addEventListener('mouseleave', start);

  if (shots.length) start();
})();
