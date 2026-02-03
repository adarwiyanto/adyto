<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin','dokter','perawat']);
$u = auth_user();
csrf_validate();

$visitId = (int)($_POST['visit_id'] ?? 0);
$caption = trim($_POST['caption'] ?? '');
if ($visitId <= 0) {
  flash_set('err','Visit tidak valid.');
  redirect('/visits.php');
}

// Pastikan visit ada
$st = db()->prepare("SELECT id FROM visits WHERE id=?");
$st->execute([$visitId]);
$visit = $st->fetch();
if (!$visit) {
  flash_set('err','Visit tidak ditemukan.');
  redirect('/visits.php');
}

// Pastikan folder upload ada
$dir = __DIR__ . '/storage/uploads/usg';
if (!is_dir($dir)) {
  @mkdir($dir, 0775, true);
}

if (empty($_FILES['files'])) {
  flash_set('err','File tidak ada.');
  redirect('/visit_edit.php?id=' . $visitId);
}

$files = $_FILES['files'];
$count = is_array($files['name']) ? count($files['name']) : 0;
if ($count <= 0) {
  flash_set('err','File tidak ada.');
  redirect('/visit_edit.php?id=' . $visitId);
}

$ok = 0;
for ($i = 0; $i < $count; $i++) {
  if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
  $tmp = $files['tmp_name'][$i] ?? '';
  if (!$tmp || !is_uploaded_file($tmp)) continue;

  $orig = (string)($files['name'][$i] ?? '');
  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) continue;

  // basic mime check
  $finfo = @finfo_open(FILEINFO_MIME_TYPE);
  $mime = $finfo ? @finfo_file($finfo, $tmp) : '';
  if ($finfo) @finfo_close($finfo);
  if ($mime && strpos($mime, 'image/') !== 0) continue;

  $fname = 'usg_' . $visitId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest = $dir . '/' . $fname;
  if (!@move_uploaded_file($tmp, $dest)) continue;

  // Set permission aman untuk file upload (umumnya 0644 di hosting Linux)
  @chmod($dest, 0644);

  $rel = '/storage/uploads/usg/' . $fname;
  try {
    db_exec("INSERT INTO usg_images(visit_id, file_path, caption, sort_order, created_at) VALUES(?,?,?,?,?)",
      [$visitId, $rel, ($caption !== '' ? $caption : null), 0, now_dt()]
    );
    $ok++;
  } catch (Throwable $e) {
    @unlink($dest);
  }
}

if ($ok > 0) {
  flash_set('ok','Foto USG ter-upload: ' . $ok);
} else {
  flash_set('err','Upload gagal. Pastikan tabel usg_images sudah dibuat dan file berupa gambar (jpg/png/webp).');
}

redirect('/visit_edit.php?id=' . $visitId);
