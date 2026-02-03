<<<<<<< HEAD
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
$is_admin = ($role === 'admin');
$is_sekretariat = ($role === 'sekretariat');

$q = trim($_GET['q'] ?? '');
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  $name = trim($_POST['full_name'] ?? '');
  $dob = $_POST['dob'] ?? null;
  $gender = $_POST['gender'] ?? 'L';
  $address = trim($_POST['address'] ?? '');

  if ($name === '') {
    flash_set('err','Nama wajib diisi.');
    redirect('/patients.php');
  }

  $prefix = date('Y');
  $row = db()->query("SELECT mrn FROM patients WHERE mrn LIKE '{$prefix}%' ORDER BY mrn DESC LIMIT 1")->fetch();
  $next = 1;
  if ($row && preg_match('/^\d{4}(\d{6})$/', $row['mrn'], $m)) $next = (int)$m[1] + 1;
  $mrn = $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);

  db_exec("INSERT INTO patients(mrn, full_name, dob, gender, address, created_at) VALUES(?,?,?,?,?,?)",
    [$mrn, $name, $dob ?: null, $gender, $address, now_dt()]
  );
  flash_set('ok','Pasien ditambahkan. MRN: ' . $mrn);
  redirect('/patients.php');
}

if ($action === 'delete') {
  auth_require_role(['admin']);
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    db_exec("DELETE FROM patients WHERE id=?", [$id]);
    flash_set('ok','Pasien dihapus.');
  }
  redirect('/patients.php');
}

$sql = "SELECT id, mrn, full_name, dob, gender, address, created_at FROM patients";
$params = [];
if ($q !== '') {
  $sql .= " WHERE mrn LIKE ? OR full_name LIKE ? ";
  $params = ["%$q%","%$q%"];
}
$sql .= " ORDER BY created_at DESC LIMIT 200";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$title = "Pasien";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Pasien</div>
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap">
    <input class="input" name="q" placeholder="Cari MRN / nama" value="<?= e($q) ?>" style="max-width:360px">
    <button class="btn secondary" type="submit">Cari</button>
  </form>
</div>

<div class="card">
  <div class="h1" style="font-size:16px">Pendaftaran Pasien Baru</div>
  <form method="post" class="grid" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">
    <div class="col-6">
      <div class="label">Nama</div>
      <input class="input" name="full_name" required>
      <?php if ($is_sekretariat): ?>
        <div class="muted" style="margin-top:6px">Sekretariat hanya mengelola data pasien (nama) dan jadwal.</div>
      <?php endif; ?>
    </div>
    <div class="col-6">
      <div class="label">Tanggal Lahir</div>
      <input class="input" type="date" name="dob">
    </div>
    <div class="col-6">
      <div class="label">Jenis Kelamin</div>
      <select class="input" name="gender">
        <option value="L">Laki-laki</option>
        <option value="P">Perempuan</option>
      </select>
    </div>
    <div class="col-12">
      <div class="label">Alamat</div>
      <input class="input" name="address">
    </div>
    <div class="col-12" style="display:flex;justify-content:flex-end">
      <button class="btn" type="submit">Simpan</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="h1" style="font-size:16px">Daftar Pasien (maks 200 terbaru)</div>
  <table class="table">
    <thead>
      <tr><th>MRN</th><th>Nama</th><th>Usia</th><th>JK</th><th>Alamat</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['mrn']) ?></td>
          <td><?= e($r['full_name']) ?></td>
          <td><?= e((string)(age_from_dob($r['dob']) ?? '-')) ?></td>
          <td><?= e($r['gender']) ?></td>
          <td><?= e($r['address'] ?? '') ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn small secondary" href="<?= e(url('/patient_edit.php?id='.(int)$r['id'])) ?>">Edit</a>
            <?php if (!$is_sekretariat): ?>
              <a class="btn small secondary" href="<?= e(url('/visits.php?patient_id='.(int)$r['id'])) ?>">Kunjungan</a>
            <?php endif; ?>
            <?php if ($is_admin): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Hapus pasien? (hanya admin)')">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn small danger" type="submit">Hapus</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">Belum ada data.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
