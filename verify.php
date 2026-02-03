<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';
require_once __DIR__ . '/app/public_documents.php';

$settings = get_settings();

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{32}$/i', $token)) {
  http_response_code(400);
  echo "Token tidak valid.";
  exit;
}

$st = db()->prepare("SELECT * FROM public_documents WHERE token=? LIMIT 1");
$st->execute([$token]);
$pd = $st->fetch();

if (!$pd) {
  http_response_code(404);
  echo "Dokumen tidak ditemukan.";
  exit;
}

public_document_log_access((int)$pd['id']);

$docType = (string)$pd['doc_type'];
$docId = (int)$pd['doc_id'];

$doc = null;
$title = 'Verifikasi Dokumen';

function fmt_gender(string $g): string {
  return $g === 'L' ? 'Laki-laki' : ($g === 'P' ? 'Perempuan' : $g);
}

try {
  switch ($docType) {
    case 'visit_report':
      $q = "SELECT v.id, v.visit_no, v.visit_date,
                   p.mrn, p.full_name, p.dob, p.gender,
                   u.full_name AS doctor_name
            FROM visits v
            JOIN patients p ON p.id=v.patient_id
            LEFT JOIN users u ON u.id=v.doctor_id
            WHERE v.id=?";
      $st2 = db()->prepare($q);
      $st2->execute([$docId]);
      $doc = $st2->fetch();
      $title = 'Verifikasi Laporan Pemeriksaan';
      break;

    case 'sick_letter':
      $q = "SELECT s.id, s.letter_no, s.created_at,
                   s.start_date, s.end_date,
                   p.mrn, p.full_name, p.dob, p.gender,
                   u.full_name AS doctor_name
            FROM sick_letters s
            JOIN patients p ON p.id=s.patient_id
            LEFT JOIN users u ON u.id=s.doctor_id
            WHERE s.id=?";
      $st2 = db()->prepare($q);
      $st2->execute([$docId]);
      $doc = $st2->fetch();
      $title = 'Verifikasi Surat Sakit';
      break;

    case 'referral':
      $q = "SELECT r.id, r.referral_no, r.created_at,
                   r.referred_to_doctor, r.referred_to_specialty,
                   p.mrn, p.full_name, p.dob, p.gender,
                   u.full_name AS doctor_name
            FROM referrals r
            JOIN patients p ON p.id=r.patient_id
            LEFT JOIN users u ON u.id=r.sender_doctor_id
            WHERE r.id=?";
      $st2 = db()->prepare($q);
      $st2->execute([$docId]);
      $doc = $st2->fetch();
      $title = 'Verifikasi Surat Rujukan';
      break;

    case 'prescription':
      $q = "SELECT x.id, x.rx_no, x.created_at,
                   v.visit_no, v.visit_date,
                   p.mrn, p.full_name, p.dob, p.gender,
                   u.full_name AS doctor_name
            FROM prescriptions x
            JOIN visits v ON v.id=x.visit_id
            JOIN patients p ON p.id=v.patient_id
            LEFT JOIN users u ON u.id=v.doctor_id
            WHERE x.id=?";
      $st2 = db()->prepare($q);
      $st2->execute([$docId]);
      $doc = $st2->fetch();
      $title = 'Verifikasi Resep';
      break;

    case 'consent':
      $q = "SELECT c.id, c.consent_no, c.created_at, c.procedure_name,
                   v.visit_no, v.visit_date,
                   p.mrn, p.full_name, p.dob, p.gender,
                   u.full_name AS doctor_name
            FROM consents c
            JOIN visits v ON v.id=c.visit_id
            JOIN patients p ON p.id=v.patient_id
            LEFT JOIN users u ON u.id=c.doctor_id
            WHERE c.id=?";
      $st2 = db()->prepare($q);
      $st2->execute([$docId]);
      $doc = $st2->fetch();
      $title = 'Verifikasi Informed Consent';
      break;
  }
} catch (Throwable $e) {
  log_app('error', 'verify lookup failed', ['err'=>$e->getMessage(), 'doc_type'=>$docType, 'doc_id'=>$docId]);
  $doc = null;
}

if (!$doc) {
  http_response_code(404);
  echo "Detail dokumen tidak ditemukan. (doc_type=" . e($docType) . ")";
  exit;
}

$age = age_from_dob($doc['dob'] ?? null);
$isRevoked = ((int)($pd['revoked'] ?? 0)) === 1;

