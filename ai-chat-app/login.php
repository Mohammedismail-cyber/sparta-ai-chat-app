<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

if (current_user()) {
    header('Location: chat.php');
    exit;
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sparta — Sign in</title>
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16.png">
<link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0a0a0b">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

<div class="splash" id="splash">
  <img class="splash-mark" src="assets/img/logo-mark.svg" alt="Sparta">
  <div class="splash-word">Sparta</div>
  <div class="splash-bar"><span></span></div>
</div>

<div class="auth-split">
  <section class="auth-showcase" aria-hidden="true">
    <div class="blob-field showcase-blobs"><span></span><span></span><span></span></div>
    <div class="showcase-brand">
      <img class="mark" src="assets/img/logo-mark.svg" alt="">
      <span>Sparta</span>
    </div>
    <div class="showcase-copy">
      <h2>Your models.<br>Your machine.</h2>
      <p>Chat, projects, and files — answered by the models already running locally.</p>
    </div>

    <div class="shot-stage" id="shot-stage">
      <article class="shot is-active" data-shot="0">
        <div class="shot-chrome">
          <span class="dot"></span><span class="dot"></span><span class="dot"></span>
          <span class="shot-url">sparta · local</span>
        </div>
        <div class="shot-body">
          <aside class="shot-side">
            <div class="shot-logo"></div>
            <div class="shot-btn"></div>
            <div class="shot-chip"></div>
            <div class="shot-chip thin"></div>
            <div class="shot-line"></div>
            <div class="shot-line short"></div>
            <div class="shot-line"></div>
          </aside>
          <div class="shot-main">
            <div class="shot-greet">
              <div class="shot-title"></div>
              <div class="shot-sub"></div>
            </div>
            <div class="shot-composer"></div>
          </div>
        </div>
      </article>

      <article class="shot" data-shot="1">
        <div class="shot-chrome">
          <span class="dot"></span><span class="dot"></span><span class="dot"></span>
          <span class="shot-url">sparta · chat</span>
        </div>
        <div class="shot-body">
          <aside class="shot-side">
            <div class="shot-logo"></div>
            <div class="shot-btn"></div>
            <div class="shot-line on"></div>
            <div class="shot-line"></div>
            <div class="shot-line short"></div>
          </aside>
          <div class="shot-main chatty">
            <div class="shot-msg user"></div>
            <div class="shot-msg ai"></div>
            <div class="shot-msg user short"></div>
            <div class="shot-msg ai mid"></div>
            <div class="shot-composer"></div>
          </div>
        </div>
      </article>

      <article class="shot" data-shot="2">
        <div class="shot-chrome">
          <span class="dot"></span><span class="dot"></span><span class="dot"></span>
          <span class="shot-url">sparta · models</span>
        </div>
        <div class="shot-body">
          <aside class="shot-side">
            <div class="shot-logo"></div>
            <div class="shot-btn"></div>
            <div class="shot-pill"></div>
            <div class="shot-pill dim"></div>
            <div class="shot-line"></div>
            <div class="shot-line"></div>
          </aside>
          <div class="shot-main">
            <div class="shot-model-card">
              <div class="shot-model-row on"></div>
              <div class="shot-model-row"></div>
              <div class="shot-model-row"></div>
              <div class="shot-model-row"></div>
            </div>
            <div class="shot-composer"></div>
          </div>
        </div>
      </article>
    </div>

    <div class="shot-meta">
      <div class="shot-dots" id="shot-dots">
        <button type="button" class="is-on" aria-label="Slide 1"></button>
        <button type="button" aria-label="Slide 2"></button>
        <button type="button" aria-label="Slide 3"></button>
      </div>
      <p class="shot-caption" id="shot-caption">A quiet canvas for local chat</p>
    </div>
  </section>

  <section class="auth-pane">
    <div class="blob-field pane-blobs"><span></span><span></span><span></span></div>
    <div class="auth-pane-inner">
      <div class="auth-mobile-brand">
        <img class="mark" src="assets/img/logo-mark.svg" alt="">Sparta
      </div>

      <div class="auth-card glass">
        <div class="tabs" role="tablist">
          <button type="button" class="tab-btn active" data-tab="login" role="tab">Log in</button>
          <button type="button" class="tab-btn" data-tab="signup" role="tab">Sign up</button>
        </div>

        <div class="auth-panels">
          <div id="panel-login" class="auth-panel is-on">
            <h1>Welcome back</h1>
            <p class="sub">Sign in to keep talking with your local models.</p>

            <form id="login-form" novalidate>
              <div class="field">
                <label for="login-email">Email</label>
                <input id="login-email" name="email" type="email" autocomplete="email" required>
              </div>
              <div class="field">
                <label for="login-password">Password</label>
                <input id="login-password" name="password" type="password" autocomplete="current-password" required>
              </div>
              <button class="btn-primary" type="submit">Log in</button>
              <div class="form-msg" id="login-msg"></div>
            </form>
          </div>

          <div id="panel-signup" class="auth-panel">
            <h1>Create your account</h1>
            <p class="sub">Everything stays on this machine.</p>

            <form id="signup-form" novalidate>
              <div class="field">
                <label for="signup-name">Name</label>
                <input id="signup-name" name="name" type="text" autocomplete="name" required>
              </div>
              <div class="field">
                <label for="signup-email">Email</label>
                <input id="signup-email" name="email" type="email" autocomplete="email" required>
              </div>
              <div class="field">
                <label for="signup-password">Password</label>
                <input id="signup-password" name="password" type="password" autocomplete="new-password" required minlength="8">
              </div>
              <button class="btn-primary" type="submit">Create account</button>
              <div class="form-msg" id="signup-msg"></div>
            </form>
          </div>
        </div>

        <div class="auth-foot">Runs on your own local models — nothing leaves this device.</div>
      </div>
    </div>
  </section>
</div>

<script>window.CSRF_TOKEN = <?php echo json_encode($csrf); ?>;</script>
<script src="assets/js/splash.js"></script>
<script src="assets/js/auth.js"></script>
</body>
</html>
