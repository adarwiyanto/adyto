<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin','dokter','perawat']);
$u = auth_user();
$settings = get_settings();
csrf_validate();

// Master Dokter Perujuk (opsional)
$refDocs = [];
try {
  $refDocs = db()->query("SELECT id, doctor_name FROM referral_doctors WHERE is_active=1 ORDER BY doctor_name ASC")->fetchAll();
} catch (Throwable $e) {
  $refDocs = [];
}


$patientId = (int)($_GET['patient_id'] ?? 0);
$action = $_POST['action'] ?? '';

// ===== Rekap filter (dipakai saat patient_id kosong / sidebar) =====
$range = $_GET['range'] ?? 'today';
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

function visit_range_dates(string $range, string $start, string $end): array {
  $today = date('Y-m-d');
  if ($range === 'today') return [$today, $today];
  if ($range === 'yesterday') {
    $y = date('Y-m-d', strtotime('-1 day'));
    return [$y, $y];
  }
  if ($range === 'last7') {
    $s = date('Y-m-d', strtotime('-6 day'));
    return [$s, $today];
  }
  if ($range === 'custom' && $start && $end) return [$start, $end];
  return [$today, $today];
}

function next_visit_no(): string {
  $prefix = date('Ymd');
  $row = db()->query("SELECT visit_no FROM visits WHERE visit_no LIKE '{$prefix}%' ORDER BY visit_no DESC LIMIT 1")->fetch();
  $n = 1;
  if ($row && preg_match('/^\d{8}(\d{4})$/', $row['visit_no'], $m)) $n = (int)$m[1] + 1;
  return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

// ===== helper upload USG =====
function usg_upload_dir_abs(): string {
  $base = realpath(__DIR__ . '/storage');
  if (!$base) return '';
  return $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'usg';
}
function usg_upload_url_rel(): string {
  return '/storage/uploads/usg';
}

function save_usg_images_for_visit(int $visitId, array $files, array $captions = []): void {
  if ($visitId <= 0) return;
  if (empty($files) || empty($files['name'])) return;

  $dir = usg_upload_dir_abs();
  if ($dir === '') throw new Exception('Folder storage tidak ditemukan.');
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  if (!is_dir($dir) || !is_writable($dir)) {
    throw new Exception('Folder upload USG tidak writable: ' . $dir);
  }

  $names = (array)$files['name'];
  $tmp   = (array)$files['tmp_name'];
  $errs  = (array)$files['error'];
  $sizes = (array)$files['size'];

  for ($i=0; $i<count($names); $i++) {
    if (!isset($errs[$i]) || $errs[$i] === UPLOAD_ERR_NO_FILE) continue;
    if ($errs[$i] !== UPLOAD_ERR_OK) continue;

    $orig = (string)$names[$i];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png'], true)) continue;

    if (isset($sizes[$i]) && (int)$sizes[$i] > 8 * 1024 * 1024) continue;

    $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($orig, PATHINFO_FILENAME));
    if (!$safe) $safe = 'usg';

    $fname = 'USG_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safe . '.' . $ext;
    $abs = $dir . DIRECTORY_SEPARATOR . $fname;

    if (!move_uploaded_file($tmp[$i], $abs)) continue;

    // Set permission aman untuk file upload (umumnya 0644 di hosting Linux)
    @chmod($abs, 0644);

    $rel = usg_upload_url_rel() . '/' . $fname;
    $cap = '';
    if (isset($captions[$i])) $cap = trim((string)$captions[$i]);

    // tabel dari patch pack
    db_exec("INSERT INTO usg_images(visit_id, file_path, caption, created_at) VALUES(?,?,?,?)",
      [$visitId, $rel, $cap, now_dt()]
    );
  }
}

