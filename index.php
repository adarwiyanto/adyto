<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$u = auth_user();
$settings = get_settings();

$cntPatients = db()->query("SELECT COUNT(*) c FROM patients")->fetch()['c'] ?? 0;
$cntVisits = db()->query("SELECT COUNT(*) c FROM visits")->fetch()['c'] ?? 0;
$cntRx = db()->query("SELECT COUNT(*) c FROM prescriptions")->fetch()['c'] ?? 0;

$title = "Dashboard";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Dashboard</div>
  <div class="muted">Ringkasan cepat. (Biar cepat juga, jangan dibuka pas jam puncak semua orang scrolling.)</div>
  <div class="grid" style="margin-top:12px">
    <div class="col-4 card">
      <div class="muted">Pasien</div>
      <div style="font-size:28px;font-weight:800"><?= e((string)$cntPatients) ?></div>
    </div>
    <div class="col-4 card">
      <div class="muted">Kunjungan</div>
      <div style="font-size:28px;font-weight:800"><?= e((string)$cntVisits) ?></div>
    </div>
    <div class="col-4 card">
      <div class="muted">Resep</div>
      <div style="font-size:28px;font-weight:800"><?= e((string)$cntRx) ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="h1" style="font-size:16px">Shortcut</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn" href="<?= e(url('/patients.php')) ?>">+ Pasien</a>
    <a class="btn secondary" href="<?= e(url('/visits.php?new=1')) ?>">+ Kunjungan</a>
    <a class="btn secondary" href="<?= e(url('/backup.php')) ?>">Backup</a>
  </div>
</div>
<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
