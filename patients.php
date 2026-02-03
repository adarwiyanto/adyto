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

function parse_dob_ddmmyyyy(?string $s): ?string {
  $s = trim((string)$s);
  if ($s === '') return null;
  if (!preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m)) return null;
  $d = (int)$m[1];
  $mo = (int)$m[2];
  $y = (int)$m[3];
  if (!checkdate($mo, $d, $y)) return null;
  return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

function dob_to_ddmmyyyy(?string $dbDob): string {
  $dbDob = trim((string)$dbDob);
  if ($dbDob === '') return '';
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dbDob, $m)) return '';
  return $m[3].'/'.$m[2].'/'.$m[1];
}

/**
 * ACTION: daftar berobat (masuk antrian visit_queue)
 */
if ($action === 'register_today' || $action === 'register_date') {
  auth_require_role(['admin','sekretariat','dokter','perawat']);

  $pid = (int)($_POST['id'] ?? 0);
  if ($pid <= 0) {
    flash_set('err','Pasien tidak valid.');
    redirect('/patients.php');
  }

  if ($action === 'register_today') {
    $date = date('Y-m-d');
  } else {
    $date = trim($_POST['queue_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      flash_set('err','Tanggal daftar tidak valid.');
      redirect('/patients.php');
    }
  }

  $st = db()->prepare("SELECT id FROM visit_queue WHERE patient_id=? AND queue_date=? AND status='new' LIMIT 1");
  $st->execute([$pid, $date]);
  if ($st->fetch()) {
    flash_set('err','Pasien sudah terdaftar (NEW) untuk tanggal tersebut.');
    redirect('/patients.php');
  }

  db_exec("INSERT INTO visit_queue(patient_id, queue_date, status, created_by, created_at)
           VALUES(?,?,?,?,?)",
    [$pid, $date, 'new', (int)($u['id'] ?? 0), now_dt()]
  );

  flash_set('ok','Pendaftaran berobat tersimpan. Tanggal: ' . $date);
  redirect('/patients.php');
}

/**
 * ACTION: create pasien
 */
if ($action === 'create') {
  $name = trim($_POST['full_name'] ?? '');
  $dob_in = trim($_POST['dob'] ?? '');
  $dob = parse_dob_ddmmyyyy($dob_in);
  $gender = $_POST['gender'] ?? 'L';
  $address = trim($_POST['address'] ?? '');

  if ($name === '') {
    flash_set('err','Nama wajib diisi.');
    redirect('/patients.php');
  }
  if ($dob_in !== '' && $dob === null) {
    flash_set('err','Format tanggal lahir harus dd/mm/yyyy. Contoh: 31/12/1990');
    redirect('/patients.php');
  }

  $prefix = date('Y');
  $row = db()->query("SELECT mrn FROM patients WHERE mrn LIKE '{$prefix}%' ORDER BY mrn DESC LIMIT 1")->fetch();
  $next = 1;
  if ($row && preg_match('/^\d{4}(\d{6})$/', $row['mrn'], $m)) $next = (int)$m[1] + 1;
  $mrn = $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);

  $stmt = db()->prepare("INSERT INTO patients(mrn, full_name, dob, gender, address, created_at) VALUES(?,?,?,?,?,?)");
  $stmt->execute([$mrn, $name, $dob, $gender, $address, now_dt()]);
  $newId = (int)db()->lastInsertId();

  flash_set('ok','Pasien ditambahkan. MRN: ' . $mrn);
  if (!empty($_POST['print_card']) && $newId > 0) {
    redirect('/patient_card_pdf.php?id=' . $newId);
  }
  redirect('/patients.php');
}

/**
 * ACTION: delete pasien (admin only)
 */
if ($action === 'delete') {
  auth_require_role(['admin']);
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    db_exec("DELETE FROM patients WHERE id=?", [$id]);
    flash_set('ok','Pasien dihapus.');
  }
  redirect('/patients.php');
}

// list pasien
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
      <div class="label">Tanggal Lahir (dd/mm/yyyy)</div>
      <input class="input" type="text" name="dob" placeholder="dd/mm/yyyy">
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

    <div class="col-12" style="display:flex;justify-content:flex-end;gap:10px">
      <button class="btn secondary" type="submit" name="print_card" value="1">Simpan &amp; Cetak Kartu</button>
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
          <td style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">

            <a class="btn small secondary" href="<?= e(url('/patient_edit.php?id='.(int)$r['id'])) ?>">Edit</a>

            <a class="btn small secondary" href="<?= e(url('/patient_card_pdf.php?id='.(int)$r['id'])) ?>" target="_blank">Kartu (PDF)</a>

            <?php if ($is_sekretariat || $is_admin): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="register_today">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn small" type="submit">Daftar Hari Ini</button>
              </form>

              <form method="post" style="display:flex;gap:6px;align-items:center">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="register_date">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input class="input" type="date" name="queue_date" style="max-width:160px">
                <button class="btn small secondary" type="submit">Daftar</button>
              </form>
            <?php endif; ?>

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
