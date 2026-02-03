<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin','dokter','perawat']);
$u = auth_user();
csrf_validate();

$id = (int)($_POST['id'] ?? 0);
$visitId = (int)($_POST['visit_id'] ?? 0);

if ($id <= 0 || $visitId <= 0) {
  flash_set('err','Parameter tidak valid.');
  redirect('/visits.php');
}

try {
  $st = db()->prepare("SELECT file_path FROM usg_images WHERE id=? AND visit_id=?");
  $st->execute([$id, $visitId]);
  $row = $st->fetch();
  if (!$row) {
    flash_set('err','Data foto tidak ditemukan.');
    redirect('/visit_edit.php?id=' . $visitId);
  }

  db_exec("DELETE FROM usg_images WHERE id=? AND visit_id=?", [$id, $visitId]);

  $path = (string)($row['file_path'] ?? '');
  if ($path) {
    $abs = __DIR__ . '/' . ltrim($path, '/');
    if (is_file($abs)) @unlink($abs);
  }

  flash_set('ok','Foto USG dihapus.');
} catch (Throwable $e) {
  flash_set('err','Gagal menghapus: ' . $e->getMessage());
}

redirect('/visit_edit.php?id=' . $visitId);
