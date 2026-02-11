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
        <div class="muted">Pilih pasien dari halaman detail pasien untuk membuka imaging.</div>
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

<?php if ($patient): ?>
<div id="dicom-app" class="dicom-layout" data-patient-id="<?= (int)$patient['id'] ?>">
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
