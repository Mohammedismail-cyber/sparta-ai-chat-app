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
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About — Sparta</title>
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="page">
  <header class="page-bar">
    <a class="back-link" href="chat.php">← Chat</a>
    <div class="page-brand"><img class="mark" src="assets/img/logo-mark.svg" alt="">Sparta</div>
  </header>
  <main class="page-main">
    <p class="eyebrow">About</p>
    <h1>A private studio on your computer.</h1>
    <p class="lead">Sparta is for people who want to think, write, and make things without sending their work somewhere else. You sign in, you keep a history, and everything you create stays on this machine.</p>

    <section class="page-grid">
      <article>
        <h2>Talk it through</h2>
        <p>Ask questions, draft copy, debug an idea, or leave a note for later. Conversations are yours — grouped into projects when you want them tidy.</p>
      </article>
      <article>
        <h2>Make something you can see</h2>
        <p>Ask for a website, a mobile app, an image, or a motion piece and Sparta builds a preview you can open right in the chat. Change the brief and make another.</p>
      </article>
      <article>
        <h2>Keep files close</h2>
        <p>Attach images, PDFs, and notes, or capture a screenshot. They sit with the message they belong to — not on someone else’s server.</p>
      </article>
      <article>
        <h2>What it’s good for</h2>
        <p>Quick design explorations. Landing pages. App sketches. Mood images. Short motion titles. Everyday writing. A quiet place to work when you don’t want an account on a public lab.</p>
      </article>
    </section>
  </main>
</body>
</html>
