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

$range = $_GET['range'] ?? 'today';
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

function sick_range_dates(string $range, string $start, string $end): array {
  $today = date('Y-m-d');
  if ($range === 'today') return [$today, $today];
  if ($range === 'yesterday') {
    $y = date('Y-m-d', strtotime('-1 day'));
    return [$y, $y];
  }
  if ($range === 'last7') {
    $s = date('Y-m-d', strtotime('-6 day'));
    return [$s, $today];
  }
  if ($range === 'custom' && $start && $end) return [$start, $end];
  return [$today, $today];
}

function next_sick_no(): string {
  $prefix = 'SS' . date('Ymd');
  $row = db()->query("SELECT letter_no FROM sick_letters WHERE letter_no LIKE '{$prefix}%' ORDER BY letter_no DESC LIMIT 1")->fetch();
  $n = 1;
  if ($row && preg_match('/^SS\d{8}(\d{4})$/', $row['letter_no'], $m)) $n = (int)$m[1] + 1;
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

if ($action === 'create_sick') {
  if (!$visit) {
    flash_set('err','Surat sakit harus dibuat dari kunjungan (visit_id tidak valid).');
    redirect('/sick_letters.php');
  }

  $startDate = trim($_POST['start_date'] ?? '');
  $endDate = trim($_POST['end_date'] ?? '');
  $diagnosis = trim($_POST['diagnosis'] ?? '');
  $notes = trim($_POST['notes'] ?? '');

  if ($startDate === '' || $endDate === '') {
    flash_set('err','Tanggal mulai dan selesai wajib diisi.');
    redirect('/sick_letters.php?visit_id=' . $visitId);
  }

  if (strtotime($endDate) < strtotime($startDate)) {
    flash_set('err','Tanggal selesai tidak boleh lebih kecil dari tanggal mulai.');
    redirect('/sick_letters.php?visit_id=' . $visitId);
  }

  $letterNo = next_sick_no();
  db_exec("INSERT INTO sick_letters(visit_id, patient_id, doctor_id, letter_no, diagnosis, start_date, end_date, notes, created_at)
           VALUES(?,?,?,?,?,?,?,?,?)",
    [$visitId, (int)$visit['patient_id'], (int)$visit['doctor_id'], $letterNo, $diagnosis, $startDate, $endDate, $notes, now_dt()]
  );

  flash_set('ok','Surat sakit dibuat. No: ' . $letterNo);
  redirect('/sick_letters.php?visit_id=' . $visitId);
}

$sickList = [];
if ($visitId > 0) {
  $st = db()->prepare("SELECT * FROM sick_letters WHERE visit_id=? ORDER BY created_at DESC LIMIT 50");
  $st->execute([$visitId]);
  $sickList = $st->fetchAll();
}

$title = 'Surat Sakit';
require __DIR__ . '/app/views/partials/header.php';
?>

<div class="card">
  <div class="h1">Surat Sakit</div>
  <?php if ($visit): ?>
    <div class="muted">Kunjungan: <?= e($visit['visit_no']) ?> | Pasien: <?= e($visit['mrn'].' - '.$visit['full_name']) ?></div>
  <?php else: ?>
    <div class="muted">Rekap surat sakit berdasarkan rentang waktu.</div>
  <?php endif; ?>
</div>

<?php if (!$visit): ?>
  <?php
    list($d1, $d2) = sick_range_dates($range, $start, $end);
    $dt1 = $d1 . ' 00:00:00';
    $dt2 = $d2 . ' 23:59:59';

    $st = db()->prepare("
      SELECT s.*, p.mrn, p.full_name, v.visit_no
      FROM sick_letters s
      JOIN patients p ON p.id=s.patient_id
      LEFT JOIN visits v ON v.id=s.visit_id
      WHERE s.created_at BETWEEN ? AND ?
      ORDER BY s.created_at DESC
      LIMIT 200
    ");
    $st->execute([$dt1, $dt2]);
    $rows = $st->fetchAll();
  ?>

  <div class="card">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
      <div style="min-width:220px">
        <div class="label">Rentang</div>
        <select class="input" name="range">
          <option value="today" <?= $range==='today'?'selected':'' ?>>Hari ini</option>
          <option value="yesterday" <?= $range==='yesterday'?'selected':'' ?>>Kemarin</option>
          <option value="last7" <?= $range==='last7'?'selected':'' ?>>7 hari terakhir</option>
          <option value="custom" <?= $range==='custom'?'selected':'' ?>>Custom</option>
        </select>
      </div>
      <div>
        <div class="label">Mulai</div>
        <input class="input" type="date" name="start" value="<?= e($d1) ?>">
      </div>
      <div>
        <div class="label">Sampai</div>
        <input class="input" type="date" name="end" value="<?= e($d2) ?>">
      </div>
      <button class="btn secondary" type="submit">Terapkan</button>
    </form>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Daftar Surat Sakit</div>
    <table class="table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No Surat</th>
          <th>Pasien</th>
          <th>No Kunjungan</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e($row['created_at']) ?></td>
            <td><?= e($row['letter_no']) ?></td>
            <td><?= e(($row['mrn'] ?? '').' - '.($row['full_name'] ?? '')) ?></td>
            <td><?= e($row['visit_no'] ?? '-') ?></td>
            <td>
              <a class="btn small" href="<?= e(url('/print_sick_letter.php?id='.(int)$row['id'])) ?>" target="_blank">Print</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted">Tidak ada data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php require __DIR__ . '/app/views/partials/footer.php'; ?>
  <?php exit; ?>
<?php endif; ?>

<?php if ($visit): ?>
  <div class="card">
    <div class="h1" style="font-size:16px">Buat Surat Sakit</div>
    <form method="post" class="grid" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_sick">
      <input type="hidden" name="visit_id" value="<?= (int)$visitId ?>">

      <div class="col-6">
        <div class="label">Tanggal Mulai</div>
        <input class="input" type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>">
      </div>
      <div class="col-6">
        <div class="label">Tanggal Selesai</div>
        <input class="input" type="date" name="end_date" value="<?= e(date('Y-m-d')) ?>">
      </div>

      <div class="col-12">
        <div class="label">Diagnosa</div>
        <textarea class="input" name="diagnosis" placeholder="Diagnosa / alasan surat sakit"></textarea>
      </div>

      <div class="col-12">
        <div class="label">Catatan</div>
        <textarea class="input" name="notes" placeholder="Catatan tambahan (opsional)"></textarea>
      </div>

      <div class="col-12" style="display:flex;justify-content:flex-end">
        <button class="btn" type="submit">Simpan Surat Sakit</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Riwayat Surat Sakit</div>
    <table class="table">
      <thead><tr><th>No Surat</th><th>Tanggal</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($sickList as $row): ?>
          <tr>
            <td><?= e($row['letter_no']) ?></td>
            <td><?= e($row['created_at']) ?></td>
            <td><a class="btn small" href="<?= e(url('/print_sick_letter.php?id='.(int)$row['id'])) ?>" target="_blank">Print</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$sickList): ?><tr><td colspan="3" class="muted">Belum ada surat sakit.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
