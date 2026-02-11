<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$u = auth_user();
$settings = get_settings();

$patientId = (int)($_GET['patient_id'] ?? 0);
$patient = null;
if ($patientId > 0) {
  $st = db()->prepare('SELECT id, mrn, full_name, dob, gender FROM patients WHERE id=? LIMIT 1');
  $st->execute([$patientId]);
  $patient = $st->fetch();
  if (!$patient) {
    http_response_code(404);
    echo 'Pasien tidak ditemukan';
    exit;
  }
}

$todayPatients = db()->query("
  SELECT DISTINCT p.id, p.mrn, p.full_name, p.gender, p.dob, MAX(v.visit_date) AS last_visit_date
  FROM visits v
  JOIN patients p ON p.id=v.patient_id
  WHERE DATE(v.visit_date)=CURDATE()
  GROUP BY p.id, p.mrn, p.full_name, p.gender, p.dob
  ORDER BY last_visit_date DESC
  LIMIT 200
")->fetchAll();

$expertiseValue = '';
if ($patient) {
  $st = db()->prepare('SELECT usg_report FROM visits WHERE patient_id=? ORDER BY visit_date DESC, id DESC LIMIT 1');
  $st->execute([(int)$patient['id']]);
  $row = $st->fetch();
  $expertiseValue = trim((string)($row['usg_report'] ?? ''));
}

$title = 'DICOM Viewer';
require __DIR__ . '/app/views/partials/header.php';
?>
<link rel="stylesheet" href="<?= e(url('/public/assets/dicom/dicom-viewer.css')) ?>">

<div class="card">
  <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
    <div>
      <div class="h1">DICOM Viewer</div>
      <?php if ($patient): ?>
        <div class="muted">Pasien: <strong><?= e($patient['full_name']) ?></strong> (MRN <?= e($patient['mrn']) ?>)</div>
      <?php else: ?>
        <div class="muted">Pilih pasien dari daftar pemeriksaan hari ini untuk membuka imaging dan upload DICOM.</div>
      <?php endif; ?>
    </div>
    <?php if ($patient): ?>
      <form id="dicom-upload-form" method="post" enctype="multipart/form-data" action="<?= e(url('/dicom_api.php')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="upload_study">
        <input type="hidden" name="patient_id" value="<?= (int)$patient['id'] ?>">
        <label class="btn secondary" for="dicom-zip-input">Pilih ZIP DICOM</label>
        <input id="dicom-zip-input" type="file" name="dicom_zip" accept=".zip" required style="display:none">
        <button type="submit" class="btn">Upload Study</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!$patient): ?>
  <div class="card">
    <div class="h1" style="font-size:16px">Daftar Pasien Pemeriksaan Hari Ini</div>
    <?php if (!$todayPatients): ?>
      <div class="muted">Belum ada data pemeriksaan hari ini.</div>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr><th>MRN</th><th>Nama</th><th>JK</th><th>Tgl Lahir</th><th>Visit</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($todayPatients as $tp): ?>
            <tr>
              <td><?= e($tp['mrn']) ?></td>
              <td><?= e($tp['full_name']) ?></td>
              <td><?= e($tp['gender']) ?></td>
              <td><?= e($tp['dob'] ?: '-') ?></td>
              <td><?= e($tp['last_visit_date']) ?></td>
              <td>
                <a class="btn small secondary" href="<?= e(url('/dicom_viewer.php?patient_id=' . (int)$tp['id'])) ?>">Buka DICOM</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($patient): ?>
<div id="dicom-app" class="dicom-layout" data-patient-id="<?= (int)$patient['id'] ?>">
  <section class="dicom-sidebar-panel dicom-expertise-panel">
    <h3>Ekspertise</h3>
    <div class="muted" style="margin-bottom:8px">Catatan ini otomatis disinkronkan ke pemeriksaan pasien (kolom laporan USG).</div>
    <form id="dicom-expertise-form" class="dicom-expertise-form">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <textarea class="input" id="dicom-expertise" name="expertise" rows="12" placeholder="Tulis hasil ekspertise..."><?= e($expertiseValue) ?></textarea>
      <div style="display:flex;justify-content:flex-end;margin-top:10px">
        <button type="submit" class="btn">Simpan Ekspertise</button>
      </div>
    </form>
  </section>

  <section class="dicom-sidebar-panel">
    <h3>Study & Series</h3>
    <div id="dicom-study-list" class="dicom-list"></div>

    <h3 style="margin-top:16px">Tools</h3>
    <div class="dicom-controls">
      <button type="button" data-tool="Wwwc">WL/WW</button>
      <button type="button" data-tool="Pan">Pan</button>
      <button type="button" data-tool="Zoom">Zoom</button>
      <button type="button" id="dicom-reset-btn">Reset</button>
    </div>
  </section>

  <section class="dicom-viewport-panel">
    <div id="dicom-viewport" class="dicom-viewport"></div>
    <div class="dicom-overlay" id="dicom-overlay"></div>
    <div class="dicom-slice-control">
      <input type="range" id="dicom-slice-slider" min="1" max="1" value="1">
      <span id="dicom-slice-label">Slice 0/0</span>
    </div>
  </section>
</div>
<?php endif; ?>

<script src="https://unpkg.com/dicom-parser/dist/dicomParser.min.js"></script>
<script src="https://unpkg.com/cornerstone-core/dist/cornerstone.min.js"></script>
<script src="https://unpkg.com/cornerstone-math/dist/cornerstoneMath.min.js"></script>
<script src="https://unpkg.com/hammerjs/hammer.min.js"></script>
<script src="https://unpkg.com/cornerstone-tools/dist/cornerstoneTools.min.js"></script>
<script src="https://unpkg.com/cornerstone-wado-image-loader/dist/cornerstoneWADOImageLoader.min.js"></script>
<script src="<?= e(url('/public/assets/dicom/dicom-viewer.js')) ?>"></script>
<script>
window.DICOM_VIEWER_BOOTSTRAP = {
  patientId: <?= (int)($patient['id'] ?? 0) ?>,
  apiUrl: <?= json_encode(url('/dicom_api.php')) ?>
};
</script>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