// ===== CREATE VISIT (mode per pasien) + tutup antrian NEW + upload usg =====
if ($action === 'create_visit') {
  $patientId = (int)($_POST['patient_id'] ?? 0);
  $anamnesis = trim($_POST['anamnesis'] ?? '');
  $physical  = trim($_POST['physical_exam'] ?? '');
  $usg       = trim($_POST['usg_report'] ?? '');
  $therapy   = trim($_POST['therapy'] ?? 'Lanjutkan terapi dari dokter sebelumnya');

  $referralDoctorId = (int)($_POST['referral_doctor_id'] ?? 0);
  $isUsg = isset($_POST['is_usg']) ? 1 : 0;
  $usgType = trim((string)($_POST['usg_type'] ?? ''));
  if ($isUsg === 1) {
    if ($usgType !== 'intervention') $usgType = 'diagnostic';
  } else {
    // Jika user mengisi laporan USG tanpa checklist, anggap sebagai USG diagnostik
    if ($usg !== '') { $isUsg = 1; $usgType = 'diagnostic'; }
    else { $usgType = null; }
  }


  if ($patientId <= 0) {
    flash_set('err','Pilih pasien terlebih dahulu.');
    redirect('/visits.php');
  }

  $visitNo = next_visit_no();
  db_exec("INSERT INTO visits(patient_id, visit_no, visit_date, anamnesis, physical_exam, usg_report, therapy, doctor_id, referral_doctor_id, is_usg, usg_type, created_at)
           VALUES(?,?,?,?,?,?,?,?,?,?,?,?)",
    [$patientId, $visitNo, now_dt(), $anamnesis, $physical, $usg, $therapy, (int)($u['id'] ?? 0), ($referralDoctorId>0?$referralDoctorId:null), $isUsg, $usgType, now_dt()]
  );

  $newVisitId = (int)db()->lastInsertId();

  // Tutup antrian NEW (hari ini) kalau ada
  $today = date('Y-m-d');
  db_exec("UPDATE visit_queue
           SET status='done', handled_visit_id=?
           WHERE patient_id=? AND queue_date=? AND status='new'
           ORDER BY id DESC
           LIMIT 1",
    [$newVisitId, $patientId, $today]
  );

  // upload foto USG jika ada (kunjungan tetap sukses walau upload gagal)
  try {
    if (!empty($_FILES['usg_images']) && is_array($_FILES['usg_images'])) {
      $caps = $_POST['usg_caption'] ?? [];
      if (!is_array($caps)) $caps = [];
      save_usg_images_for_visit($newVisitId, $_FILES['usg_images'], $caps);
    }
  } catch (Throwable $e) {
    log_app('error', 'USG upload failed on create_visit', ['err'=>$e->getMessage(), 'visit_id'=>$newVisitId]);
    flash_set('err','Kunjungan tersimpan, tapi upload foto USG gagal: '.$e->getMessage());
    redirect('/visits.php?patient_id=' . $patientId);
  }

  flash_set('ok','Kunjungan tersimpan. No: ' . $visitNo);
  redirect('/visits.php?patient_id=' . $patientId);
}

// ===== UPDATE VISIT (jaga aman: tidak pakai updated_at) =====
if ($action === 'update_visit') {
  $id = (int)($_POST['id'] ?? 0);
  $anamnesis = trim($_POST['anamnesis'] ?? '');
  $physical  = trim($_POST['physical_exam'] ?? '');
  $usg       = trim($_POST['usg_report'] ?? '');
  $therapy   = trim($_POST['therapy'] ?? '');

  $referralDoctorId = (int)($_POST['referral_doctor_id'] ?? 0);
  $isUsg = isset($_POST['is_usg']) ? 1 : 0;
  $usgType = trim((string)($_POST['usg_type'] ?? ''));
  if ($isUsg === 1) {
    if ($usgType !== 'intervention') $usgType = 'diagnostic';
  } else {
    if ($usg !== '') { $isUsg = 1; $usgType = 'diagnostic'; }
    else { $usgType = null; }
  }


  db_exec("UPDATE visits SET anamnesis=?, physical_exam=?, usg_report=?, therapy=?, doctor_id=?, referral_doctor_id=?, is_usg=?, usg_type=? WHERE id=?",
    [$anamnesis, $physical, $usg, $therapy, (int)($u['id'] ?? 0), ($referralDoctorId>0?$referralDoctorId:null), $isUsg, $usgType, $id]
  );

  flash_set('ok','Kunjungan diperbarui.');
  $pid = (int)($_POST['patient_id'] ?? 0);
  redirect('/visits.php?patient_id=' . $pid);
}

