<?php
require_once __DIR__ . '/../app/helpers.php';

$lockFile = __DIR__ . '/locked.txt';
if (file_exists($lockFile)) {
  http_response_code(404);
  echo "Install sudah dikunci.";
  exit;
}

$step = (int)($_GET['step'] ?? 1);
$errors = [];
$ok = null;

function can_write($path): bool {
  return is_writable($path) || (is_dir($path) && is_writable($path));
}

if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $basePath = trim($_POST['base_path'] ?? '');
  $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
  $dbPort = (int)($_POST['db_port'] ?? 3306);
  $dbUser = trim($_POST['db_user'] ?? 'root');
  $dbPass = (string)($_POST['db_pass'] ?? '');
  $dbName = trim($_POST['db_name'] ?? '');

  $adminUser = trim($_POST['admin_user'] ?? '');
  $adminPass1 = (string)($_POST['admin_pass1'] ?? '');
  $adminPass2 = (string)($_POST['admin_pass2'] ?? '');

  if ($basePath === '' || $basePath[0] !== '/') $errors[] = "Base path harus diawali '/', contoh: /AMS atau /praktek_mandiri";
  if ($dbName === '') $errors[] = "Nama database wajib diisi.";
  if ($adminUser === '') $errors[] = "Username admin wajib diisi.";
  if (strlen($adminPass1) < 8) $errors[] = "Password minimal 8 karakter.";
  if ($adminPass1 !== $adminPass2) $errors[] = "Password tidak sama.";

  if (!can_write(__DIR__ . '/../app')) $errors[] = "Folder app/ tidak bisa ditulis. Pastikan permission OK.";
  if (!can_write(__DIR__)) $errors[] = "Folder install/ tidak bisa ditulis (untuk lock).";
  if (!can_write(__DIR__ . '/../storage')) $errors[] = "Folder storage/ tidak bisa ditulis.";

  if (!$errors) {
    // Create config.php
    $csrfKey = bin2hex(random_bytes(16));
    $config = [
      'installed' => true,
      'base_path' => rtrim($basePath, '/'),
      'db' => [
        'host' => $dbHost,
        'port' => $dbPort,
        'name' => $dbName,
        'user' => $dbUser,
        'pass' => $dbPass,
        'charset' => 'utf8mb4',
      ],
      'security' => [
        'session_name' => 'AMSSESSID',
        'csrf_key' => $csrfKey,
      ],
      'uploads' => [
        'logo_dir' => __DIR__ . '/../storage/uploads/logo',
        'signature_dir' => __DIR__ . '/../storage/uploads/signature',
      ],
      'paths' => [
        'logs' => __DIR__ . '/../storage/logs',
        'backups' => __DIR__ . '/../storage/backups',
      ],
      'gdrive' => [
        'enabled' => false,
        'service_account_json' => __DIR__ . '/../storage/credentials/gdrive_service_account.json',
        'folder_id' => '',
      ],
    ];

    $configPhp = "<?php\nreturn " . var_export($config, true) . ";\n";
    file_put_contents(__DIR__ . '/../app/config.php', $configPhp);

    // Connect to MySQL without DB first
    try {
      $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort);
      $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]);

      // Create DB
      $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
      $pdo->exec("USE `{$dbName}`");

      // Tables
      $schema = file_get_contents(__DIR__ . '/schema.sql');
      $pdo->exec($schema);

      // Seed settings
      $stmt = $pdo->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?)");
      $defaults = [
        ['brand_title','Praktek dr. Agus'],
        ['brand_badge','Adena Medical System'],
        ['footer_text','© 2026 Adena Medical System ver 1.1'],
        ['clinic_name','Praktek dr. Agus'],
        ['clinic_address',''],
        ['clinic_sip',''],
        ['logo_path',''],
        ['signature_path',''],
        ['custom_css',''],
      ];
      foreach ($defaults as $d) $stmt->execute($d);

      // Create admin
      $hash = password_hash($adminPass1, PASSWORD_DEFAULT);
      $pdo->prepare("INSERT INTO users(username,password_hash,role,full_name,is_active,created_at) VALUES(?,?,?,?,1,NOW())")
          ->execute([$adminUser,$hash,'admin',$adminUser]);

      // lock installer
      file_put_contents($lockFile, "locked " . date('c'));
      // redirect to login
      header('Location: ' . rtrim($basePath,'/') . '/login.php?installed=1');
      exit;

    } catch (Throwable $e) {
      $errors[] = "Gagal instalasi: " . $e->getMessage();
      // cleanup config on failure
      @unlink(__DIR__ . '/../app/config.php');
    }
  }
}

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install AMS</title>
  <link rel="stylesheet" href="../public/assets/css/theme.css">
</head>
<body>
    <button class="btn back-button back-floating" type="button" onclick="goBack('../index.php')" aria-label="Kembali">←</button>
    <div class="card">
      <div class="h1">Install Adena Medical System (AMS)</div>
      <div class="muted">Wizard ini berjalan tanpa database, dan akan membuat database + user admin pertama.</div>

      <?php if ($errors): ?>
        <div class="alert err">
          <ul>
            <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <div class="grid">
          <div class="col-12">
            <div class="label">Nama Folder/URL Path (base path)</div>
            <input class="input" name="base_path" placeholder="/AMS" value="<?= e($_POST['base_path'] ?? '/AMS') ?>">
            <div class="muted" style="margin-top:6px">Jika folder Anda bernama <b>praktek_mandiri</b>, isi: <b>/praktek_mandiri</b></div>
          </div>

          <div class="col-6">
            <div class="label">MySQL Host</div>
            <input class="input" name="db_host" value="<?= e($_POST['db_host'] ?? '127.0.0.1') ?>">
          </div>
          <div class="col-6">
            <div class="label">MySQL Port</div>
            <input class="input" name="db_port" value="<?= e($_POST['db_port'] ?? '3306') ?>">
          </div>
          <div class="col-6">
            <div class="label">MySQL User</div>
            <input class="input" name="db_user" value="<?= e($_POST['db_user'] ?? 'root') ?>">
          </div>
          <div class="col-6">
            <div class="label">MySQL Password</div>
            <input class="input" type="password" name="db_pass" value="<?= e($_POST['db_pass'] ?? '') ?>">
          </div>
          <div class="col-12">
            <div class="label">Nama Database (akan dibuat otomatis)</div>
            <input class="input" name="db_name" placeholder="praktek_db" value="<?= e($_POST['db_name'] ?? '') ?>">
          </div>

          <div class="col-12">
            <div class="h1" style="font-size:16px;margin-top:6px">Buat Admin Pertama</div>
          </div>
          <div class="col-6">
            <div class="label">Username Admin</div>
            <input class="input" name="admin_user" value="<?= e($_POST['admin_user'] ?? '') ?>">
          </div>
          <div class="col-6"></div>
          <div class="col-6">
            <div class="label">Password</div>
            <input class="input" type="password" name="admin_pass1">
            <div class="muted" style="margin-top:6px">Minimal 8 karakter.</div>
          </div>
          <div class="col-6">
            <div class="label">Ulangi Password</div>
            <input class="input" type="password" name="admin_pass2">
          </div>

          <div class="col-12" style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn" type="submit">Install</button>
          </div>
        </div>
      </form>

      <div class="muted" style="margin-top:10px">
        Setelah sukses, halaman ini otomatis terkunci dan tidak dapat diakses lagi.
      </div>
    </div>
  </div>
  <script src="../public/assets/js/app.js"></script>
</body>
</html>
