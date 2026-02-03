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

// ===== Rekap filter (dipakai saat visit_id kosong / sidebar) =====
$range = $_GET['range'] ?? 'today';
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

function rx_range_dates(string $range, string $start, string $end): array {
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
  // custom
  if ($range === 'custom' && $start && $end) return [$start, $end];
  return [$today, $today];
}

function next_rx_no(): string {
  $prefix = 'RX' . date('Ymd');
  $row = db()->query("SELECT rx_no FROM prescriptions WHERE rx_no LIKE '{$prefix}%' ORDER BY rx_no DESC LIMIT 1")->fetch();
  $n = 1;
  if ($row && preg_match('/^RX\d{8}(\d{4})$/', $row['rx_no'], $m)) $n = (int)$m[1] + 1;
  return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

// ===== Load visit bila ada visit_id =====
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

// ===== Create prescription (mode lama: harus dari visit) =====
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

// ===== List prescriptions per visit (mode lama) =====
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
    <div class="muted">Rekap resep berdasarkan rentang waktu.</div>
  <?php endif; ?>
</div>

<?php if (!$visit): ?>
  <?php
    list($d1, $d2) = rx_range_dates($range, $start, $end);
    $dt1 = $d1 . ' 00:00:00';
    $dt2 = $d2 . ' 23:59:59';

    $st = db()->prepare("
      SELECT rx.*, v.visit_no, p.mrn, p.full_name
      FROM prescriptions rx
      JOIN visits v ON v.id = rx.visit_id
      JOIN patients p ON p.id = v.patient_id
      WHERE rx.created_at BETWEEN ? AND ?
      ORDER BY rx.created_at DESC
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
    <div class="h1" style="font-size:16px">Daftar Resep</div>
    <table class="table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No Resep</th>
          <th>Pasien</th>
          <th>No Kunjungan</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $rx): ?>
          <tr>
            <td><?= e($rx['created_at']) ?></td>
            <td><?= e($rx['rx_no']) ?></td>
            <td><?= e(($rx['mrn'] ?? '').' - '.($rx['full_name'] ?? '')) ?></td>
            <td><?= e($rx['visit_no'] ?? '-') ?></td>
            <td>
              <a class="btn small" href="<?= e(url('/print_rx.php?id='.(int)$rx['id'])) ?>" target="_blank">Print Resep</a>
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