$clinicName = $settings['clinic_name'] ?? ($settings['brand_title'] ?? 'Adena Medical System');
$brandBadge = $settings['brand_badge'] ?? 'Adena Medical System';

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e(url('/public/assets/css/theme.css')) ?>">
  <style>
    body{background:#0b1220}
    .wrap{max-width:820px;margin:0 auto;padding:18px}
    .card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px}
    .muted{color:#a5b4fc}
    .k{color:#cbd5e1;font-size:12px}
    .v{color:#e5e7eb;font-weight:700}
    .grid{display:grid;grid-template-columns:1fr;gap:10px}
    @media (min-width: 720px){ .grid{grid-template-columns:1fr 1fr} }
    .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800}
    .ok{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.35);color:#bbf7d0}
    .bad{background:rgba(239,68,68,.18);border:1px solid rgba(239,68,68,.35);color:#fecaca}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
        <div>
          <div style="font-size:18px;font-weight:900;color:#e5e7eb"><?= e($clinicName) ?></div>
          <div class="muted" style="font-size:12px"><?= e($brandBadge) ?></div>
        </div>
        <div>
          <?php if ($isRevoked): ?>
            <span class="badge bad">TIDAK VALID (DICABUT)</span>
          <?php else: ?>
            <span class="badge ok">VALID</span>
          <?php endif; ?>
        </div>
      </div>

      <div style="margin-top:14px" class="grid">
        <div>
          <div class="k">Jenis dokumen</div>
          <div class="v"><?= e($title) ?></div>
        </div>
        <div>
          <div class="k">Token</div>
          <div class="v mono"><?= e($token) ?></div>
        </div>

        <div>
          <div class="k">Pasien</div>
          <div class="v"><?= e($doc['full_name'] ?? '-') ?></div>
          <div class="k"><?= e($doc['mrn'] ?? '-') ?> · <?= e((string)($age ?? '-')) ?> th · <?= e(fmt_gender((string)($doc['gender'] ?? ''))) ?></div>
        </div>
        <div>
          <div class="k">Dokter</div>
          <div class="v"><?= e($doc['doctor_name'] ?? '-') ?></div>
        </div>

        <?php if ($docType === 'visit_report'): ?>
          <div>
            <div class="k">No Kunjungan</div>
            <div class="v"><?= e($doc['visit_no'] ?? '-') ?></div>
          </div>
          <div>
            <div class="k">Tanggal Kunjungan</div>
            <div class="v"><?= e($doc['visit_date'] ?? '-') ?></div>
          </div>
        <?php endif; ?>

        <?php if ($docType === 'sick_letter'): ?>
          <div>
            <div class="k">No Surat Sakit</div>
            <div class="v"><?= e($doc['letter_no'] ?? '-') ?></div>
          </div>
          <div>
            <div class="k">Tanggal Terbit</div>
            <div class="v"><?= e($doc['created_at'] ?? '-') ?></div>
          </div>
          <div>
            <div class="k">Periode</div>
            <div class="v"><?= e($doc['start_date'] ?? '-') ?> s/d <?= e($doc['end_date'] ?? '-') ?></div>
          </div>
        <?php endif; ?>

        <?php if ($docType === 'referral'): ?>
          <div>
            <div class="k">No Rujukan</div>
            <div class="v"><?= e($doc['referral_no'] ?? '-') ?></div>
          </div>
          <div>
            <div class="k">Tanggal Terbit</div>
            <div class="v"><?= e($doc['created_at'] ?? '-') ?></div>
          </div>
          <div>
            <div class="k">Tujuan</div>
            <div class="v"><?= e($doc['referred_to_doctor'] ?? '-') ?> (<?= e($doc['referred_to_specialty'] ?? '-') ?>)</div>
          </div>
        <?php endif; ?>

        <?php if ($docType === 'prescription'): ?>
          <div>
            <div class="k">No Resep</div>
            <div class="v"><?= e($doc['rx_no'] ?? '-') ?></div>
          </div>
          <div>
            <div class="k">Tanggal Terbit</div>
            <div class="v"><?= e($doc['created_at'] ?? '-') ?></div>
          </div>
          <div>
            <div class="k">No Kunjungan</div>
            <div class="v"><?= e($doc['visit_no'] ?? '-') ?> · <?= e($doc['visit_date'] ?? '-') ?></div>
          </div>
        <?php endif; ?>

        <?php if ($docType === 'consent'): ?>
          <div>
            <div class="k">No Consent</div>
            <div class="v"><?= e($doc['consent_no'] ?? '-') ?></div>
          </div>
          <div>
            <div class="k">Tindakan</div>
            <div class="v"><?= e($doc['procedure_name'] ?? '-') ?></div>
          </div>
          <div>
            <div class="k">Tanggal Terbit</div>
            <div class="v"><?= e($doc['created_at'] ?? '-') ?></div>
          </div>
        <?php endif; ?>
      </div>

      <div style="margin-top:14px" class="k">
        Catatan: status “Valid” berarti token verifikasi terdaftar pada sistem. Jika dokumen dicabut, status akan berubah menjadi “Tidak valid”.
      </div>
    </div>
  </div>
</body>
</html>
