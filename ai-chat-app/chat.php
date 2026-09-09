<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/theme.php';
start_secure_session();

$user = current_user();
if (!$user) {
    header('Location: login.php');
    exit;
}
$theme = normalize_theme($user['theme'] ?? 'paper');

$csrf = csrf_token();

$initials = strtoupper(substr(trim($user['name']), 0, 1) ?: '?');
$firstName = trim(explode(' ', $user['name'])[0]);

$prevLoginAt = $_SESSION['prev_login_at'] ?? null;
if ($prevLoginAt) {
    $dt = new DateTime($prevLoginAt);
    $lastLoginText = 'Last login ' . $dt->format('M j, g:i A');
} else {
    $lastLoginText = 'First time here';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sparta</title>
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16.png">
<link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#ffffff">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app">

<script>window.SPARTA_MANUAL_SPLASH = true;</script>

<div class="splash" id="splash">
  <img class="splash-mark" src="assets/img/logo-mark.svg" alt="Sparta">
  <div class="splash-word">Sparta</div>
  <div class="splash-bar"><span></span></div>
</div>

<div class="sidebar-scrim" id="scrim"></div>

<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img class="mark" src="assets/img/logo-mark.svg" alt="">
      <span>Sparta</span>
    </div>

    <nav class="nav-groups">
      <section class="nav-group open" data-group="new">
        <button class="nav-group-h" type="button" aria-expanded="true">
          <span>New</span>
          <svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div class="nav-group-b">
          <button class="nav-item" id="new-chat-btn" type="button">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            New chat
          </button>
          <button class="nav-item" id="add-project-btn" type="button">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 20h16M4 4h7l2 3h7v13H4z"/></svg>
            New project
          </button>
          <div class="project-rail" id="project-list"></div>
        </div>
      </section>

      <section class="nav-group open grow" data-group="chats">
        <button class="nav-group-h" type="button" aria-expanded="true">
          <span id="history-label">Chats</span>
          <button class="clear-filter-btn" id="clear-filter-btn" style="display:none;" type="button">All</button>
          <svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div class="nav-group-b">
          <div class="history-list" id="history-list">
            <div class="history-empty">Your conversations will show up here.</div>
          </div>
        </div>
      </section>

      <section class="nav-group open" data-group="account">
        <button class="nav-group-h" type="button" aria-expanded="true">
          <span>Account</span>
          <svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div class="nav-group-b">
          <a class="nav-item" href="about.php">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/></svg>
            About
          </a>
          <a class="nav-item" href="account.php">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            Account
          </a>
          <button class="nav-item danger" id="logout-btn" type="button">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
            <span>Log out</span>
          </button>
        </div>
      </section>
    </nav>
  </aside>

  <main class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="menu-btn" id="menu-btn" type="button" aria-label="Open sidebar">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <span class="conv-title" id="conv-title">New chat</span>
      </div>
      <div class="topbar-actions">
        <button class="kebab-btn" id="kebab-btn" title="Move chat" type="button">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="5" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="12" cy="19" r="1.2"/></svg>
        </button>
        <div class="menu-popover" id="move-menu">
          <div class="menu-label">Move to</div>
          <div id="move-menu-list"></div>
        </div>
      </div>
    </header>

    <div class="stream-wrap">
      <div class="greeting" id="greeting">
        <h2>How are you doing, <?php echo htmlspecialchars($firstName); ?>?</h2>
        <p>Ask anything — or ask for a website, app, image, or motion piece.</p>
      </div>
      <div class="stream hidden" id="stream"></div>
    </div>

    <div class="composer-wrap">
      <div class="pending-attachments" id="pending-attachments"></div>
      <form class="composer" id="composer-form">
        <button class="composer-icon-btn" id="attach-btn" title="Attach file or image" type="button" aria-label="Attach">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21.4 11.6l-8.5 8.5a5.5 5.5 0 0 1-7.8-7.8l8.5-8.5a3.5 3.5 0 0 1 5 5l-8.5 8.5a1.5 1.5 0 0 1-2.1-2.1l7.4-7.5"/></svg>
        </button>
        <button class="composer-icon-btn" id="screenshot-btn" title="Capture a screenshot" type="button" aria-label="Screenshot">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        </button>
        <textarea id="composer-input" placeholder="Message…" rows="1" required></textarea>
        <button class="mode-toggle" id="reply-mode" type="button" data-mode="think" title="Switch reply mode" aria-label="Reply mode">Thinking</button>
        <button class="send-btn" id="send-btn" type="submit" aria-label="Send">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
        </button>
      </form>
      <input type="file" id="file-input" style="display:none;" accept="image/png,image/jpeg,image/webp,image/gif,application/pdf,text/plain,text/markdown,text/csv,application/json" multiple>
      <div class="composer-hint">Enter to send · Fast or Thinking sits next to send</div>
    </div>
  </main>

  <aside class="studio" id="studio" hidden>
    <div class="studio-head">
      <div>
        <div class="studio-kind" id="studio-kind">Preview</div>
        <div class="studio-title" id="studio-title"></div>
      </div>
      <div class="studio-actions">
        <a class="studio-btn" id="studio-open" href="#" target="_blank" rel="noopener">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 5h5v5M19 5l-7 7"/><path d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5"/></svg>
          Open
        </a>
        <button class="studio-btn icon" id="studio-close" type="button" aria-label="Close preview">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
    </div>
    <div class="studio-frame" id="studio-frame">
      <iframe id="studio-iframe" title="Preview" sandbox="allow-scripts"></iframe>
    </div>
  </aside>
</div>

<div class="modal-scrim" id="project-modal">
  <div class="modal-card">
    <h3>New project</h3>
    <input type="text" id="project-name-input" placeholder="Name" maxlength="60">
    <div class="modal-actions">
      <button class="modal-cancel" id="project-modal-cancel" type="button">Cancel</button>
      <button class="modal-confirm" id="project-modal-confirm" type="button">Create</button>
    </div>
  </div>
</div>

<div class="lightbox-scrim" id="lightbox">
  <img id="lightbox-img" src="" alt="">
</div>

<script>
  window.CSRF_TOKEN = <?php echo json_encode($csrf); ?>;
  window.USER_INITIALS = <?php echo json_encode($initials); ?>;
</script>
<script src="assets/js/splash.js"></script>
<script src="assets/js/chat.js"></script>
</body>
</html>
