<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';
require_once __DIR__ . '/app/public_documents.php';

auth_require();
$u = auth_user();
$settings = get_settings();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("
  SELECT rx.*, 
         v.visit_no, v.visit_date, v.signature_path,
         p.mrn, p.full_name, p.dob, p.gender,
         u.full_name AS doctor_name,
         u.signature_path AS doctor_signature_path
  FROM prescriptions rx
  JOIN visits v ON v.id = rx.visit_id
  JOIN patients p ON p.id = v.patient_id
  LEFT JOIN users u ON u.id = v.doctor_id
  WHERE rx.id = ?
");
$st->execute([$id]);
$rx = $st->fetch();
if (!$rx) {
  http_response_code(404);
  echo "Not found";
  exit;
}


// Public verification token + QR
$pd = public_document_get_or_create('prescription', (int)$rx['id'], (string)$rx['rx_no']);
$verifyUrl = public_document_verify_url((string)$pd['token']);
$qrImg = public_document_qr_image_url($verifyUrl, $settings);
$clinicName = $settings['clinic_name'] ?? 'Praktek dr. Agus';
$clinicSip  = $settings['clinic_sip'] ?? '';

$sig_global = $settings['signature_path'] ?? '';
$sig_doctor = $rx['doctor_signature_path'] ?? '';
$sig_visit  = $rx['signature_path'] ?? '';
$sig = $sig_visit ?: ($sig_doctor ?: $sig_global);

$age = age_from_dob($rx['dob']);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Resep <?= e($rx['rx_no']) ?></title>
<link rel="stylesheet" href="<?= e(url('/public/assets/css/theme.css')) ?>">
<style>
/* ====== SET UKURAN KERTAS RESEP ====== */
@page {
  size: 10cm 15cm;
  margin: 6mm;
}

body {
  margin: 0;
  padding: 0;
  background: #fff;
  color: #000;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 11px;
}

/* ====== KERTAS ====== */
.paper {
  width: 100%;
  height: 100%;
}

/* ====== HEADER ====== */
.header {
  border-bottom: 1px solid #000;
  padding-bottom: 4px;
  margin-bottom: 6px;
}

.header .clinic {
  font-weight: bold;
  font-size: 13px;
}

.header .sip {
  font-size: 10px;
}

/* ====== INFO PASIEN ====== */
.info {
  margin-bottom: 6px;
}

.info div {
  line-height: 1.3;
}

/* ====== ISI RESEP ====== */
.content {
  margin-top: 6px;
  min-height: 70px;
  white-space: pre-wrap;
  font-size: 12px;
}

/* ====== TTD ====== */
.sign {
  margin-top: 10px;
  text-align: right;
}

.sign img {
  max-width: 90px;
  max-height: 40px;
}

.sign .doctor {
  margin-top: 2px;
  font-size: 10px;
}
</style>
</head>

<body onload="window.print()">
  <div class="paper">
    <button class="btn back-button back-floating no-print" type="button" onclick="goBack('<?= e(url('/prescriptions.php')) ?>')" aria-label="Kembali">←</button>
  <div class="paper">

    <div class="header">
      <div class="clinic"><?= e($clinicName) ?></div>
      <?php if ($clinicSip): ?>
        <div class="sip">SIP: <?= e($clinicSip) ?></div>
      <?php endif; ?>
    </div>

    <div class="info">
      <div>No Resep: <b><?= e($rx['rx_no']) ?></b></div>
      <div>Tanggal: <?= e(substr($rx['created_at'], 0, 10)) ?></div>
      <div>MRN: <?= e($rx['mrn']) ?></div>
      <div>Nama: <?= e($rx['full_name']) ?></div>
      <div>Usia: <?= e((string)($age ?? '-')) ?> th | JK: <?= e($rx['gender']) ?></div>
    </div>

    <div class="content">
<?= e($rx['content']) ?>
    </div>



    <div style="margin-top:16px;display:flex;justify-content:space-between;gap:14px;align-items:flex-end;flex-wrap:wrap">
      <div style="font-size:11px;max-width:560px">
        <div style="font-weight:800">Verifikasi dokumen</div>
        <div>Scan QR atau buka link berikut untuk verifikasi keaslian dokumen:</div>
        <div style="word-break:break-all"><a href="<?= e($verifyUrl) ?>" target="_blank" rel="noopener"><?= e($verifyUrl) ?></a></div>
      </div>
      <div>
        <img src="<?= e($qrImg) ?>" alt="QR Verifikasi" style="width:110px;height:110px">
      </div>
    </div>

    <div class="sign">
      <?php if ($sig): ?>
        <img src="<?= e(url($sig)) ?>" alt="TTD">
      <?php endif; ?>
      <div class="doctor">
        <?= e($rx['doctor_name'] ?? $u['full_name']) ?>
      </div>
    </div>

  </div>
  <script src="<?= e(url('/public/assets/js/app.js')) ?>"></script>
</body>
</html>