$patients = db()->query("SELECT id, mrn, full_name, dob, gender FROM patients ORDER BY created_at DESC LIMIT 300")->fetchAll();

/**
 * ===== REKAP MODE (SIDEBAR) =====
 * Jika patient_id kosong, tampilkan antrian pendaftaran berobat (visit_queue) + status NEW/DONE
 */
if ($patientId <= 0) {
  list($d1, $d2) = visit_range_dates($range, $start, $end);

  $st = db()->prepare("
    SELECT
      q.id AS queue_id,
      q.queue_date,
      q.status,
      q.patient_id,
      q.handled_visit_id,
      q.created_at AS queue_created_at,

      p.mrn,
      p.full_name,

      v.visit_no,
      v.visit_date,
      u.full_name AS doctor_name
    FROM visit_queue q
    JOIN patients p ON p.id = q.patient_id
    LEFT JOIN visits v ON v.id = q.handled_visit_id
    LEFT JOIN users u ON u.id = v.doctor_id
    WHERE q.queue_date BETWEEN ? AND ?
    ORDER BY q.queue_date DESC, q.id DESC
    LIMIT 200
  ");
  $st->execute([$d1, $d2]);
  $rows = $st->fetchAll();

  $title = "Kunjungan";
  require __DIR__ . '/app/views/partials/header.php';
  ?>
  <div class="card">
    <div class="h1">Kunjungan</div>
    <div class="muted">Rekap pendaftaran berobat (NEW/DONE) berdasarkan rentang waktu.</div>
  </div>

  <div class="card">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
      <div style="min-width:220px">
        <div class="label">Rentang</div>
        <select class="input" name="range">
          <option value="today" <?= $range==='today'?'selected':'' ?>>Hari ini</option>
          <option value="yesterday" <?= $range==='yesterday'?'selected':'' ?>>Kemarin</option>
          <option value="last7" <?= $range==='last7'?'selected':'' ?>>7 hari terakhir</option>
          <option value="custom" <?= $range==='custom'?'selected':'' ?>>Custom</option>
        </select>
      </div>
      <div>
        <div class="label">Mulai</div>
        <input class="input" type="date" name="start" value="<?= e($d1) ?>">
      </div>
      <div>
        <div class="label">Sampai</div>
        <input class="input" type="date" name="end" value="<?= e($d2) ?>">
      </div>
      <button class="btn secondary" type="submit">Terapkan</button>
      <a class="btn" href="<?= e(url('/patients.php')) ?>">+ Pasien</a>
    </form>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Daftar Pendaftaran Berobat</div>
    <table class="table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Status</th>
          <th>Pasien</th>
          <th>No Kunjungan</th>
          <th>Dokter</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $stt = strtoupper((string)($r['status'] ?? '')); ?>
          <tr>
            <td><?= e($r['queue_date']) ?></td>
            <td><?= e($stt) ?></td>
            <td><?= e(($r['mrn'] ?? '').' - '.($r['full_name'] ?? '')) ?></td>
            <td><?= e($r['visit_no'] ?? '-') ?></td>
            <td><?= e($r['doctor_name'] ?? '-') ?></td>
            <td style="display:flex;gap:8px;flex-wrap:wrap">
              <a class="btn small secondary" href="<?= e(url('/visits.php?patient_id='.(int)$r['patient_id'])) ?>">Buka Pasien</a>

              <?php if (!empty($r['handled_visit_id'])): ?>
                <a class="btn small secondary" href="<?= e(url('/visit_edit.php?id='.(int)$r['handled_visit_id'])) ?>">Edit</a>
                <a class="btn small" href="<?= e(url('/print_visit.php?id='.(int)$r['handled_visit_id'])) ?>" target="_blank">Print Hasil</a>
                <a class="btn small secondary" href="<?= e(url('/prescriptions.php?visit_id='.(int)$r['handled_visit_id'])) ?>">Resep</a>
                <a class="btn small secondary" href="<?= e(url('/referrals.php?visit_id='.(int)$r['handled_visit_id'])) ?>">Rujukan</a>
              <?php else: ?>
                <span class="muted">Belum ditangani</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="muted">Tidak ada data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php require __DIR__ . '/app/views/partials/footer.php'; ?>
  <?php exit; ?>
<?php } ?>

<?php
// ===== MODE PER PASIEN (buat kunjungan baru + preview upload USG) =====
$patient = null;
if ($patientId > 0) {
  $st = db()->prepare("SELECT * FROM patients WHERE id = ?");
  $st->execute([$patientId]);
  $patient = $st->fetch();
}

$visits = [];
if ($patientId > 0) {
  $st = db()->prepare("SELECT v.*, u.full_name AS doctor_name
                       FROM visits v
                       LEFT JOIN users u ON u.id=v.doctor_id
                       WHERE v.patient_id = ?
                       ORDER BY v.visit_date DESC
                       LIMIT 50");
  $st->execute([$patientId]);
  $visits = $st->fetchAll();
}

$title = "Kunjungan";
require __DIR__ . '/app/views/partials/header.php';
?>
<style>
  .usg-upload-row{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
  .file-hidden{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
  .usg-preview{margin-top:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px}
  .usg-thumb{border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:10px;background:rgba(255,255,255,.03)}
  .usg-thumb img{width:100%;height:120px;object-fit:cover;border-radius:10px;display:block}
  .usg-cap{margin-top:6px;font-size:12px;opacity:.85;word-break:break-word}
</style>

<div class="card">
  <div class="h1">Kunjungan</div>
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div style="min-width:320px;flex:1">
      <div class="label">Pilih pasien (atau ketik lalu pilih)</div>
      <select class="input" name="patient_id" required>
        <option value="">-- pilih --</option>
        <?php foreach ($patients as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= $patientId==(int)$p['id']?'selected':'' ?>>
            <?= e($p['mrn'].' - '.$p['full_name'].' ('.($p['dob']?age_from_dob($p['dob']).' th':'-').')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn secondary" type="submit">Tampilkan</button>
    <a class="btn" href="<?= e(url('/patients.php')) ?>">+ Pasien</a>
  </form>
</div>

<?php if ($patient): ?>
  <div class="card">
    <div class="h1" style="font-size:16px">Identitas Pasien</div>
    <div class="grid">
      <div class="col-6"><div class="muted">MRN</div><div><?= e($patient['mrn']) ?></div></div>
      <div class="col-6"><div class="muted">Nama</div><div><?= e($patient['full_name']) ?></div></div>
      <div class="col-6"><div class="muted">Usia</div><div><?= e((string)(age_from_dob($patient['dob']) ?? '-')) ?></div></div>
      <div class="col-6"><div class="muted">Jenis Kelamin</div><div><?= e($patient['gender']) ?></div></div>
      <div class="col-12"><div class="muted">Alamat</div><div><?= e($patient['address'] ?? '') ?></div></div>
    </div>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Tambah Kunjungan Baru</div>

    <form method="post" class="grid" autocomplete="off" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_visit">
      <input type="hidden" name="patient_id" value="<?= (int)$patientId ?>">

      <div class="col-12">
        <div class="label">Anamnesa</div>
        <textarea class="input" name="anamnesis"></textarea>
      </div>
      <div class="col-12">
        <div class="label">Pemeriksaan Fisik</div>
        <textarea class="input" name="physical_exam"></textarea>
      </div>
      <div class="col-12">
        <div class="label">Dokter Perujuk (opsional)</div>
        <select class="input" name="referral_doctor_id">
          <option value="0">-- tidak ada / tidak diisi --</option>
          <?php foreach ($refDocs as $rd): ?>
            <option value="<?= (int)$rd['id'] ?>"><?= e($rd['doctor_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label style="display:flex;align-items:center;gap:10px">
          <input type="checkbox" name="is_usg" id="is_usg_create" value="1">
          <span>Termasuk pemeriksaan USG</span>
        </label>
        <div id="usg_type_wrap_create" style="margin-top:8px;display:none;gap:14px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:8px">
            <input type="radio" name="usg_type" value="diagnostic" checked> <span>USG diagnostik</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px">
            <input type="radio" name="usg_type" value="intervention"> <span>USG intervensi</span>
          </label>
        </div>
        <div class="muted" style="margin-top:6px">Checklist ini dipakai untuk rekapan bulanan/tahunan.</div>
      </div>

      <div class="col-12" id="usg_report_wrap_create">
        <div class="label">Laporan USG</div>
        <textarea class="input" name="usg_report"></textarea>
      </div>
      <div class="col-12">
        <div class="label">Pengobatan / Terapi</div>
        <textarea class="input" name="therapy">Lanjutkan terapi dari dokter sebelumnya</textarea>
      </div>

      <div class="col-12">
        <div class="h1" style="font-size:16px;margin-top:4px">Foto USG</div>
        <div class="muted">Upload foto hasil USG (JPG/PNG). Akan ikut tercetak pada "Print Hasil".</div>

        <div class="usg-upload-row" style="margin-top:10px">
          <input id="usg_images_create" class="file-hidden" type="file" name="usg_images[]" accept=".jpg,.jpeg,.png" multiple>
          <label class="btn secondary" for="usg_images_create">Pilih Foto</label>

          <input class="input" name="usg_caption[]" placeholder="Caption (opsional, mis: Abdomen, Ginjal kanan)" style="min-width:320px;flex:1">
        </div>

        <div id="usgPreviewCreate" class="usg-preview" style="display:none"></div>
      </div>

      <div class="col-12" style="display:flex;justify-content:flex-end;gap:10px">
        <button class="btn" type="submit">Simpan Kunjungan</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Riwayat Kunjungan</div>
    <table class="table">
      <thead>
        <tr><th>Tanggal</th><th>No Kunjungan</th><th>Dokter</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($visits as $v): ?>
          <tr>
            <td><?= e($v['visit_date']) ?></td>
            <td><?= e($v['visit_no']) ?></td>
            <td><?= e($v['doctor_name'] ?? '-') ?></td>
            <td style="display:flex;gap:8px;flex-wrap:wrap">
              <a class="btn small secondary" href="<?= e(url('/visit_edit.php?id='.(int)$v['id'])) ?>">Edit</a>
              <a class="btn small" href="<?= e(url('/print_visit.php?id='.(int)$v['id'])) ?>" target="_blank">Print Hasil</a>
              <a class="btn small secondary" href="<?= e(url('/prescriptions.php?visit_id='.(int)$v['id'])) ?>">Resep</a>
              <a class="btn small secondary" href="<?= e(url('/referrals.php?visit_id='.(int)$v['id'])) ?>">Rujukan</a>
              <a class="btn small secondary" href="<?= e(url('/sick_letters.php?visit_id='.(int)$v['id'])) ?>">Surat Sakit</a>
              <a class="btn small secondary" href="<?= e(url('/consents.php?visit_id='.(int)$v['id'])) ?>">Informed Consent</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$visits): ?><tr><td colspan="4" class="muted">Belum ada kunjungan.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <script>
    (function(){
      const input = document.getElementById('usg_images_create');
      const wrap  = document.getElementById('usgPreviewCreate');
      if (!input || !wrap) return;

      input.addEventListener('change', function(){
        wrap.innerHTML = '';
        const files = Array.from(input.files || []);
        if (!files.length) {
          wrap.style.display = 'none';
          return;
        }
        wrap.style.display = 'grid';
        files.forEach(f => {
          const url = URL.createObjectURL(f);
          const box = document.createElement('div');
          box.className = 'usg-thumb';
          box.innerHTML = `<img src="${url}" alt="USG"><div class="usg-cap">${escapeHtml(f.name)}</div>`;
          wrap.appendChild(box);
        });
      });


      // Toggle USG field visibility
      const chk = document.getElementById('is_usg_create');
      const typeWrap = document.getElementById('usg_type_wrap_create');
      const reportWrap = document.getElementById('usg_report_wrap_create');
      function syncUsg(){
        const on = !!(chk && chk.checked);
        if (typeWrap) typeWrap.style.display = on ? 'flex' : 'none';
        if (reportWrap) reportWrap.style.opacity = on ? '1' : '.85';
      }
      if (chk) {
        chk.addEventListener('change', syncUsg);
        syncUsg();
      }

      function escapeHtml(s){
        return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
      }
    })();
  </script>
<?php endif; ?>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
