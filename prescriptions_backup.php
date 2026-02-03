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

$visitId = (int)($_GET['visit_id'] ?? ($_POST['visit_id'] ?? 0));
$action = $_POST['action'] ?? '';

function next_rx_no(): string {
  $prefix = 'RX' . date('Ymd');
  $row = db()->query("SELECT rx_no FROM prescriptions WHERE rx_no LIKE '{$prefix}%' ORDER BY rx_no DESC LIMIT 1")->fetch();
  $n = 1;
  if ($row && preg_match('/^RX\d{8}(\d{4})$/', $row['rx_no'], $m)) $n = (int)$m[1] + 1;
  return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

$visit = null;
if ($visitId > 0) {
  $st = db()->prepare("SELECT v.*, p.mrn, p.full_name, p.dob, p.gender, p.address, u.full_name AS doctor_name
                       FROM visits v
                       JOIN patients p ON p.id=v.patient_id
                       LEFT JOIN users u ON u.id=v.doctor_id
                       WHERE v.id=?");
  $st->execute([$visitId]);
  $visit = $st->fetch();
}

if ($action === 'create_rx' && $visit) {
  $content = trim($_POST['content'] ?? '');
  if ($content === '') {
    flash_set('err','Isi resep wajib.');
    redirect('/prescriptions.php?visit_id=' . $visitId);
  }
  $rxNo = next_rx_no();
  db_exec("INSERT INTO prescriptions(visit_id, rx_no, content, created_at) VALUES(?,?,?,?)",
    [$visitId, $rxNo, $content, now_dt()]
  );
  flash_set('ok','Resep dibuat. No: ' . $rxNo);
  redirect('/prescriptions.php?visit_id=' . $visitId);
}

$rxList = [];
if ($visitId > 0) {
  $st = db()->prepare("SELECT * FROM prescriptions WHERE visit_id=? ORDER BY created_at DESC LIMIT 50");
  $st->execute([$visitId]);
  $rxList = $st->fetchAll();
}

$title = "Resep";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Resep</div>
  <?php if ($visit): ?>
    <div class="muted">Kunjungan: <?= e($visit['visit_no']) ?> | Pasien: <?= e($visit['mrn'].' - '.$visit['full_name']) ?></div>
  <?php else: ?>
    <div class="muted">Pilih kunjungan dari menu Kunjungan → Resep.</div>
  <?php endif; ?>
</div>

<?php if ($visit): ?>
  <div class="card">
    <div class="h1" style="font-size:16px">Buat Resep</div>
    <form method="post" class="grid">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_rx">
      <input type="hidden" name="visit_id" value="<?= (int)$visitId ?>">
      <div class="col-12">
        <div class="label">Isi resep</div>
        <textarea class="input" name="content" placeholder="R/ ..."></textarea>
      </div>
      <div class="col-12" style="display:flex;justify-content:flex-end">
        <button class="btn" type="submit">Simpan Resep</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Riwayat Resep</div>
    <table class="table">
      <thead><tr><th>No Resep</th><th>Tanggal</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rxList as $rx): ?>
          <tr>
            <td><?= e($rx['rx_no']) ?></td>
            <td><?= e($rx['created_at']) ?></td>
            <td><a class="btn small" href="<?= e(url('/print_rx.php?id='.(int)$rx['id'])) ?>" target="_blank">Print Resep</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rxList): ?><tr><td colspan="3" class="muted">Belum ada resep.</td></tr><?php endif; ?>
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

$visitId = (int)($_GET['visit_id'] ?? ($_POST['visit_id'] ?? 0));
$action = $_POST['action'] ?? '';

function next_rx_no(): string {
  $prefix = 'RX' . date('Ymd');
  $row = db()->query("SELECT rx_no FROM prescriptions WHERE rx_no LIKE '{$prefix}%' ORDER BY rx_no DESC LIMIT 1")->fetch();
  $n = 1;
  if ($row && preg_match('/^RX\d{8}(\d{4})$/', $row['rx_no'], $m)) $n = (int)$m[1] + 1;
  return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

$visit = null;
if ($visitId > 0) {
  $st = db()->prepare("SELECT v.*, p.mrn, p.full_name, p.dob, p.gender, p.address, u.full_name AS doctor_name
                       FROM visits v
                       JOIN patients p ON p.id=v.patient_id
                       LEFT JOIN users u ON u.id=v.doctor_id
                       WHERE v.id=?");
  $st->execute([$visitId]);
  $visit = $st->fetch();
}

if ($action === 'create_rx' && $visit) {
  $content = trim($_POST['content'] ?? '');
  if ($content === '') {
    flash_set('err','Isi resep wajib.');
    redirect('/prescriptions.php?visit_id=' . $visitId);
  }
  $rxNo = next_rx_no();
  db_exec("INSERT INTO prescriptions(visit_id, rx_no, content, created_at) VALUES(?,?,?,?)",
    [$visitId, $rxNo, $content, now_dt()]
  );
  flash_set('ok','Resep dibuat. No: ' . $rxNo);
  redirect('/prescriptions.php?visit_id=' . $visitId);
}

$rxList = [];
if ($visitId > 0) {
  $st = db()->prepare("SELECT * FROM prescriptions WHERE visit_id=? ORDER BY created_at DESC LIMIT 50");
  $st->execute([$visitId]);
  $rxList = $st->fetchAll();
}

$title = "Resep";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Resep</div>
  <?php if ($visit): ?>
    <div class="muted">Kunjungan: <?= e($visit['visit_no']) ?> | Pasien: <?= e($visit['mrn'].' - '.$visit['full_name']) ?></div>
  <?php else: ?>
    <div class="muted">Pilih kunjungan dari menu Kunjungan → Resep.</div>
  <?php endif; ?>
</div>

<?php if ($visit): ?>
  <div class="card">
    <div class="h1" style="font-size:16px">Buat Resep</div>
    <form method="post" class="grid">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_rx">
      <input type="hidden" name="visit_id" value="<?= (int)$visitId ?>">
      <div class="col-12">
        <div class="label">Isi resep</div>
        <textarea class="input" name="content" placeholder="R/ ..."></textarea>
      </div>
      <div class="col-12" style="display:flex;justify-content:flex-end">
        <button class="btn" type="submit">Simpan Resep</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Riwayat Resep</div>
    <table class="table">
      <thead><tr><th>No Resep</th><th>Tanggal</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rxList as $rx): ?>
          <tr>
            <td><?= e($rx['rx_no']) ?></td>
            <td><?= e($rx['created_at']) ?></td>
            <td><a class="btn small" href="<?= e(url('/print_rx.php?id='.(int)$rx['id'])) ?>" target="_blank">Print Resep</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rxList): ?><tr><td colspan="3" class="muted">Belum ada resep.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
>>>>>>> 8b27ebf (Add surat sakit & informed consent)
