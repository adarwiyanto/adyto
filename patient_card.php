<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$settings = get_settings();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo "Bad request"; exit; }

$st = db()->prepare("SELECT id, mrn, full_name, dob, gender, address FROM patients WHERE id=? LIMIT 1");
$st->execute([$id]);
$p = $st->fetch();
if (!$p) { http_response_code(404); echo "Not found"; exit; }

// URL yang akan dibuka saat QR discan (arah ke riwayat kunjungan pasien)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $scheme . '://' . $host . base_path();
$openUrl = $base . '/visits.php?patient_id=' . (int)$p['id'];

// QR via Google Chart (ringan, tanpa library tambahan)
$qr = 'https://chart.googleapis.com/chart?chs=180x180&cht=qr&chld=L|0&chl=' . rawurlencode($openUrl);

$clinicName = $settings['clinic_name'] ?? ($settings['brand_title'] ?? 'Adena Medical System');
$clinicAddr = $settings['clinic_address'] ?? '';
$logo = $settings['logo_path'] ?? '';

$dob = $p['dob'] ? date('d/m/Y', strtotime((string)$p['dob'])) : '';
$age = age_from_dob($p['dob']);
$auto = (int)($_GET['auto'] ?? 0);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kartu Berobat - <?= e($p['mrn']) ?></title>
  <link rel="stylesheet" href="<?= e(url('/public/assets/css/theme.css')) ?>">
  <style>
    @page { size: 85.6mm 53.98mm; margin: 0; }
    html, body { margin: 0; padding: 0; background: #fff; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .wrap { width: 85.6mm; height: 53.98mm; padding: 4mm; box-sizing: border-box; }
    .card { width: 100%; height: 100%; border: 1px solid #e5e5e5; border-radius: 10px; padding: 3.5mm; box-sizing: border-box; display: flex; gap: 3mm; }
    .left { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
    .top { display:flex; gap: 2.5mm; align-items: center; }
    .logo { width: 12mm; height: 12mm; border-radius: 8px; border: 1px solid #eee; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .logo img { width:100%; height:100%; object-fit: contain; }
    .clinic { min-width: 0; }
    .clinic .name { font-weight: 800; font-size: 12px; line-height: 1.1; }
    .clinic .addr { font-size: 9px; color: #444; line-height: 1.15; margin-top: 1mm; }

    .meta { margin-top: 2.5mm; display: grid; grid-template-columns: 18mm 1fr; row-gap: 1.2mm; column-gap: 2mm; font-size: 10px; }
    .k { color:#333; font-weight:700; }
    .v { color:#000; overflow:hidden; text-overflow: ellipsis; white-space: nowrap; }
    .mrn { font-weight: 900; letter-spacing: .5px; }

    .bottom { margin-top: auto; display:flex; justify-content: space-between; align-items: flex-end; gap: 2mm; }
    .hint { font-size: 8.5px; color: #555; line-height: 1.2; }

    .right { width: 22mm; display:flex; flex-direction: column; align-items: center; justify-content: space-between; }
    .qr { width: 22mm; height: 22mm; border: 1px solid #eee; border-radius: 8px; overflow: hidden; }
    .qr img { width: 100%; height: 100%; display:block; }
    .qrlabel { font-size: 8px; color: #444; text-align:center; }

    .noprint { padding: 10px; }
    @media print {
      .noprint { display: none !important; }
    }
  </style>
</head>
<body <?= $auto ? 'onload="window.print()"' : '' ?>>

  <div class="noprint" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <button class="btn back-button" type="button" onclick="goBack('<?= e(url('/patients.php')) ?>')" aria-label="Kembali">← Kembali</button>
    <button class="btn" onclick="window.print()">Cetak</button>
    <div class="muted">Tips: di dialog print pilih "Actual size" / "100%" agar ukuran KTP pas.</div>
  </div>

  <div class="wrap">
    <div class="card">
      <div class="left">
        <div class="top">
          <div class="logo">
            <?php if ($logo): ?>
              <img src="<?= e(url($logo)) ?>" alt="Logo">
            <?php endif; ?>
          </div>
          <div class="clinic">
            <div class="name"><?= e($clinicName) ?></div>
            <?php if ($clinicAddr): ?><div class="addr"><?= e($clinicAddr) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="meta">
          <div class="k">MRN</div><div class="v mrn"><?= e($p['mrn']) ?></div>
          <div class="k">Nama</div><div class="v"><?= e($p['full_name']) ?></div>
          <div class="k">TTL</div><div class="v"><?= e($dob ?: '-') ?><?= $age !== null ? ' ('.$age.' th)' : '' ?></div>
          <div class="k">JK</div><div class="v"><?= e($p['gender'] ?? '-') ?></div>
          <div class="k">Alamat</div><div class="v"><?= e($p['address'] ?? '-') ?></div>
        </div>

        <div class="bottom">
          <div class="hint">
            Scan QR untuk buka data pasien (riwayat kunjungan).
          </div>
        </div>
      </div>

      <div class="right">
        <div class="qr"><img src="<?= e($qr) ?>" alt="QR"></div>
        <div class="qrlabel">SCAN</div>
      </div>
    </div>
  </div>

  <script src="<?= e(url('/public/assets/js/app.js')) ?>"></script>
</body>
</html>
