<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/theme.php';
start_secure_session();
$user = current_user();
if (!$user) {
    header('Location: login.php');
    exit;
}
$csrf = csrf_token();
$theme = normalize_theme($user['theme'] ?? 'paper');
$themes = available_themes();
$initials = strtoupper(substr(trim($user['name']), 0, 1) ?: '?');
$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'That form expired. Refresh and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $nextTheme = normalize_theme($_POST['theme'] ?? $theme);
        if ($name === '' || strlen($name) > 80) {
            $error = 'Enter a name between 1 and 80 characters.';
        } else {
            $stmt = db()->prepare('UPDATE users SET name = ?, theme = ? WHERE id = ?');
            $stmt->execute([$name, $nextTheme, $user['id']]);
            $user['name'] = $name;
            $user['theme'] = $nextTheme;
            $theme = $nextTheme;
            $saved = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account — Sparta</title>
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="page">
  <header class="page-bar">
    <a class="back-link" href="chat.php">← Chat</a>
    <div class="page-brand"><img class="mark" src="assets/img/logo-mark.svg" alt="">Sparta</div>
  </header>
  <main class="page-main narrow">
    <p class="eyebrow">Account</p>
    <h1>Preferences</h1>
    <p class="lead">Your profile and how the workspace looks. Changes stay on this machine.</p>

    <?php if ($saved): ?><div class="notice ok">Saved.</div><?php endif; ?>
    <?php if ($error): ?><div class="notice bad"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="account-card" style="margin-bottom:22px;">
      <div class="avatar lg"><?php echo htmlspecialchars($initials); ?></div>
      <div>
        <div class="name"><?php echo htmlspecialchars($user['name']); ?></div>
        <div class="mail"><?php echo htmlspecialchars($user['email']); ?></div>
      </div>
    </div>

    <form method="post" class="settings-form">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <div class="field">
        <label for="name">Display name</label>
        <input id="name" name="name" type="text" maxlength="80" required value="<?php echo htmlspecialchars($user['name']); ?>">
      </div>

      <fieldset class="theme-field">
        <legend>Background</legend>
        <div class="swatches">
          <?php foreach ($themes as $key => $meta): ?>
            <label class="swatch">
              <input type="radio" name="theme" value="<?php echo htmlspecialchars($key); ?>" <?php echo $theme === $key ? 'checked' : ''; ?>>
              <span class="chip" style="background:<?php echo htmlspecialchars($meta['swatch']); ?>"></span>
              <span><?php echo htmlspecialchars($meta['label']); ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <button class="btn-primary" type="submit" style="max-width:220px;">Save changes</button>
    </form>
  </main>
</body>
</html>
