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

$mode = $_GET['mode'] ?? 'today';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

function day_start(string $ymd): string { return $ymd . ' 00:00:00'; }
function day_end(string $ymd): string { return $ymd . ' 23:59:59'; }

$rangeLabel = 'Hari ini';
$dtStart = day_start(date('Y-m-d'));
$dtEnd = day_end(date('Y-m-d'));

if ($mode === 'yesterday') {
  $ymd = date('Y-m-d', strtotime('-1 day'));
  $dtStart = day_start($ymd);
  $dtEnd = day_end($ymd);
  $rangeLabel = 'Kemarin';
} elseif ($mode === 'last7') {
  $dtStart = date('Y-m-d 00:00:00', strtotime('-6 day'));
  $dtEnd = date('Y-m-d 23:59:59');
  $rangeLabel = '7 hari terakhir';
} elseif ($mode === 'custom' && $start && $end) {
  $dtStart = day_start($start);
  $dtEnd = day_end($end);
  $rangeLabel = 'Custom: ' . $start . ' s/d ' . $end;
}

$st = db()->prepare("
  SELECT v.visit_date, v.visit_no, p.mrn, p.full_name, u.full_name AS doctor_name,
         v.id AS visit_id, p.id AS patient_id
  FROM visits v
  JOIN patients p ON p.id=v.patient_id
  LEFT JOIN users u ON u.id=v.doctor_id
  WHERE v.visit_date BETWEEN ? AND ?
  ORDER BY v.visit_date DESC
  LIMIT 500
");
$st->execute([$dtStart, $dtEnd]);
$rows = $st->fetchAll();

$title = "Jadwal";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Jadwal Pasien</div>
  <div class="muted">Rentang aktif: <b><?= e($rangeLabel) ?></b></div>
</div>

<div class="card">
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <a class="btn secondary" href="<?= e(url('/schedule.php?mode=today')) ?>">Hari ini</a>
    <a class="btn secondary" href="<?= e(url('/schedule.php?mode=yesterday')) ?>">Kemarin</a>
    <a class="btn secondary" href="<?= e(url('/schedule.php?mode=last7')) ?>">7 hari</a>

    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
      <input type="hidden" name="mode" value="custom">
      <div>
        <div class="label">Mulai</div>
        <input class="input" type="date" name="start" value="<?= e($start) ?>" required>
      </div>
      <div>
        <div class="label">Sampai</div>
        <input class="input" type="date" name="end" value="<?= e($end) ?>" required>
      </div>
      <button class="btn" type="submit">Terapkan</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="h1" style="font-size:16px">Daftar Kunjungan (maks 500)</div>
  <table class="table">
    <thead>
      <tr><th>Waktu</th><th>No Kunjungan</th><th>MRN</th><th>Nama</th><th>Dokter</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['visit_date']) ?></td>
          <td><?= e($r['visit_no']) ?></td>
          <td><?= e($r['mrn']) ?></td>
          <td><?= e($r['full_name']) ?></td>
          <td><?= e($r['doctor_name'] ?? '-') ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn small secondary" href="<?= e(url('/patient_edit.php?id='.(int)$r['patient_id'])) ?>">Edit Pasien</a>
            <?php if (!$is_sekretariat): ?>
              <a class="btn small" href="<?= e(url('/print_visit.php?id='.(int)$r['visit_id'])) ?>" target="_blank">Print</a>
            <?php else: ?>
              <span class="muted">Sekretariat: view-only</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">Tidak ada kunjungan pada rentang ini.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
