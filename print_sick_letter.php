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
$st = db()->prepare("SELECT s.*, v.visit_no, v.visit_date,
                            p.mrn, p.full_name, p.dob, p.gender, p.address,
                            u.full_name AS doctor_name, u.signature_path AS doctor_signature_path
                     FROM sick_letters s
                     JOIN visits v ON v.id=s.visit_id
                     JOIN patients p ON p.id=s.patient_id
                     LEFT JOIN users u ON u.id=s.doctor_id
                     WHERE s.id=?");
$st->execute([$id]);
$s = $st->fetch();
if (!$s) { http_response_code(404); echo "Not found"; exit; }

// Public verification token + QR
$pd = public_document_get_or_create('sick_letter', (int)$s['id'], (string)$s['letter_no']);
$verifyUrl = public_document_verify_url((string)$pd['token']);
$qrImg = public_document_qr_image_url($verifyUrl, $settings);

$clinicName = $settings['clinic_name'] ?? ($settings['brand_title'] ?? 'Praktek dr. Agus');
$clinicAddr = $settings['clinic_address'] ?? '';
$clinicSip  = $settings['clinic_sip'] ?? '';
$logo = $settings['logo_path'] ?? '';

$sig_global = $settings['signature_path'] ?? '';
$sig_doctor = $s['doctor_signature_path'] ?? '';
$sig = $sig_doctor ?: $sig_global;

$age = age_from_dob($s['dob']);
$days = null;
try {
  $d1 = new DateTime($s['start_date']);
  $d2 = new DateTime($s['end_date']);
  $days = $d1->diff($d2)->days + 1;
} catch (Throwable $e) {
  $days = null;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Surat Sakit - <?= e($s['letter_no']) ?></title>
  <link rel="stylesheet" href="<?= e(url('/public/assets/css/theme.css')) ?>">
  <style>
    body{background:#fff;color:#000}
    .paper{max-width:820px;margin:0 auto;padding:20px}
    .kop{display:flex;gap:12px;align-items:center;border-bottom:1px solid #ddd;padding-bottom:12px;margin-bottom:12px}
    .kop img{width:64px;height:64px;object-fit:contain}
    .kop .t1{font-size:18px;font-weight:800}
    .kop .t2{font-size:12px;color:#333}
    .label2{font-size:12px;color:#333;font-weight:700;margin:10px 0 4px}
    pre{white-space:pre-wrap;font-family:inherit;margin:0}
    .sign{margin-top:24px;display:flex;justify-content:flex-end}
    .signbox{text-align:center}
    .signbox img{width:180px;height:auto}
  </style>
</head>
<body onload="window.print()">
  <button class="btn back-button back-floating no-print" type="button" onclick="goBack('<?= e(url('/sick_letters.php')) ?>')" aria-label="Kembali">←</button>
  <div class="paper">
    <div class="kop">
      <?php if ($logo): ?><img src="<?= e(url($logo)) ?>" alt="Logo"><?php endif; ?>
      <div>
        <div class="t1"><?= e($clinicName) ?></div>
        <?php if ($clinicAddr): ?><div class="t2"><?= e($clinicAddr) ?></div><?php endif; ?>
        <?php if ($clinicSip): ?><div class="t2">SIP: <?= e($clinicSip) ?></div><?php endif; ?>
      </div>
    </div>

    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
      <div>Surat Sakit No: <b><?= e($s['letter_no']) ?></b></div>
      <div>Tanggal: <?= e($s['created_at']) ?></div>
    </div>

    <div class="label2">Identitas Pasien</div>
    <div>MRN: <?= e($s['mrn']) ?> | Nama: <?= e($s['full_name']) ?> | Usia: <?= e((string)($age ?? '-')) ?> | JK: <?= e($s['gender']) ?></div>
    <div>Alamat: <?= e($s['address'] ?? '') ?></div>
    <div>No Kunjungan: <?= e($s['visit_no']) ?> | Tanggal Kunjungan: <?= e($s['visit_date']) ?></div>

    <div class="label2">Keterangan</div>
    <div>Diberikan surat keterangan sakit untuk istirahat mulai tanggal <b><?= e($s['start_date']) ?></b>
      sampai tanggal <b><?= e($s['end_date']) ?></b>
      <?php if ($days !== null): ?>(<?= e((string)$days) ?> hari)<?php endif; ?>.</div>

    <?php if (!empty($s['diagnosis'])): ?>
      <div class="label2">Diagnosa</div>
      <pre><?= e($s['diagnosis']) ?></pre>
    <?php endif; ?>

    <?php if (!empty($s['notes'])): ?>
      <div class="label2">Catatan</div>
      <pre><?= e($s['notes']) ?></pre>
    <?php endif; ?>



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
      <div class="signbox">
        <?php if ($sig): ?><img src="<?= e(url($sig)) ?>" alt="Tanda tangan"><?php endif; ?>
        <div style="margin-top:6px"><?= e($s['doctor_name'] ?? $u['full_name']) ?></div>
      </div>
    </div>
  </div>
  <script src="<?= e(url('/public/assets/js/app.js')) ?>"></script>
</body>
</html>
