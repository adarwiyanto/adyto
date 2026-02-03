<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin']);
$u = auth_user();
$settings = get_settings();
csrf_validate();

$action = $_POST['action'] ?? '';

if ($action === 'add') {
  $name = trim((string)($_POST['doctor_name'] ?? ''));
  if ($name === '') {
    flash_set('err','Nama dokter perujuk wajib diisi.');
    redirect('/referral_doctors.php');
  }
  db_exec(
    "INSERT INTO referral_doctors(doctor_name, is_active, created_at, updated_at) VALUES(?,?,?,?)",
    [$name, 1, now_dt(), null]
  );
  flash_set('ok','Dokter perujuk ditambahkan.');
  redirect('/referral_doctors.php');
}

if ($action === 'toggle') {
  $id = (int)($_POST['id'] ?? 0);
  $to = (int)($_POST['to'] ?? 0);
  if ($id > 0) {
    db_exec("UPDATE referral_doctors SET is_active=?, updated_at=? WHERE id=?", [$to ? 1 : 0, now_dt(), $id]);
    flash_set('ok','Status dokter perujuk diperbarui.');
  }
  redirect('/referral_doctors.php');
}

if ($action === 'rename') {
  $id = (int)($_POST['id'] ?? 0);
  $name = trim((string)($_POST['doctor_name'] ?? ''));
  if ($id <= 0 || $name === '') {
    flash_set('err','Data tidak valid.');
    redirect('/referral_doctors.php');
  }
  db_exec("UPDATE referral_doctors SET doctor_name=?, updated_at=? WHERE id=?", [$name, now_dt(), $id]);
  flash_set('ok','Nama dokter perujuk diperbarui.');
  redirect('/referral_doctors.php');
}

$rows = [];
try {
  $rows = db()->query("SELECT id, doctor_name, is_active, created_at, updated_at FROM referral_doctors ORDER BY doctor_name ASC")->fetchAll();
} catch (Throwable $e) {
  $rows = [];
}

$title = 'Dokter Perujuk';
require __DIR__ . '/app/views/partials/header.php';
?>

<div class="card">
  <div class="h1">Dokter Perujuk</div>
  <div class="muted">Master data dokter perujuk untuk dipilih dari menu saat input kunjungan.</div>
</div>

<div class="card">
  <div class="h1" style="font-size:16px">Tambah Dokter Perujuk</div>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="add">

    <div style="min-width:320px;flex:1">
      <div class="label">Nama dokter</div>
      <input class="input" name="doctor_name" placeholder="Contoh: dr. Amir Yusuf, Sp.JP" required>
    </div>

    <button class="btn" type="submit">Tambah</button>
  </form>
</div>

<div class="card">
  <div class="h1" style="font-size:16px">Daftar Dokter Perujuk</div>
  <table class="table">
    <thead>
      <tr>
        <th>Nama</th>
        <th>Status</th>
        <th>Dibuat</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td style="min-width:280px">
            <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="rename">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input class="input" name="doctor_name" value="<?= e($r['doctor_name']) ?>" style="min-width:260px;flex:1">
              <button class="btn small secondary" type="submit">Simpan</button>
            </form>
          </td>
          <td>
            <?= ((int)$r['is_active']===1) ? '<span class="badge ok">Aktif</span>' : '<span class="badge">Nonaktif</span>' ?>
          </td>
          <td class="muted"><?= e($r['created_at']) ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap">
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="to" value="<?= ((int)$r['is_active']===1)?0:1 ?>">
              <button class="btn small <?= ((int)$r['is_active']===1)?'danger':'secondary' ?>" type="submit">
                <?= ((int)$r['is_active']===1)?'Nonaktifkan':'Aktifkan' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="4" class="muted">Belum ada data dokter perujuk. Tambahkan di form atas.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
