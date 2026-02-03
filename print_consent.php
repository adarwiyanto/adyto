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
$st = db()->prepare("SELECT c.*, v.visit_no, v.visit_date,
                            p.mrn, p.full_name, p.dob, p.gender, p.address,
                            u.full_name AS doctor_name, u.signature_path AS doctor_signature_path
                     FROM consents c
                     JOIN visits v ON v.id=c.visit_id
                     JOIN patients p ON p.id=c.patient_id
                     LEFT JOIN users u ON u.id=c.doctor_id
                     WHERE c.id=?");
$st->execute([$id]);
$c = $st->fetch();
if (!$c) { http_response_code(404); echo "Not found"; exit; }


// Public verification token + QR
$pd = public_document_get_or_create('consent', (int)$c['id'], (string)$c['consent_no']);
$verifyUrl = public_document_verify_url((string)$pd['token']);
$qrImg = public_document_qr_image_url($verifyUrl, $settings);
$clinicName = $settings['clinic_name'] ?? ($settings['brand_title'] ?? 'Praktek dr. Agus');
$clinicAddr = $settings['clinic_address'] ?? '';
$clinicSip  = $settings['clinic_sip'] ?? '';
$logo = $settings['logo_path'] ?? '';

$sig_global = $settings['signature_path'] ?? '';
$sig_doctor = $c['doctor_signature_path'] ?? '';
$sig_patient = $c['signature_path'] ?? '';
$sig_doc = $sig_doctor ?: $sig_global;

$age = age_from_dob($c['dob']);
$signerName = $c['signer_name'] ?: ($c['full_name'] ?? '');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Informed Consent - <?= e($c['consent_no']) ?></title>
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
    .sign-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:24px}
    .signbox{text-align:center}
    .signbox img{width:180px;height:auto}
  </style>
</head>
<body onload="window.print()">
  <button class="btn back-button back-floating no-print" type="button" onclick="goBack('<?= e(url('/consents.php')) ?>')" aria-label="Kembali">←</button>
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
      <div>Consent No: <b><?= e($c['consent_no']) ?></b></div>
      <div>Tanggal: <?= e($c['created_at']) ?></div>
    </div>

    <div class="label2">Identitas Pasien</div>
    <div>MRN: <?= e($c['mrn']) ?> | Nama: <?= e($c['full_name']) ?> | Usia: <?= e((string)($age ?? '-')) ?> | JK: <?= e($c['gender']) ?></div>
    <div>Alamat: <?= e($c['address'] ?? '') ?></div>
    <div>No Kunjungan: <?= e($c['visit_no']) ?> | Tanggal Kunjungan: <?= e($c['visit_date']) ?></div>

    <div class="label2">Tindakan</div>
    <div><?= e($c['procedure_name']) ?></div>

    <?php if (!empty($c['diagnosis'])): ?>
      <div class="label2">Diagnosa</div>
      <pre><?= e($c['diagnosis']) ?></pre>
    <?php endif; ?>

    <?php if (!empty($c['risks'])): ?>
      <div class="label2">Risiko</div>
      <pre><?= e($c['risks']) ?></pre>
    <?php endif; ?>

    <?php if (!empty($c['benefits'])): ?>
      <div class="label2">Manfaat</div>
      <pre><?= e($c['benefits']) ?></pre>
    <?php endif; ?>

    <?php if (!empty($c['alternatives'])): ?>
      <div class="label2">Alternatif</div>
      <pre><?= e($c['alternatives']) ?></pre>
    <?php endif; ?>

    <?php if (!empty($c['notes'])): ?>
      <div class="label2">Catatan</div>
      <pre><?= e($c['notes']) ?></pre>
    <?php endif; ?>

    <div class="label2">Pernyataan</div>
    <div>Saya menyatakan telah menerima penjelasan mengenai tindakan di atas, memahami manfaat, risiko, serta alternatif, dan menyetujui tindakan dilakukan.</div>

    <div class="sign-grid">
      <div class="signbox">
        <div class="label2">Pasien/Keluarga</div>
        <?php if ($sig_patient): ?><img src="<?= e(url($sig_patient)) ?>" alt="Tanda tangan pasien"><?php endif; ?>
        <div style="margin-top:6px"><?= e($signerName) ?></div>
        <?php if (!empty($c['signer_relation'])): ?>
          <div style="font-size:12px;color:#333"><?= e($c['signer_relation']) ?></div>
        <?php endif; ?>
      </div>
      <div class="signbox">
        <div class="label2">Dokter</div>
        <?php if ($sig_doc): ?><img src="<?= e(url($sig_doc)) ?>" alt="Tanda tangan dokter"><?php endif; ?>
        <div style="margin-top:6px"><?= e($c['doctor_name'] ?? $u['full_name']) ?></div>
      </div>
    </div>
  </div>

    <script src="<?= e(url('/public/assets/js/app.js')) ?>"></script>
</body>
</html>
