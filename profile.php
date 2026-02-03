<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$u = auth_user();
$settings = get_settings();
csrf_validate();

function save_signature_upload(string $field): ?string {
  if (empty($_FILES[$field]['name'])) return null;
  if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;

  $name = $_FILES[$field]['name'];
  $tmp  = $_FILES[$field]['tmp_name'];
  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, ['png','jpg','jpeg','webp'], true)) return null;

  $dirAbs = __DIR__ . '/storage/uploads/signature/users';
  if (!is_dir($dirAbs)) @mkdir($dirAbs, 0775, true);

  $fn = 'sig_user_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
  $dest = $dirAbs . '/' . $fn;
  if (!move_uploaded_file($tmp, $dest)) return null;

  return '/storage/uploads/signature/users/' . $fn;
}

$me = db()->prepare("SELECT * FROM users WHERE id=?");
$me->execute([$u['id']]);
$me = $me->fetch();

if (is_post()) {
  $full = trim($_POST['full_name'] ?? '');
  $sip = trim($_POST['sip'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');

  $sig = save_signature_upload('signature_user');

  if ($sig) {
    db_exec("UPDATE users SET full_name=?, sip=?, phone=?, email=?, signature_path=?, updated_at=? WHERE id=?",
      [$full, $sip, $phone, $email, $sig, now_dt(), $u['id']]
    );
  } else {
    db_exec("UPDATE users SET full_name=?, sip=?, phone=?, email=?, updated_at=? WHERE id=?",
      [$full, $sip, $phone, $email, now_dt(), $u['id']]
    );
  }

  $_SESSION['user']['full_name'] = $full ?: $_SESSION['user']['username'];
  flash_set('ok','Profile tersimpan.');
  redirect('/profile.php');
}

$title = "Profile";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Profile</div>
  <div class="muted">Berlaku untuk semua role. Tanda tangan per-user berguna untuk dokter.</div>
</div>

<div class="card">
  <form method="post" class="grid" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="col-6">
      <div class="label">Nama Lengkap</div>
      <input class="input" name="full_name" value="<?= e($me['full_name'] ?? '') ?>">
    </div>
    <div class="col-6">
      <div class="label">SIP (opsional)</div>
      <input class="input" name="sip" value="<?= e($me['sip'] ?? '') ?>">
    </div>
    <div class="col-6">
      <div class="label">No HP</div>
      <input class="input" name="phone" value="<?= e($me['phone'] ?? '') ?>">
    </div>
    <div class="col-6">
      <div class="label">Email</div>
      <input class="input" name="email" value="<?= e($me['email'] ?? '') ?>">
    </div>

    <div class="col-12">
      <div class="label">Tanda tangan (untuk dokter) - png/jpg/webp</div>
      <input class="input" type="file" name="signature_user" accept=".png,.jpg,.jpeg,.webp">
      <?php if (!empty($me['signature_path'])): ?>
        <div class="muted" style="margin-top:6px">Saat ini: <code><?= e($me['signature_path']) ?></code></div>
      <?php endif; ?>
    </div>

    <div class="col-12" style="display:flex;justify-content:flex-end">
      <button class="btn" type="submit">Simpan</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
