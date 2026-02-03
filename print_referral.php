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
$st = db()->prepare("SELECT r.*, v.visit_no, v.visit_date, v.signature_path,
                            p.mrn, p.full_name, p.dob, p.gender, p.address,
                            ud.full_name AS sender_name, ud.signature_path AS sender_signature_path
                     FROM referrals r
                     LEFT JOIN visits v ON v.id=r.visit_id
                     JOIN patients p ON p.id=r.patient_id
                     LEFT JOIN users ud ON ud.id=r.sender_doctor_id
                     WHERE r.id=?");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) { http_response_code(404); echo "Not found"; exit; }


// Public verification token + QR
$pd = public_document_get_or_create('referral', (int)$r['id'], (string)$r['referral_no']);
$verifyUrl = public_document_verify_url((string)$pd['token']);
$qrImg = public_document_qr_image_url($verifyUrl, $settings);
$clinicName = $settings['clinic_name'] ?? ($settings['brand_title'] ?? 'Praktek dr. Agus');
$clinicAddr = $settings['clinic_address'] ?? '';
$clinicSip  = $settings['clinic_sip'] ?? '';
$logo = $settings['logo_path'] ?? '';

$sig_global = $settings['signature_path'] ?? '';
$sig_sender = $r['sender_signature_path'] ?? '';
$sig_visit  = $r['signature_path'] ?? '';
$sig = $sig_visit ?: ($sig_sender ?: $sig_global);

$age = age_from_dob($r['dob']);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rujukan - <?= e($r['referral_no']) ?></title>
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
      <div>Rujukan No: <b><?= e($r['referral_no']) ?></b></div>
      <div>Tanggal: <?= e($r['created_at']) ?></div>
    </div>

    <div class="label2">Tujuan Rujukan</div>
    <div>Dokter: <?= e($r['referred_to_doctor']) ?></div>
    <div>Spesialis/Fasilitas: <?= e($r['referred_to_specialty']) ?></div>

    <div class="label2">Identitas Pasien</div>
    <div>MRN: <?= e($r['mrn']) ?> | Nama: <?= e($r['full_name']) ?> | Usia: <?= e((string)($age ?? '-')) ?> | JK: <?= e($r['gender']) ?></div>
    <div>Alamat: <?= e($r['address'] ?? '') ?></div>
    <?php if (!empty($r['visit_no'])): ?>
      <div>No Kunjungan: <?= e($r['visit_no']) ?> | Tanggal Kunjungan: <?= e($r['visit_date']) ?></div>
    <?php endif; ?>

    <div class="label2">Diagnosa / Alasan Rujuk</div>
    <pre><?= e($r['diagnosis']) ?></pre>



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
        <div style="margin-top:6px"><?= e($r['sender_name'] ?? '-') ?></div>
      </div>
    </div>
  </div>
</body>
</html>
