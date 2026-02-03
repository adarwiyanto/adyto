<<<<<<< HEAD
<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin','dokter','perawat']);
$u = auth_user();
$settings = get_settings();
csrf_validate();

$patientId = (int)($_GET['patient_id'] ?? 0);
$new = isset($_GET['new']) ? 1 : 0;
$action = $_POST['action'] ?? '';

function next_visit_no(): string {
  $prefix = date('Ymd');
  $row = db()->query("SELECT visit_no FROM visits WHERE visit_no LIKE '{$prefix}%' ORDER BY visit_no DESC LIMIT 1")->fetch();
  $n = 1;
  if ($row && preg_match('/^\d{8}(\d{4})$/', $row['visit_no'], $m)) $n = (int)$m[1] + 1;
  return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

if ($action === 'create_visit') {
  $patientId = (int)($_POST['patient_id'] ?? 0);
  $anamnesis = trim($_POST['anamnesis'] ?? '');
  $physical = trim($_POST['physical_exam'] ?? '');
  $usg = trim($_POST['usg_report'] ?? '');
  $therapy = trim($_POST['therapy'] ?? 'Lanjutkan terapi dari dokter sebelumnya');

  if ($patientId <= 0) {
    flash_set('err','Pilih pasien terlebih dahulu.');
    redirect('/visits.php');
  }
  $visitNo = next_visit_no();
  db_exec("INSERT INTO visits(patient_id, visit_no, visit_date, anamnesis, physical_exam, usg_report, therapy, doctor_id, created_at)
           VALUES(?,?,?,?,?,?,?,?,?)",
    [$patientId, $visitNo, now_dt(), $anamnesis, $physical, $usg, $therapy, $u['id'], now_dt()]
  );
  flash_set('ok','Kunjungan tersimpan. No: ' . $visitNo);
  redirect('/visits.php?patient_id=' . $patientId);
}

if ($action === 'update_visit') {
  $id = (int)($_POST['id'] ?? 0);
  $anamnesis = trim($_POST['anamnesis'] ?? '');
  $physical = trim($_POST['physical_exam'] ?? '');
  $usg = trim($_POST['usg_report'] ?? '');
  $therapy = trim($_POST['therapy'] ?? '');

  db_exec("UPDATE visits SET anamnesis=?, physical_exam=?, usg_report=?, therapy=?, doctor_id=?, updated_at=? WHERE id=?",
    [$anamnesis, $physical, $usg, $therapy, $u['id'], now_dt(), $id]
  );
  flash_set('ok','Kunjungan diperbarui.');
  $pid = (int)($_POST['patient_id'] ?? 0);
  redirect('/visits.php?patient_id=' . $pid);
}

$patients = db()->query("SELECT id, mrn, full_name, dob, gender FROM patients ORDER BY created_at DESC LIMIT 300")->fetchAll();

$patient = null;
if ($patientId > 0) {
  $st = db()->prepare("SELECT * FROM patients WHERE id = ?");
  $st->execute([$patientId]);
  $patient = $st->fetch();
}

$visits = [];
if ($patientId > 0) {
  $st = db()->prepare("SELECT v.*, u.full_name AS doctor_name
                       FROM visits v LEFT JOIN users u ON u.id=v.doctor_id
                       WHERE v.patient_id = ? ORDER BY v.visit_date DESC LIMIT 50");
  $st->execute([$patientId]);
  $visits = $st->fetchAll();
}

$title = "Kunjungan";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Kunjungan</div>
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div style="min-width:320px;flex:1">
      <div class="label">Pilih pasien (atau ketik lalu pilih)</div>
      <select class="input" name="patient_id" required>
        <option value="">-- pilih --</option>
        <?php foreach ($patients as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= $patientId==(int)$p['id']?'selected':'' ?>>
            <?= e($p['mrn'].' - '.$p['full_name'].' ('.($p['dob']?age_from_dob($p['dob']).' th':'-').')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn secondary" type="submit">Tampilkan</button>
    <a class="btn" href="<?= e(url('/patients.php')) ?>">+ Pasien</a>
  </form>
</div>

<?php if ($patient): ?>
  <div class="card">
    <div class="h1" style="font-size:16px">Identitas Pasien</div>
    <div class="grid">
      <div class="col-6"><div class="muted">MRN</div><div><?= e($patient['mrn']) ?></div></div>
      <div class="col-6"><div class="muted">Nama</div><div><?= e($patient['full_name']) ?></div></div>
      <div class="col-6"><div class="muted">Usia</div><div><?= e((string)(age_from_dob($patient['dob']) ?? '-')) ?></div></div>
      <div class="col-6"><div class="muted">Jenis Kelamin</div><div><?= e($patient['gender']) ?></div></div>
      <div class="col-12"><div class="muted">Alamat</div><div><?= e($patient['address'] ?? '') ?></div></div>
    </div>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Tambah Kunjungan Baru</div>
    <form method="post" class="grid" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_visit">
      <input type="hidden" name="patient_id" value="<?= (int)$patientId ?>">

      <div class="col-12">
        <div class="label">Anamnesa</div>
        <textarea class="input" name="anamnesis"></textarea>
      </div>
      <div class="col-12">
        <div class="label">Pemeriksaan Fisik</div>
        <textarea class="input" name="physical_exam"></textarea>
      </div>
      <div class="col-12">
        <div class="label">Laporan USG</div>
        <textarea class="input" name="usg_report"></textarea>
      </div>
      <div class="col-12">
        <div class="label">Pengobatan / Terapi</div>
        <textarea class="input" name="therapy">Lanjutkan terapi dari dokter sebelumnya</textarea>
      </div>
      <div class="col-12" style="display:flex;justify-content:flex-end;gap:10px">
        <button class="btn" type="submit">Simpan Kunjungan</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Riwayat Kunjungan</div>
    <table class="table">
      <thead>
        <tr><th>Tanggal</th><th>No Kunjungan</th><th>Dokter</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($visits as $v): ?>
          <tr>
            <td><?= e($v['visit_date']) ?></td>
            <td><?= e($v['visit_no']) ?></td>
            <td><?= e($v['doctor_name'] ?? '-') ?></td>
            <td style="display:flex;gap:8px;flex-wrap:wrap">
              <a class="btn small secondary" href="<?= e(url('/visit_edit.php?id='.(int)$v['id'])) ?>">Edit</a>
              <a class="btn small" href="<?= e(url('/print_visit.php?id='.(int)$v['id'])) ?>" target="_blank">Print Hasil</a>
              <a class="btn small secondary" href="<?= e(url('/prescriptions.php?visit_id='.(int)$v['id'])) ?>">Resep</a>
              <a class="btn small secondary" href="<?= e(url('/referrals.php?visit_id='.(int)$v['id'])) ?>">Rujukan</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$visits): ?><tr><td colspan="4" class="muted">Belum ada kunjungan.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
=======
<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin','dokter','perawat']);
$u = auth_user();
$settings = get_settings();
csrf_validate();

$patientId = (int)($_GET['patient_id'] ?? 0);
$new = isset($_GET['new']) ? 1 : 0;
$action = $_POST['action'] ?? '';

function next_visit_no(): string {
  $prefix = date('Ymd');
  $row = db()->query("SELECT visit_no FROM visits WHERE visit_no LIKE '{$prefix}%' ORDER BY visit_no DESC LIMIT 1")->fetch();
  $n = 1;
  if ($row && preg_match('/^\d{8}(\d{4})$/', $row['visit_no'], $m)) $n = (int)$m[1] + 1;
  return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

if ($action === 'create_visit') {
  $patientId = (int)($_POST['patient_id'] ?? 0);
  $anamnesis = trim($_POST['anamnesis'] ?? '');
  $physical = trim($_POST['physical_exam'] ?? '');
  $usg = trim($_POST['usg_report'] ?? '');
  $therapy = trim($_POST['therapy'] ?? 'Lanjutkan terapi dari dokter sebelumnya');

  if ($patientId <= 0) {
    flash_set('err','Pilih pasien terlebih dahulu.');
    redirect('/visits.php');
  }
  $visitNo = next_visit_no();
  db_exec("INSERT INTO visits(patient_id, visit_no, visit_date, anamnesis, physical_exam, usg_report, therapy, doctor_id, created_at)
           VALUES(?,?,?,?,?,?,?,?,?)",
    [$patientId, $visitNo, now_dt(), $anamnesis, $physical, $usg, $therapy, $u['id'], now_dt()]
  );
  flash_set('ok','Kunjungan tersimpan. No: ' . $visitNo);
  redirect('/visits.php?patient_id=' . $patientId);
}

if ($action === 'update_visit') {
  $id = (int)($_POST['id'] ?? 0);
  $anamnesis = trim($_POST['anamnesis'] ?? '');
  $physical = trim($_POST['physical_exam'] ?? '');
  $usg = trim($_POST['usg_report'] ?? '');
  $therapy = trim($_POST['therapy'] ?? '');

  db_exec("UPDATE visits SET anamnesis=?, physical_exam=?, usg_report=?, therapy=?, doctor_id=?, updated_at=? WHERE id=?",
    [$anamnesis, $physical, $usg, $therapy, $u['id'], now_dt(), $id]
  );
  flash_set('ok','Kunjungan diperbarui.');
  $pid = (int)($_POST['patient_id'] ?? 0);
  redirect('/visits.php?patient_id=' . $pid);
}

$patients = db()->query("SELECT id, mrn, full_name, dob, gender FROM patients ORDER BY created_at DESC LIMIT 300")->fetchAll();

$patient = null;
if ($patientId > 0) {
  $st = db()->prepare("SELECT * FROM patients WHERE id = ?");
  $st->execute([$patientId]);
  $patient = $st->fetch();
}

$visits = [];
if ($patientId > 0) {
  $st = db()->prepare("SELECT v.*, u.full_name AS doctor_name
                       FROM visits v LEFT JOIN users u ON u.id=v.doctor_id
                       WHERE v.patient_id = ? ORDER BY v.visit_date DESC LIMIT 50");
  $st->execute([$patientId]);
  $visits = $st->fetchAll();
}

$title = "Kunjungan";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Kunjungan</div>
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div style="min-width:320px;flex:1">
      <div class="label">Pilih pasien (atau ketik lalu pilih)</div>
      <select class="input" name="patient_id" required>
        <option value="">-- pilih --</option>
        <?php foreach ($patients as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= $patientId==(int)$p['id']?'selected':'' ?>>
            <?= e($p['mrn'].' - '.$p['full_name'].' ('.($p['dob']?age_from_dob($p['dob']).' th':'-').')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn secondary" type="submit">Tampilkan</button>
    <a class="btn" href="<?= e(url('/patients.php')) ?>">+ Pasien</a>
  </form>
</div>

<?php if ($patient): ?>
  <div class="card">
    <div class="h1" style="font-size:16px">Identitas Pasien</div>
    <div class="grid">
      <div class="col-6"><div class="muted">MRN</div><div><?= e($patient['mrn']) ?></div></div>
      <div class="col-6"><div class="muted">Nama</div><div><?= e($patient['full_name']) ?></div></div>
      <div class="col-6"><div class="muted">Usia</div><div><?= e((string)(age_from_dob($patient['dob']) ?? '-')) ?></div></div>
      <div class="col-6"><div class="muted">Jenis Kelamin</div><div><?= e($patient['gender']) ?></div></div>
      <div class="col-12"><div class="muted">Alamat</div><div><?= e($patient['address'] ?? '') ?></div></div>
    </div>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Tambah Kunjungan Baru</div>
    <form method="post" class="grid" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_visit">
      <input type="hidden" name="patient_id" value="<?= (int)$patientId ?>">

      <div class="col-12">
        <div class="label">Anamnesa</div>
        <textarea class="input" name="anamnesis"></textarea>
      </div>
      <div class="col-12">
        <div class="label">Pemeriksaan Fisik</div>
        <textarea class="input" name="physical_exam"></textarea>
      </div>
      <div class="col-12">
        <div class="label">Laporan USG</div>
        <textarea class="input" name="usg_report"></textarea>
      </div>
      <div class="col-12">
        <div class="label">Pengobatan / Terapi</div>
        <textarea class="input" name="therapy">Lanjutkan terapi dari dokter sebelumnya</textarea>
      </div>
      <div class="col-12" style="display:flex;justify-content:flex-end;gap:10px">
        <button class="btn" type="submit">Simpan Kunjungan</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Riwayat Kunjungan</div>
    <table class="table">
      <thead>
        <tr><th>Tanggal</th><th>No Kunjungan</th><th>Dokter</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($visits as $v): ?>
          <tr>
            <td><?= e($v['visit_date']) ?></td>
            <td><?= e($v['visit_no']) ?></td>
            <td><?= e($v['doctor_name'] ?? '-') ?></td>
            <td style="display:flex;gap:8px;flex-wrap:wrap">
              <a class="btn small secondary" href="<?= e(url('/visit_edit.php?id='.(int)$v['id'])) ?>">Edit</a>
              <a class="btn small" href="<?= e(url('/print_visit.php?id='.(int)$v['id'])) ?>" target="_blank">Print Hasil</a>
              <a class="btn small secondary" href="<?= e(url('/prescriptions.php?visit_id='.(int)$v['id'])) ?>">Resep</a>
              <a class="btn small secondary" href="<?= e(url('/referrals.php?visit_id='.(int)$v['id'])) ?>">Rujukan</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$visits): ?><tr><td colspan="4" class="muted">Belum ada kunjungan.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
>>>>>>> 8b27ebf (Add surat sakit & informed consent)
