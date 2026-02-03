<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$u = auth_user();
$settings = get_settings();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT id, mrn, full_name, dob, gender, address FROM patients WHERE id=?");
$st->execute([$id]);
$p = $st->fetch();
if (!$p) { http_response_code(404); echo "Not found"; exit; }

function fmt_dob($dob): string {
  if (!$dob) return '-';
  // expect YYYY-MM-DD
  if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$dob, $m)) {
    return $m[3] . '-' . $m[2] . '-' . $m[1];
  }
  return (string)$dob;
}

$clinicName = $settings['clinic_name'] ?? ($settings['brand_title'] ?? 'Adena Medical System');
$badge = $settings['brand_badge'] ?? 'KARTU BEROBAT';
$footer = $settings['footer_text'] ?? '';
$logo = $settings['logo_path'] ?? '';

$cardCss = (string)($settings['card_css'] ?? '');
$auto = (int)($_GET['autoprint'] ?? 0);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kartu Berobat - <?= e($p['mrn']) ?></title>
  <link rel="stylesheet" href="<?= e(url('/public/assets/css/theme.css')) ?>">
  <style>
    :root{
      --card-w: 85.6mm;
      --card-h: 53.98mm;
      --accent: #22c55e; /* hijau kekinian */
      --accent2:#3b82f6; /* biru */
      --ink:#0f172a;
    }
    body{background:#0b1020; color:#e5e7eb; padding:20px;}
    .topbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;max-width:calc(var(--card-w) + 40px);margin:0 auto 14px auto}
    .topbar .btn{border-radius:999px}

    /* area kartu */
    .wrap{max-width:calc(var(--card-w) + 40px);margin:0 auto;}
    .card-ktp{
      width:var(--card-w);
      height:var(--card-h);
      background:#ffffff;
      border-radius:14px;
      overflow:hidden;
      position:relative;
      box-shadow: 0 10px 30px rgba(0,0,0,.25);
      color:var(--ink);
      border:1px solid #e5e7eb;
    }

    /* dekorasi: SVG (bukan background) -> tetap ikut print */
    .decor{position:absolute; inset:0; pointer-events:none;}
    .decor svg{position:absolute}
    .decor .tl{top:-16px; left:-18px; width:72mm; height:40mm; opacity:.22}
    .decor .br{bottom:-18px; right:-22px; width:78mm; height:44mm; opacity:.18}

    .stripe{position:absolute; left:0; top:0; bottom:0; width:8mm; background:linear-gradient(180deg,var(--accent),var(--accent2));}

    .inner{position:relative; z-index:2; padding:10mm 10mm 8mm 12mm; height:100%; display:flex; flex-direction:column;}

    .head{display:flex; align-items:center; justify-content:space-between; gap:10px}
    .brand{display:flex; align-items:center; gap:8px; min-width:0}
    .brand .logo{width:9mm;height:9mm;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;background:#fff;display:flex;align-items:center;justify-content:center}
    .brand .logo img{width:100%;height:100%;object-fit:contain}
    .brand .t{min-width:0}
    .brand .t1{font-weight:900;font-size:12px;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:48mm}
    .brand .t2{font-size:9px;color:#475569;font-weight:700;letter-spacing:.08em;text-transform:uppercase}

    .mrn{
      text-align:right;
      padding:5px 7px;
      border:1px solid #e5e7eb;
      border-radius:12px;
      background:#ffffff;
      min-width:24mm;
      max-width:28mm;
    }
    .mrn .l{font-size:8px;color:#64748b;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .mrn .v{font-size:11px;font-weight:900;line-height:1;letter-spacing:.02em;white-space:nowrap}

    .name{margin-top:7mm; font-size:16px; font-weight:900; line-height:1.1; text-transform:capitalize;}

    .grid{margin-top:3mm; display:grid; grid-template-columns: 1fr 1fr; gap:2mm 6mm; font-size:10px;}
    .row .k{font-size:9px;color:#64748b;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
    .row .v{font-weight:800;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:40mm}
    .row.full{grid-column:1 / -1}
    .row.full .v{max-width:72mm}

    .foot{margin-top:auto; display:flex; align-items:flex-end; justify-content:space-between; gap:8px}
    .note{font-size:9px;color:#475569;font-weight:700}

    /* Print to PDF */
    @page{ size: var(--card-w) var(--card-h); margin:0; }
    @media print{
      html,body{width:var(--card-w);height:var(--card-h);margin:0;padding:0;background:#fff}
      body{ -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .topbar{display:none !important;}
      .wrap{max-width:none;margin:0}
      .card-ktp{box-shadow:none;border:none;border-radius:0}
    }

    /* CSS custom dari admin */
    <?= $cardCss ?>
  </style>
</head>
<body <?= $auto ? 'onload="window.print()"' : '' ?>>
  <div class="topbar">
    <button class="btn" onclick="window.print()">Cetak / Simpan PDF</button>
    <button class="btn back-button" type="button" onclick="goBack('<?= e(url('/patients.php')) ?>')" aria-label="Kembali">← Kembali</button>
  </div>

  <div class="wrap">
    <div class="card-ktp" aria-label="Kartu Berobat">
      <div class="stripe"></div>
      <div class="decor">
        <svg class="tl" viewBox="0 0 400 220" xmlns="http://www.w3.org/2000/svg">
          <path d="M20,190 C90,60 160,30 260,60 C340,82 360,140 390,200 L390,0 L0,0 Z" fill="var(--accent)"/>
          <path d="M0,220 C80,110 160,70 260,90 C330,105 360,150 400,210 L400,220 Z" fill="var(--accent2)" opacity=".85"/>
        </svg>
        <svg class="br" viewBox="0 0 420 240" xmlns="http://www.w3.org/2000/svg">
          <path d="M420,40 C320,120 260,160 160,150 C70,140 40,190 0,240 L420,240 Z" fill="var(--accent2)"/>
          <path d="M420,0 C340,90 270,130 180,125 C90,120 70,170 0,240 L420,240 Z" fill="var(--accent)" opacity=".75"/>
        </svg>
      </div>

      <div class="inner">
        <div class="head">
          <div class="brand">
            <div class="logo">
              <?php if ($logo): ?>
                <img src="<?= e(url($logo)) ?>" alt="Logo">
              <?php else: ?>
                <div style="font-weight:900;font-size:10px;color:var(--ink)">AMS</div>
              <?php endif; ?>
            </div>
            <div class="t">
              <div class="t1"><?= e($clinicName) ?></div>
              <div class="t2"><?= e($badge) ?></div>
            </div>
          </div>

          <div class="mrn">
            <div class="l">No. RM</div>
            <div class="v"><?= e($p['mrn']) ?></div>
          </div>
        </div>

        <div class="name"><?= e($p['full_name']) ?></div>

        <div class="grid">
          <div class="row">
            <div class="k">Tgl Lahir</div>
            <div class="v"><?= e(fmt_dob($p['dob'])) ?></div>
          </div>
          <div class="row">
            <div class="k">JK</div>
            <div class="v"><?= e(($p['gender'] ?? '') === 'P' ? 'Perempuan' : 'Laki-laki') ?></div>
          </div>
          <div class="row full">
            <div class="k">Alamat</div>
            <div class="v"><?= e($p['address'] ?: '-') ?></div>
          </div>
        </div>

        <div class="foot">
          <div class="note">Bawa kartu ini saat berobat</div>
        </div>
      </div>
    </div>
  </div>
    <script src="<?= e(url('/public/assets/js/app.js')) ?>"></script>
</body>
</html>
