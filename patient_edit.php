<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$u = auth_user();
$settings = get_settings();
csrf_validate();

$role = $u['role'] ?? '';
$is_sekretariat = ($role === 'sekretariat');

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT * FROM patients WHERE id=?");
$st->execute([$id]);
$p = $st->fetch();
if (!$p) { http_response_code(404); echo "Not found"; exit; }

if (is_post()) {
  $name = trim($_POST['full_name'] ?? '');
  if ($name === '') { flash_set('err','Nama wajib diisi.'); redirect('/patient_edit.php?id='.$id); }

  if ($is_sekretariat) {
    db_exec("UPDATE patients SET full_name=? WHERE id=?", [$name, $id]);
  } else {
    $dob = $_POST['dob'] ?? null;
    $gender = $_POST['gender'] ?? 'L';
    $address = trim($_POST['address'] ?? '');
    db_exec("UPDATE patients SET full_name=?, dob=?, gender=?, address=? WHERE id=?",
      [$name, $dob ?: null, $gender, $address, $id]
    );
  }

  flash_set('ok','Data pasien diperbarui.');
  redirect('/patients.php');
}

$title = "Edit Pasien";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Edit Pasien</div>
  <div class="muted">MRN: <?= e($p['mrn']) ?></div>
  <?php if ($is_sekretariat): ?><div class="muted">Sekretariat hanya dapat mengubah <b>Nama</b>.</div><?php endif; ?>
</div>

<div class="card">
  <form method="post" class="grid" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="col-6">
      <div class="label">Nama</div>
      <input class="input" name="full_name" value="<?= e($p['full_name']) ?>" required>
    </div>

    <?php if (!$is_sekretariat): ?>
      <div class="col-6">
        <div class="label">Tanggal Lahir</div>
        <input class="input" type="date" name="dob" value="<?= e($p['dob'] ?? '') ?>">
      </div>
      <div class="col-6">
        <div class="label">Jenis Kelamin</div>
        <select class="input" name="gender">
          <option value="L" <?= ($p['gender']==='L')?'selected':'' ?>>Laki-laki</option>
          <option value="P" <?= ($p['gender']==='P')?'selected':'' ?>>Perempuan</option>
        </select>
      </div>
      <div class="col-12">
        <div class="label">Alamat</div>
        <input class="input" name="address" value="<?= e($p['address'] ?? '') ?>">
      </div>
    <?php endif; ?>

    <div class="col-12" style="display:flex;justify-content:flex-end;gap:10px">
      <a class="btn secondary" href="<?= e(url('/patients.php')) ?>">Kembali</a>
      <button class="btn" type="submit">Simpan</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
