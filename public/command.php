<?php
// SECURITY: Delete this file immediately after use.

define('CMD_SECRET', 'zenvora');

// Allowed commands whitelist - prevents arbitrary execution
$allowedCommands = [
    // Cache
    'optimize'         => ['label' => 'Optimize',          'cmd' => 'optimize',               'group' => 'Cache',    'danger' => false],
    'optimize_clear'   => ['label' => 'Clear All Cache',   'cmd' => 'optimize:clear',          'group' => 'Cache',    'danger' => false],
    'cache_clear'      => ['label' => 'Cache Clear',       'cmd' => 'cache:clear',             'group' => 'Cache',    'danger' => false],
    'config_clear'     => ['label' => 'Config Clear',      'cmd' => 'config:clear',            'group' => 'Cache',    'danger' => false],
    'view_clear'       => ['label' => 'View Clear',        'cmd' => 'view:clear',              'group' => 'Cache',    'danger' => false],
    'route_clear'      => ['label' => 'Route Clear',       'cmd' => 'route:clear',             'group' => 'Cache',    'danger' => false],
    'event_clear'      => ['label' => 'Event Clear',       'cmd' => 'event:clear',             'group' => 'Cache',    'danger' => false],
    // Database
    'migrate'          => ['label' => 'Migrate',           'cmd' => 'migrate --force',         'group' => 'Database', 'danger' => false],
    'migrate_rollback' => ['label' => 'Rollback',          'cmd' => 'migrate:rollback --force','group' => 'Database', 'danger' => true],
    'migrate_status'   => ['label' => 'Migrate Status',    'cmd' => 'migrate:status',          'group' => 'Database', 'danger' => false],
    'db_seed'          => ['label' => 'DB Seed',           'cmd' => 'db:seed --force',         'group' => 'Database', 'danger' => true],
    // Queue
    'queue_restart'    => ['label' => 'Queue Restart',     'cmd' => 'queue:restart',           'group' => 'Queue',    'danger' => false],
    'queue_work'       => ['label' => 'Queue Work',        'cmd' => 'queue:work',              'group' => 'Queue',    'danger' => false],
    // Storage
    'storage_link'     => ['label' => 'Storage Link',      'cmd' => 'storage:link',            'group' => 'Storage',  'danger' => false],
    // Info
    'route_list'       => ['label' => 'Route List',        'cmd' => 'route:list',              'group' => 'Info',     'danger' => false],
    'about'            => ['label' => 'About',             'cmd' => 'about',                   'group' => 'Info',     'danger' => false],
    'env_show'         => ['label' => 'Environment',       'cmd' => 'env',                     'group' => 'Info',     'danger' => false],
];

// ── Secret validation ─────────────────────────────────────────────────────────
$providedSecret = $_GET['secret'] ?? '';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($providedSecret !== CMD_SECRET) {
    if ($isAjax) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'output' => 'Forbidden: invalid secret.']);
        exit;
    }
    // Show login form if no secret in URL
    renderLoginPage();
    exit;
}

// ── AJAX command runner ───────────────────────────────────────────────────────
if ($isAjax && isset($_GET['run'])) {
    header('Content-Type: application/json');

    $cmdKey = $_GET['cmd'] ?? '';
    if (!array_key_exists($cmdKey, $allowedCommands)) {
        echo json_encode(['success' => false, 'output' => 'Invalid or disallowed command key.']);
        exit;
    }

    define('LARAVEL_START', microtime(true));
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    $artisanCmd = $allowedCommands[$cmdKey]['cmd'];
    $status     = $kernel->call($artisanCmd);
    $output     = trim($kernel->output());
    $elapsed    = round(microtime(true) - LARAVEL_START, 3);

    echo json_encode([
        'success'   => $status === 0,
        'exit_code' => $status,
        'command'   => 'php artisan ' . $artisanCmd,
        'output'    => $output !== '' ? $output : '(no output)',
        'elapsed'   => $elapsed . 's',
    ]);
    exit;
}

// ── Render the control panel ──────────────────────────────────────────────────
$groupedCommands = [];
foreach ($allowedCommands as $key => $meta) {
    $groupedCommands[$meta['group']][$key] = $meta;
}

$groupColors = [
    'Cache'    => '#3b82f6',
    'Database' => '#8b5cf6',
    'Queue'    => '#f59e0b',
    'Storage'  => '#10b981',
    'Info'     => '#6b7280',
];