=======
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
$is_admin = ($role === 'admin');
$is_sekretariat = ($role === 'sekretariat');

$q = trim($_GET['q'] ?? '');
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  $name = trim($_POST['full_name'] ?? '');
  $dob = $_POST['dob'] ?? null;
  $gender = $_POST['gender'] ?? 'L';
  $address = trim($_POST['address'] ?? '');

  if ($name === '') {
    flash_set('err','Nama wajib diisi.');
    redirect('/patients.php');
  }

  $prefix = date('Y');
  $row = db()->query("SELECT mrn FROM patients WHERE mrn LIKE '{$prefix}%' ORDER BY mrn DESC LIMIT 1")->fetch();
  $next = 1;
  if ($row && preg_match('/^\d{4}(\d{6})$/', $row['mrn'], $m)) $next = (int)$m[1] + 1;
  $mrn = $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);

  db_exec("INSERT INTO patients(mrn, full_name, dob, gender, address, created_at) VALUES(?,?,?,?,?,?)",
    [$mrn, $name, $dob ?: null, $gender, $address, now_dt()]
  );
  flash_set('ok','Pasien ditambahkan. MRN: ' . $mrn);
  redirect('/patients.php');
}

if ($action === 'delete') {
  auth_require_role(['admin']);
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    db_exec("DELETE FROM patients WHERE id=?", [$id]);
    flash_set('ok','Pasien dihapus.');
  }
  redirect('/patients.php');
}

$sql = "SELECT id, mrn, full_name, dob, gender, address, created_at FROM patients";
$params = [];
if ($q !== '') {
  $sql .= " WHERE mrn LIKE ? OR full_name LIKE ? ";
  $params = ["%$q%","%$q%"];
}
$sql .= " ORDER BY created_at DESC LIMIT 200";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$title = "Pasien";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Pasien</div>
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap">
    <input class="input" name="q" placeholder="Cari MRN / nama" value="<?= e($q) ?>" style="max-width:360px">
    <button class="btn secondary" type="submit">Cari</button>
  </form>
</div>

<div class="card">
  <div class="h1" style="font-size:16px">Pendaftaran Pasien Baru</div>
  <form method="post" class="grid" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">
    <div class="col-6">
      <div class="label">Nama</div>
      <input class="input" name="full_name" required>
      <?php if ($is_sekretariat): ?>
        <div class="muted" style="margin-top:6px">Sekretariat hanya mengelola data pasien (nama) dan jadwal.</div>
      <?php endif; ?>
    </div>
    <div class="col-6">
      <div class="label">Tanggal Lahir</div>
      <input class="input" type="date" name="dob">
    </div>
    <div class="col-6">
      <div class="label">Jenis Kelamin</div>
      <select class="input" name="gender">
        <option value="L">Laki-laki</option>
        <option value="P">Perempuan</option>
      </select>
    </div>
    <div class="col-12">
      <div class="label">Alamat</div>
      <input class="input" name="address">
    </div>
    <div class="col-12" style="display:flex;justify-content:flex-end">
      <button class="btn" type="submit">Simpan</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="h1" style="font-size:16px">Daftar Pasien (maks 200 terbaru)</div>
  <table class="table">
    <thead>
      <tr><th>MRN</th><th>Nama</th><th>Usia</th><th>JK</th><th>Alamat</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['mrn']) ?></td>
          <td><?= e($r['full_name']) ?></td>
          <td><?= e((string)(age_from_dob($r['dob']) ?? '-')) ?></td>
          <td><?= e($r['gender']) ?></td>
          <td><?= e($r['address'] ?? '') ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn small secondary" href="<?= e(url('/patient_edit.php?id='.(int)$r['id'])) ?>">Edit</a>
            <?php if (!$is_sekretariat): ?>
              <a class="btn small secondary" href="<?= e(url('/visits.php?patient_id='.(int)$r['id'])) ?>">Kunjungan</a>
            <?php endif; ?>
            <?php if ($is_admin): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Hapus pasien? (hanya admin)')">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn small danger" type="submit">Hapus</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">Belum ada data.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
>>>>>>> 8b27ebf (Add surat sakit & informed consent)
