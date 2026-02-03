<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$u = auth_user();
$settings = get_settings();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo 'Bad request';
  exit;
}

$st = db()->prepare("SELECT id, mrn, full_name, dob, gender, address, created_at FROM patients WHERE id=? LIMIT 1");
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
  http_response_code(404);
  echo 'Not found';
  exit;
}

$title = 'Detail Pasien';
require __DIR__ . '/app/views/partials/header.php';
?>

<div class="card">
  <div class="h1">Detail Pasien</div>
  <div class="muted">Halaman ini sengaja simpel: cocok untuk target QR (cepat kebuka).</div>
</div>

<div class="card">
  <div class="grid">
    <div class="col-6">
      <div class="label">MRN</div>
      <div style="font-weight:800;font-size:18px"><?= e($p['mrn']) ?></div>
    </div>
    <div class="col-6">
      <div class="label">Jenis Kelamin</div>
      <div><?= e($p['gender']) ?></div>
    </div>

    <div class="col-12">
      <div class="label">Nama</div>
      <div style="font-weight:800;font-size:18px"><?= e($p['full_name']) ?></div>
    </div>

    <div class="col-6">
      <div class="label">Tanggal Lahir</div>
      <div><?= e(dob_to_ddmmyyyy($p['dob'])) ?></div>
      <div class="muted" style="margin-top:6px">Usia: <?= e((string)(age_from_dob($p['dob']) ?? '-')) ?></div>
    </div>

    <div class="col-6">
      <div class="label">Tgl dibuat</div>
      <div><?= e($p['created_at'] ?? '') ?></div>
    </div>

    <div class="col-12">
      <div class="label">Alamat</div>
      <div><?= e($p['address'] ?? '') ?></div>
    </div>

    <div class="col-12" style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn secondary" href="<?= e(url('/patient_edit.php?id='.(int)$p['id'])) ?>">Edit</a>
      <a class="btn secondary" href="<?= e(url('/visits.php?patient_id='.(int)$p['id'])) ?>">Kunjungan</a>
      <a class="btn secondary" href="<?= e(url('/patient_card_pdf.php?id='.(int)$p['id'])) ?>" target="_blank" rel="noopener">Kartu (PDF)</a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