function renderLoginPage(): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>zenvora - Command Panel</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d1117; color: #c9d1d9; font-family: 'Segoe UI', system-ui, sans-serif;
         display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .login-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px;
                padding: 2.5rem 2rem; width: 100%; max-width: 380px; }
  .login-card h1 { font-size: 1.3rem; font-weight: 700; color: #e6edf3; margin-bottom: .3rem; }
  .login-card p  { font-size: .82rem; color: #8b949e; margin-bottom: 1.8rem; }
  label { display: block; font-size: .8rem; color: #8b949e; margin-bottom: .4rem; }
  input[type=password] { width: 100%; background: #0d1117; border: 1px solid #30363d;
    border-radius: 6px; padding: .6rem .8rem; color: #e6edf3; font-size: .95rem; outline: none; }
  input[type=password]:focus { border-color: #58a6ff; }
  button[type=submit] { margin-top: 1.2rem; width: 100%; background: #238636; border: none;
    border-radius: 6px; padding: .7rem; color: #fff; font-size: .95rem; font-weight: 600;
    cursor: pointer; transition: background .2s; }
  button[type=submit]:hover { background: #2ea043; }
  .warn { margin-top: 1.5rem; font-size: .75rem; color: #f85149; text-align: center; }
</style>
</head>
<body>
<div class="login-card">
  <h1>🛠 Command Panel</h1>
  <p>Enter the secret key to access the control panel.</p>
  <form method="get">
    <label for="s">Secret Key</label>
    <input type="password" id="s" name="secret" placeholder="••••••••" autofocus required>
    <button type="submit">Unlock Panel</button>
  </form>
  <p class="warn">⚠ Delete this file immediately after use.</p>
</div>
</body>
</html>
<?php }

renderPanel($groupedCommands, $groupColors, $providedSecret);

function renderPanel(array $groupedCommands, array $groupColors, string $secret): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>zenvora - Command Panel</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d1117; color: #c9d1d9; font-family: 'Segoe UI', system-ui, sans-serif;
         min-height: 100vh; padding: 2rem 1rem; }

  .header { max-width: 900px; margin: 0 auto 2rem; display: flex; align-items: center;
            justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
  .header-title { font-size: 1.25rem; font-weight: 700; color: #e6edf3; }
  .header-title span { color: #58a6ff; }
  .badge-warn { background: #3d1f00; border: 1px solid #f85149; color: #f85149;
                font-size: .72rem; font-weight: 600; padding: .25rem .6rem; border-radius: 20px; }

  .panel { max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; }

  .group-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; overflow: hidden; }
  .group-header { padding: .6rem 1rem; font-size: .75rem; font-weight: 700;
                  letter-spacing: .08em; text-transform: uppercase; }
  .group-buttons { padding: .8rem 1rem 1rem; display: flex; flex-wrap: wrap; gap: .6rem; }

  .cmd-btn { display: inline-flex; align-items: center; gap: .45rem;
             padding: .45rem .9rem; border-radius: 6px; border: 1px solid #30363d;
             background: #21262d; color: #c9d1d9; font-size: .85rem; font-weight: 500;
             cursor: pointer; transition: background .15s, border-color .15s, opacity .2s; }
  .cmd-btn:hover:not(:disabled) { background: #30363d; border-color: #8b949e; }
  .cmd-btn:disabled { opacity: .45; cursor: not-allowed; }
  .cmd-btn.danger { border-color: #6e3030; color: #f85149; }
  .cmd-btn.danger:hover:not(:disabled) { background: #3d1f1f; border-color: #f85149; }
  .cmd-btn .spinner { display: none; width: 12px; height: 12px; border: 2px solid #8b949e;
                      border-top-color: #58a6ff; border-radius: 50%; animation: spin .6s linear infinite; }
  .cmd-btn.running .spinner { display: inline-block; }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* Console */
  .console-wrap { background: #161b22; border: 1px solid #30363d; border-radius: 12px; overflow: hidden; }
  .console-titlebar { display: flex; align-items: center; justify-content: space-between;
                       padding: .6rem 1rem; border-bottom: 1px solid #30363d; }
  .console-titlebar span { font-size: .8rem; font-weight: 700; color: #8b949e;
                            text-transform: uppercase; letter-spacing: .08em; }
  .console-actions { display: flex; gap: .5rem; }
  .console-clear, .console-copy { background: none; border: 1px solid #30363d; color: #8b949e;
    font-size: .75rem; padding: .25rem .65rem; border-radius: 5px; cursor: pointer; transition: all .15s; }
  .console-clear:hover, .console-copy:hover { background: #21262d; color: #c9d1d9; }

  #console { padding: 1rem; min-height: 260px; max-height: 500px; overflow-y: auto;
             font-family: 'Cascadia Code', 'Consolas', 'Fira Code', monospace; font-size: .82rem;
             line-height: 1.6; color: #c9d1d9; }

  .log-entry { margin-bottom: .8rem; border-left: 3px solid #30363d; padding-left: .75rem; }
  .log-entry.success { border-color: #238636; }
  .log-entry.error   { border-color: #f85149; }
  .log-meta { color: #8b949e; font-size: .75rem; margin-bottom: .2rem; }
  .log-meta .cmd-label { color: #58a6ff; font-weight: 600; }
  .log-meta .elapsed { color: #3fb950; }
  .log-output { white-space: pre-wrap; word-break: break-word; color: #e6edf3; }
  .log-output.err-text { color: #f85149; }
  .placeholder { color: #484f58; font-style: italic; }
</style>
</head>
<body>

<div class="header">
  <div class="header-title">🛠 <span>zenvora</span> Command Panel</div>
  <div class="badge-warn">⚠ Delete this file after use</div>
</div>

<div class="panel">

  <?php foreach ($groupedCommands as $group => $commands):
    $color = $groupColors[$group] ?? '#8b949e'; ?>
  <div class="group-card">
    <div class="group-header" style="background:<?= htmlspecialchars($color) ?>22; color:<?= htmlspecialchars($color) ?>;">
      <?= htmlspecialchars($group) ?>
    </div>
    <div class="group-buttons">
      <?php foreach ($commands as $key => $meta):
        $danger = $meta['danger'] ? 'danger' : ''; ?>
      <button class="cmd-btn <?= $danger ?>"
              data-key="<?= htmlspecialchars($key) ?>"
              data-label="<?= htmlspecialchars($meta['label']) ?>"
              <?= $meta['danger'] ? 'data-confirm="true"' : '' ?>>
        <span class="spinner"></span>
        <?= htmlspecialchars($meta['label']) ?>
        <?= $meta['danger'] ? ' ⚠' : '' ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="console-wrap">
    <div class="console-titlebar">
      <span>Output Console</span>
      <div class="console-actions">
        <button class="console-copy" onclick="copyConsole()">Copy</button>
        <button class="console-clear" onclick="clearConsole()">Clear</button>
      </div>
    </div>
    <div id="console">
      <span class="placeholder">Run a command above to see output here…</span>
    </div>
  </div>

</div>

<script>
const SECRET = <?= json_encode($secret) ?>;

document.querySelectorAll('.cmd-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const key     = btn.dataset.key;
    const label   = btn.dataset.label;
    const confirm = btn.dataset.confirm === 'true';

    if (confirm && !window.confirm(`"${label}" is a destructive action. Proceed?`)) return;

    runCommand(key, label, btn);
  });
});

function runCommand(key, label, btn) {
  // Disable all buttons while running
  document.querySelectorAll('.cmd-btn').forEach(b => b.disabled = true);
  btn.classList.add('running');

  clearPlaceholder();

  const entry = document.createElement('div');
  entry.className = 'log-entry';
  entry.innerHTML = `
    <div class="log-meta">
      <span class="cmd-label">▶ ${escHtml(label)}</span>
      <span> - </span><span>${new Date().toLocaleTimeString()}</span>
    </div>
    <div class="log-output">Running…</div>`;
  const console_ = document.getElementById('console');
  console_.appendChild(entry);
  scrollConsole();

  const url = `?secret=${encodeURIComponent(SECRET)}&run=1&cmd=${encodeURIComponent(key)}`;

  fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  // console log all network errors and response data
    .then(r => r.json())
    .then(data => {
      console.log('Command response:', data);
      entry.classList.add(data.success ? 'success' : 'error');
      entry.querySelector('.log-meta').innerHTML = `
        <span class="cmd-label">▶ ${escHtml(label)}</span>
        <span style="color:#8b949e;"> - ${escHtml(data.command)}</span>
        <span>  </span><span class="elapsed">${escHtml(data.elapsed)}</span>
        <span style="color:#8b949e;">  exit: ${data.exit_code}</span>`;
      const out = entry.querySelector('.log-output');
      out.textContent = data.output;
      if (!data.success) out.classList.add('err-text');
    })
    .catch(err => {
      console.error('Network error:', err);
      entry.classList.add('error');
      entry.querySelector('.log-output').textContent = 'Request failed: ' + err.message;
    })
    .finally(() => {
      document.querySelectorAll('.cmd-btn').forEach(b => b.disabled = false);
      btn.classList.remove('running');
      scrollConsole();
    });
}

function clearPlaceholder() {
  const ph = document.querySelector('#console .placeholder');
  if (ph) ph.remove();
}

function clearConsole() {
  document.getElementById('console').innerHTML = '<span class="placeholder">Console cleared.</span>';
}

function copyConsole() {
  const text = document.getElementById('console').innerText;
  navigator.clipboard.writeText(text).then(() => {
    const btn = document.querySelector('.console-copy');
    btn.textContent = 'Copied!';
    setTimeout(() => btn.textContent = 'Copy', 1500);
  });
}

function scrollConsole() {
  const c = document.getElementById('console');
  c.scrollTop = c.scrollHeight;
}

function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
</script>
</body>
</html>
<?php }
