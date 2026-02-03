<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin']);
$u = auth_user();
$settings = get_settings();

$dir = config()['paths']['logs'] ?? (__DIR__ . '/storage/logs');
$files = [];
if (is_dir($dir)) {
  foreach (glob(rtrim($dir,'/\\') . DIRECTORY_SEPARATOR . 'app-*.log') as $f) {
    $files[] = basename($f);
  }
  rsort($files);
}

$file = $_GET['file'] ?? ($files[0] ?? '');
$content = '';
if ($file && preg_match('/^app-\d{4}-\d{2}-\d{2}\.log$/', $file)) {
  $p = rtrim($dir,'/\\') . DIRECTORY_SEPARATOR . $file;
  if (file_exists($p)) {
    $content = file_get_contents($p);
    // limit display
    if (strlen($content) > 50000) $content = substr($content, -50000);
  }
}

$title = "Log";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Log</div>
  <div class="muted">Untuk debugging bila ada error di kemudian hari.</div>
</div>

<div class="card">
  <form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
    <div style="min-width:260px">
      <div class="label">Pilih file</div>
      <select class="input" name="file">
        <?php foreach ($files as $f): ?>
          <option value="<?= e($f) ?>" <?= $f===$file?'selected':'' ?>><?= e($f) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn secondary" type="submit">Buka</button>
  </form>

  <div class="card" style="margin-top:12px">
    <div class="label">Isi log (tail)</div>
    <pre style="white-space:pre-wrap;word-break:break-word;margin:0"><?= e($content) ?></pre>
  </div>
</div>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
