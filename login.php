<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

$installed = file_exists(__DIR__ . '/install/locked.txt') && file_exists(__DIR__ . '/app/config.php');
if (!$installed) {
  header('Location: ' . url('/install/'));
  exit;
}

session_boot();
csrf_validate();

$error = null;
if (is_post()) {
  $u = trim($_POST['username'] ?? '');
  $p = (string)($_POST['password'] ?? '');
  if (auth_login($u, $p)) {
    flash_set('ok', 'Login berhasil.');
    redirect('/index.php');
  } else {
    $error = "Username atau password salah.";
    log_app('info', 'Login failed', ['username'=>$u,'ip'=>$_SERVER['REMOTE_ADDR'] ?? '']);
  }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - AMS</title>
  <link rel="stylesheet" href="<?= e(url('/public/assets/css/theme.css')) ?>">
</head>
<body>
  <button class="btn back-button back-floating" type="button" onclick="goBack('<?= e(url('/index.php')) ?>')" aria-label="Kembali">←</button>
  <div class="container" style="max-width:520px">
    <div class="card">
      <div class="h1">Login</div>
      <div class="muted">Silakan masuk.</div>

      <?php if (!empty($_GET['installed'])): ?>
        <div class="alert ok">Instalasi sukses. Silakan login.</div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert err"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="label">Username</div>
        <input class="input" name="username" required>
        <div class="label" style="margin-top:10px">Password</div>
        <input class="input" type="password" name="password" required>
        <div style="display:flex;justify-content:flex-end;margin-top:12px">
          <button class="btn" type="submit">Masuk</button>
        </div>
      </form>

      <div class="muted" style="margin-top:10px">Jika ada error, jangan panik: log dulu, baru panik terukur.</div>
    </div>
  </div>
  <script src="<?= e(url('/public/assets/js/app.js')) ?>"></script>
</body>
</html>
