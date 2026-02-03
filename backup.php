<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin']);
$u = auth_user();
$settings = get_settings();
csrf_validate();

$cfg = config();
$dbCfg = $cfg['db'];
$backupDir = $cfg['paths']['backups'] ?? (__DIR__ . '/storage/backups');

function export_sql(string $filename): string {
  $cfg = config();
  $db = $cfg['db'];
  $dir = $cfg['paths']['backups'] ?? (__DIR__ . '/storage/backups');
  if (!is_dir($dir)) @mkdir($dir, 0775, true);

  // Windows XAMPP biasanya punya mysqldump di C:\xampp\mysql\bin\mysqldump.exe
  // Jika PATH belum ada, Anda bisa set manual di Windows Environment variable, atau edit path di bawah.
  $mysqldump = 'mysqldump';
  $out = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

  $cmd = sprintf('"%s" --host=%s --port=%d --user=%s %s --routines --events --single-transaction --quick %s > "%s"',
    $mysqldump,
    escapeshellarg($db['host']),
    (int)$db['port'],
    escapeshellarg($db['user']),
    $db['pass'] !== '' ? '--password=' . escapeshellarg($db['pass']) : '',
    escapeshellarg($db['name']),
    $out
  );

  // Note: escapeshellarg akan menambahkan quotes; untuk host/user ini aman.
  // jalankan melalui shell
  @shell_exec($cmd);
  return $out;
}

if (!is_dir($backupDir)) @mkdir($backupDir, 0775, true);

$action = $_POST['action'] ?? '';
if ($action === 'export') {
  $fn = 'backup_' . date('Ymd_His') . '.sql';
  $path = export_sql($fn);
  if (file_exists($path) && filesize($path) > 10) {
    db_exec("INSERT INTO audit_logs(created_at,user_id,action,meta) VALUES(?,?,?,?)",
      [now_dt(), $u['id'], 'backup_export', json_encode(['file'=>$fn], JSON_UNESCAPED_UNICODE)]
    );
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    readfile($path);
    exit;
  } else {
    flash_set('err','Gagal export. Pastikan mysqldump tersedia (PATH) atau edit path mysqldump di backup.php.');
    redirect('/backup.php');
  }
}

$title = "Backup DB";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Backup Database</div>
  <div class="muted">Export SQL via tombol. Untuk upload Google Drive: butuh credential service account (lihat README).</div>
</div>

<div class="card">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="export">
    <button class="btn" type="submit">Export (Download) SQL</button>
  </form>

  <div class="muted" style="margin-top:12px">
    Upload Google Drive belum otomatis diaktifkan karena butuh credential JSON (service account) dan folder_id.
    Kalau Dok mau, saya bisa tambahkan modul upload Drive lengkap: tinggal taruh file JSON di <code>storage/credentials</code>
    dan isi <code>folder_id</code> di app/config.php.
  </div>
</div>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
