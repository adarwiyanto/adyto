<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin','dokter','perawat']); // sekretariat tidak boleh membuat rujukan
$u = auth_user();
$settings = get_settings();
csrf_validate();

$visitId = (int)($_GET['visit_id'] ?? ($_POST['visit_id'] ?? 0));
$action = $_POST['action'] ?? '';

// ===== Rekap filter (dipakai saat visit_id kosong / sidebar) =====
$range = $_GET['range'] ?? 'today';
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

function ref_range_dates(string $range, string $start, string $end): array {
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

function next_ref_no(): string {
  $prefix = 'RJ' . date('Ymd');
  $row = db()->query("SELECT referral_no FROM referrals WHERE referral_no LIKE '{$prefix}%' ORDER BY referral_no DESC LIMIT 1")->fetch();
  $n = 1;
  if ($row && preg_match('/^RJ\d{8}(\d{4})$/', $row['referral_no'], $m)) $n = (int)$m[1] + 1;
  return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

// ===== Load visit bila ada visit_id =====
$visit = null;
if ($visitId > 0) {
  $st = db()->prepare("SELECT v.*, p.mrn, p.full_name, p.dob, p.gender, p.address,
                              ud.full_name AS doctor_name, v.doctor_id
                       FROM visits v
                       JOIN patients p ON p.id=v.patient_id
                       LEFT JOIN users ud ON ud.id=v.doctor_id
                       WHERE v.id=?");
  $st->execute([$visitId]);
  $visit = $st->fetch();
}

// ===== Create referral (mode lama: harus dari visit) =====
if ($action === 'create_ref') {
  if (!$visit) {
    flash_set('err','Rujukan harus dibuat dari kunjungan (visit_id tidak valid).');
    redirect('/referrals.php');
  }

  $toDoctor = trim($_POST['referred_to_doctor'] ?? '');
  $toSpec   = trim($_POST['referred_to_specialty'] ?? '');
  $diag     = trim($_POST['diagnosis'] ?? '');

  if ($toDoctor === '' || $toSpec === '' || $diag === '') {
    flash_set('err','Tujuan dokter, spesialis, dan diagnosa wajib diisi.');
    redirect('/referrals.php?visit_id=' . $visitId);
  }

  $refNo = next_ref_no();
  db_exec("INSERT INTO referrals(visit_id, patient_id, sender_doctor_id, referral_no, referred_to_doctor, referred_to_specialty, diagnosis, created_at)
           VALUES(?,?,?,?,?,?,?,?)",
    [$visitId, (int)$visit['patient_id'], (int)$visit['doctor_id'], $refNo, $toDoctor, $toSpec, $diag, now_dt()]
  );

  flash_set('ok','Rujukan dibuat. No: ' . $refNo);
  redirect('/referrals.php?visit_id=' . $visitId);
}

// ===== List referrals per visit (mode lama) =====
$refList = [];
if ($visitId > 0) {
  $st = db()->prepare("SELECT r.*, u.full_name AS sender_name
                       FROM referrals r
                       LEFT JOIN users u ON u.id=r.sender_doctor_id
                       WHERE r.visit_id=? ORDER BY r.created_at DESC LIMIT 50");
  $st->execute([$visitId]);
  $refList = $st->fetchAll();
}

$title = "Rujukan";
require __DIR__ . '/app/views/partials/header.php';
?>

<div class="card">
  <div class="h1">Rujukan</div>

  <?php if ($visit): ?>
    <div class="muted">Kunjungan: <?= e($visit['visit_no']) ?> | Pasien: <?= e($visit['mrn'].' - '.$visit['full_name']) ?></div>
  <?php else: ?>
    <div class="muted">Rekap rujukan berdasarkan rentang waktu.</div>
  <?php endif; ?>
</div>

<?php if (!$visit): ?>
  <?php
    list($d1, $d2) = ref_range_dates($range, $start, $end);
    $dt1 = $d1 . ' 00:00:00';
    $dt2 = $d2 . ' 23:59:59';

    $st = db()->prepare("
      SELECT r.*, p.mrn, p.full_name, u.full_name AS sender_name
      FROM referrals r
      JOIN patients p ON p.id=r.patient_id
      LEFT JOIN users u ON u.id=r.sender_doctor_id
      WHERE r.created_at BETWEEN ? AND ?
      ORDER BY r.created_at DESC
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
    <div class="h1" style="font-size:16px">Daftar Rujukan</div>
    <table class="table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No</th>
          <th>Pasien</th>
          <th>Pengirim</th>
          <th>Tujuan</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['created_at']) ?></td>
            <td><?= e($r['referral_no']) ?></td>
            <td><?= e(($r['mrn'] ?? '').' - '.($r['full_name'] ?? '')) ?></td>
            <td><?= e($r['sender_name'] ?? '-') ?></td>
            <td><?= e($r['referred_to_doctor'].' / '.$r['referred_to_specialty']) ?></td>
            <td>
              <a class="btn small" href="<?= e(url('/print_referral.php?id='.(int)$r['id'])) ?>" target="_blank">Print</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="muted">Tidak ada data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php require __DIR__ . '/app/views/partials/footer.php'; ?>
  <?php exit; ?>
<?php endif; ?>

<?php if ($visit): ?>
  <div class="card">
    <div class="h1" style="font-size:16px">Form Rujukan</div>
    <form method="post" class="grid" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_ref">
      <input type="hidden" name="visit_id" value="<?= (int)$visitId ?>">

      <div class="col-6">
        <div class="label">Tujuan Rujuk: Nama Dokter</div>
        <input class="input" name="referred_to_doctor" placeholder="dr. ...">
      </div>
      <div class="col-6">
        <div class="label">Spesialis</div>
        <input class="input" name="referred_to_specialty" placeholder="Sp. ... / fasilitas ...">
      </div>

      <div class="col-6">
        <div class="label">Nama Pasien</div>
        <input class="input" value="<?= e($visit['full_name']) ?>" readonly>
      </div>
      <div class="col-6">
        <div class="label">Pengirim (Dokter yang menangani)</div>
        <input class="input" value="<?= e($visit['doctor_name'] ?? '-') ?>" readonly>
      </div>

      <div class="col-12">
        <div class="label">Diagnosa</div>
        <textarea class="input" name="diagnosis" placeholder="Diagnosa / alasan rujuk"></textarea>
      </div>

      <div class="col-12" style="display:flex;justify-content:flex-end">
        <button class="btn" type="submit">Simpan Rujukan</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Riwayat Rujukan</div>
    <table class="table">
      <thead>
        <tr>
          <th>No Rujukan</th>
          <th>Tanggal</th>
          <th>Tujuan</th>
          <th>Spesialis</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($refList as $r): ?>
          <tr>
            <td><?= e($r['referral_no']) ?></td>
            <td><?= e($r['created_at']) ?></td>
            <td><?= e($r['referred_to_doctor']) ?></td>
            <td><?= e($r['referred_to_specialty']) ?></td>
            <td>
              <a class="btn small" href="<?= e(url('/print_referral.php?id='.(int)$r['id'])) ?>" target="_blank">Print</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$refList): ?><tr><td colspan="5" class="muted">Belum ada rujukan.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
