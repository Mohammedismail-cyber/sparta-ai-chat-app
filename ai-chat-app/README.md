# Sparta — a local AI chat app (PHP)

A self-contained chat interface with accounts, sessions, projects, file
and image attachments, and screenshot capture, that talks to a **model
running on your own machine**. No external API calls, no accounts on
someone else's server — just PHP, SQLite, and whatever model you have
running locally.

## What's inside

- **Auth** — sign up / log in / log out, bcrypt password hashing, CSRF
  protection on every state-changing request, brute-force lockout after
  5 failed logins, last-login tracking + a login history table.
- **Chat** — multiple conversations per user, full history saved to
  SQLite.
- **Projects** — group conversations into named projects from the
  sidebar; move any chat between projects from the ⋯ menu in the top bar.
- **Attachments** — attach images, PDFs, and text files to a message,
  or capture a screenshot directly from the browser. Images are passed
  to your local model as vision input (if it supports it); other files
  are noted by name so the model knows they were shared.
- **Models** — auto-discovers every model Ollama has pulled and **picks
  one per question** (fast chat, code, or a stronger model for long /
  analytical asks). The UI never shows which model answered. Everyday
  replies use a small, kept-warm model so the first answer is quicker.
- **Design** — shadcn-quiet surfaces, frosted liquid-glass panels, and
  spring motion over a slow animated backdrop. Sign-in is a split layout:
  rotating framed app shots on the left, the form on the right. Space
  Grotesk for display type, Inter for body text.
- **Backend** — plain PHP 8+, PDO/SQLite (zero server setup), no
  frameworks or Composer dependencies required.

## Requirements

- PHP 8.1+ with `pdo_sqlite`, `curl`, `mbstring`, and `fileinfo`
  extensions enabled (all bundled with most PHP installs; on
  Ubuntu/Debian: `sudo apt install php-cli php-sqlite3 php-curl php-mbstring`).
- A local model server. This ships wired up for **[Ollama](https://ollama.com)**,
  but any server that accepts a similar JSON chat payload will work —
  see "Using a different local runtime" below.
- A browser with screen-capture support (Chrome, Edge, Firefox) if you
  want to use the screenshot button — it needs a secure context
  (`localhost` counts; a real deployment needs HTTPS).

## Getting started

1. **Install and start Ollama**, then pull at least one model. The app
   discovers whatever you have locally — you don't have to name it in
   config. For image attachments to actually be *understood*, pull a
   vision-capable model such as `llama3.2-vision`:
   ```bash
   ollama serve
   ollama pull hermes3:8b
   ```

2. **Optional fallback** in `includes/config.php` if discovery can't
   reach Ollama:
   ```php
   define('LLM_MODEL', 'hermes3:8b');
   ```
   Vision is detected from the model name (llava, vision, etc.). Other
   attachments still upload and display even when the model is text-only.

3. **Run the app** with PHP's built-in server. From this folder
   (`ai-chat-app`):
   ```bash
   php -S localhost:8000
   ```
   Or from the repo root one level up:
   ```bash
   php -S localhost:8000
   ```
   (a root `index.php` sends `/` into `ai-chat-app/`). Then open
   `http://localhost:8000` in your browser.

4. **Sign up** for an account. The database (`data/app.sqlite`) and
   every table — including projects and attachments — are created
   automatically on first run.

That's it — everyone who signs up talks to the same local Ollama
runtime, picks their own model, and gets their own login, projects,
conversations, and history. Switch models any time from the top bar.

## Using a different local runtime

Everything about the model connection lives in two files:

- `includes/config.php` — `LLM_BASE_URL`, `LLM_ENDPOINT`, `LLM_MODEL`, extras
- `includes/llm.php` — discovery, ranking, and request/response handling
- `api/models.php` — list installed models and persist the user's pick

By default it POSTs to Ollama's `/api/chat`, including a base64 `images`
array on messages that have image attachments. If you're running LM
Studio, llama.cpp's server, or anything with an OpenAI-style
`/v1/chat/completions` endpoint, point `LLM_ENDPOINT` at it —
`ask_local_model()` already understands both response shapes (image
input on non-Ollama servers may need a small tweak to match their
format).

## Project structure

```
├── index.php                Redirects to /chat.php or /login.php
├── login.php                 Sign in / sign up screen, with splash
├── chat.php                   Main chat interface (auth required)
├── manifest.json              PWA manifest (installable, app icons)
├── includes/
│   ├── config.php             App, local model, and upload settings
│   ├── db.php                  SQLite connection, schema + migrations
│   ├── auth.php                 Sessions, CSRF, rate limiting
│   ├── llm.php                   Local model connector (+ vision)
│   └── uploads.php                Attachment validation & storage
├── api/
│   ├── register.php, login.php, logout.php
│   ├── conversations.php      List / create / delete / move conversations
│   ├── projects.php            List / create / delete projects
│   ├── get_history.php          Fetch messages (+ attachments) for a chat
│   ├── send_message.php          Send a message + attachments, get a reply
│   ├── models.php                  List / select local models
│   ├── status.php                  Model server health
│   ├── upload.php                  Handle a file/image/screenshot upload
│   └── file.php                     Serve an attachment (auth-checked)
├── assets/
│   ├── css/style.css          The whole design system
│   ├── js/{auth,chat,splash}.js
│   └── img/                    Logo, favicons, PWA icons
├── data/app.sqlite            Created automatically on first run
└── uploads/{user_id}/         Created automatically; never served directly
```

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt); never stored or
  logged in plain text.
- Every POST/DELETE request requires a per-session CSRF token.
- Sessions are `httponly`, `SameSite=Lax`, and marked `secure`
  automatically when served over HTTPS.
- Repeated failed logins for the same email are locked out for 15
  minutes after 5 attempts.
- Uploaded files are validated by their real bytes (`finfo`), not by
  filename or client-supplied MIME type, against a fixed whitelist
  (images, PDF, plain text/markdown/CSV/JSON), capped at 20MB, and
  stored under a random server-generated name — never the name the
  browser sent.
- `/data` and `/uploads` are both blocked from direct web access via
  `.htaccess`; attachments are only reachable through `api/file.php`,
  which checks that the requesting user actually owns the file. If
  you're on Nginx instead of Apache, add equivalent `deny` rules for
  both directories.
- All queries use PDO prepared statements — no string-built SQL anywhere.
- Schema changes run through an additive, idempotent migration step in
  `includes/db.php`, so pulling a newer version of this app onto an
  existing `data/app.sqlite` won't break it.

## Notes on attachments and the local model

- Images are base64-encoded and sent as vision input alongside your
  message, for every model turn in the recent context window — not
  just the message you just sent.
- Non-image files (PDF, text, markdown, CSV, JSON) aren't parsed or
  read into the model's context; the model is just told their names
  were attached. Add your own extraction step in `send_message.php` if
  you want their contents summarized too.
- The screenshot button uses the browser's own screen-capture picker
  (`getDisplayMedia`) — nothing is captured without you explicitly
  choosing a window/tab/screen in that picker each time.
