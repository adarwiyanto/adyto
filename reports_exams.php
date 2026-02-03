<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin','dokter','perawat']);
$u = auth_user();
$settings = get_settings();
csrf_validate();

$mode  = $_GET['mode'] ?? 'month'; // month|year
$year  = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? (int)date('m'));
if ($year < 2000 || $year > 2100) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('m');

function month_start_end(int $year, int $month): array {
  $start = sprintf('%04d-%02d-01', $year, $month);
  $end = date('Y-m-d', strtotime($start . ' +1 month -1 day'));
  return [$start, $end];
}

function year_start_end(int $year): array {
  return [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)];
}

function fetch_counts_between(string $startDate, string $endDate): array {
  $st = db()->prepare("
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN (is_usg IS NULL OR is_usg=0) THEN 1 ELSE 0 END) AS regular_count,
      SUM(CASE WHEN (is_usg=1 AND (usg_type='diagnostic' OR usg_type IS NULL)) THEN 1 ELSE 0 END) AS usg_diagnostic_count,
      SUM(CASE WHEN (is_usg=1 AND usg_type='intervention') THEN 1 ELSE 0 END) AS usg_intervention_count
    FROM visits
    WHERE DATE(visit_date) BETWEEN ? AND ?
  ");
  $st->execute([$startDate, $endDate]);
  $row = $st->fetch() ?: [];
  return [
    'total' => (int)($row['total'] ?? 0),
    'regular' => (int)($row['regular_count'] ?? 0),
    'usg_diagnostic' => (int)($row['usg_diagnostic_count'] ?? 0),
    'usg_intervention' => (int)($row['usg_intervention_count'] ?? 0),
  ];
}

$title = 'Rekapan Pemeriksaan';
require __DIR__ . '/app/views/partials/header.php';

$months = [
  1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
  7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
];

?>
<div class="card">
  <div class="h1">Rekapan Pemeriksaan</div>
  <div class="muted">Kategori: Pemeriksaan biasa, USG diagnostik, USG intervensi. Data lama tetap aman; bila kunjungan lama memiliki Laporan USG, sistem menganggapnya USG diagnostik.</div>
</div>

<div class="card">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div>
      <div class="label">Mode</div>
      <select class="input" name="mode">
        <option value="month" <?= $mode==='month'?'selected':'' ?>>Bulanan</option>
        <option value="year" <?= $mode==='year'?'selected':'' ?>>Tahunan</option>
      </select>
    </div>
    <div>
      <div class="label">Tahun</div>
      <input class="input" type="number" name="year" value="<?= (int)$year ?>" min="2000" max="2100" style="width:120px">
    </div>
    <div id="monthWrap" style="<?= $mode==='year'?'display:none':'' ?>">
      <div class="label">Bulan</div>
      <select class="input" name="month">
        <?php foreach ($months as $n=>$label): ?>
          <option value="<?= (int)$n ?>" <?= $month===(int)$n?'selected':'' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn secondary" type="submit">Tampilkan</button>
  </form>
</div>

<?php if ($mode === 'month'): ?>
  <?php
    [$d1, $d2] = month_start_end($year, $month);
    $counts = fetch_counts_between($d1, $d2);
  ?>
  <div class="card">
    <div class="h1" style="font-size:16px">Rekap Bulan <?= e($months[$month] ?? (string)$month) ?> <?= (int)$year ?></div>
    <div class="muted">Periode: <?= e($d1) ?> s/d <?= e($d2) ?></div>

    <table class="table" style="margin-top:10px">
      <thead>
        <tr><th>Kategori</th><th style="text-align:right">Jumlah</th></tr>
      </thead>
      <tbody>
        <tr><td>Pemeriksaan biasa</td><td style="text-align:right"><?= (int)$counts['regular'] ?></td></tr>
        <tr><td>USG diagnostik</td><td style="text-align:right"><?= (int)$counts['usg_diagnostic'] ?></td></tr>
        <tr><td>USG intervensi</td><td style="text-align:right"><?= (int)$counts['usg_intervention'] ?></td></tr>
        <tr><td><strong>Total</strong></td><td style="text-align:right"><strong><?= (int)$counts['total'] ?></strong></td></tr>
      </tbody>
    </table>
  </div>

<?php else: ?>
  <?php
    [$y1, $y2] = year_start_end($year);
    // Yearly table per month
    $rows = [];
    for ($m=1; $m<=12; $m++) {
      [$a, $b] = month_start_end($year, $m);
      $rows[$m] = fetch_counts_between($a, $b);
      $rows[$m]['start'] = $a;
      $rows[$m]['end'] = $b;
    }
    $yearTotal = fetch_counts_between($y1, $y2);
  ?>
  <div class="card">
    <div class="h1" style="font-size:16px">Rekap Tahunan <?= (int)$year ?></div>
    <div class="muted">Periode: <?= e($y1) ?> s/d <?= e($y2) ?></div>

    <table class="table" style="margin-top:10px">
      <thead>
        <tr>
          <th>Bulan</th>
          <th style="text-align:right">Biasa</th>
          <th style="text-align:right">USG diag</th>
          <th style="text-align:right">USG interv</th>
          <th style="text-align:right">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $m=>$r): ?>
          <tr>
            <td><?= e($months[$m] ?? (string)$m) ?></td>
            <td style="text-align:right"><?= (int)$r['regular'] ?></td>
            <td style="text-align:right"><?= (int)$r['usg_diagnostic'] ?></td>
            <td style="text-align:right"><?= (int)$r['usg_intervention'] ?></td>
            <td style="text-align:right"><?= (int)$r['total'] ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td><strong>Total <?= (int)$year ?></strong></td>
          <td style="text-align:right"><strong><?= (int)$yearTotal['regular'] ?></strong></td>
          <td style="text-align:right"><strong><?= (int)$yearTotal['usg_diagnostic'] ?></strong></td>
          <td style="text-align:right"><strong><?= (int)$yearTotal['usg_intervention'] ?></strong></td>
          <td style="text-align:right"><strong><?= (int)$yearTotal['total'] ?></strong></td>
        </tr>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<script>
  (function(){
    const mode = document.querySelector('select[name="mode"]');
    const monthWrap = document.getElementById('monthWrap');
    if (!mode || !monthWrap) return;
    function sync(){
      monthWrap.style.display = mode.value === 'year' ? 'none' : '';
    }
    mode.addEventListener('change', sync);
    sync();
  })();
</script>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
