<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin','dokter','perawat']);
$u = auth_user();
$settings = get_settings();
csrf_validate();

$visitId = (int)($_GET['visit_id'] ?? ($_POST['visit_id'] ?? 0));
$action = $_POST['action'] ?? '';

$range = $_GET['range'] ?? 'today';
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

function consent_range_dates(string $range, string $start, string $end): array {
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

function next_consent_no(): string {
  $prefix = 'IC' . date('Ymd');
  $row = db()->query("SELECT consent_no FROM consents WHERE consent_no LIKE '{$prefix}%' ORDER BY consent_no DESC LIMIT 1")->fetch();
  $n = 1;
  if ($row && preg_match('/^IC\d{8}(\d{4})$/', $row['consent_no'], $m)) $n = (int)$m[1] + 1;
  return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function save_consent_signature(string $dataUrl): ?string {
  if (!preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl, $m)) return null;
  $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
  $data = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
  if ($data === false) return null;

  $dir = __DIR__ . '/storage/uploads/signature/consents';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  if (!is_dir($dir) || !is_writable($dir)) {
    throw new Exception('Folder tanda tangan tidak writable: ' . $dir);
  }

  $fname = 'CONSENT_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $abs = $dir . DIRECTORY_SEPARATOR . $fname;
  if (file_put_contents($abs, $data) === false) {
    throw new Exception('Gagal menyimpan tanda tangan.');
  }
  @chmod($abs, 0644);

  return '/storage/uploads/signature/consents/' . $fname;
}

$visit = null;
if ($visitId > 0) {
  $st = db()->prepare("SELECT v.*, p.mrn, p.full_name, p.dob, p.gender, p.address, u.full_name AS doctor_name
                       FROM visits v
                       JOIN patients p ON p.id=v.patient_id
                       LEFT JOIN users u ON u.id=v.doctor_id
                       WHERE v.id=?");
  $st->execute([$visitId]);
  $visit = $st->fetch();
}

if ($action === 'create_consent') {
  if (!$visit) {
    flash_set('err','Informed consent harus dibuat dari kunjungan (visit_id tidak valid).');
    redirect('/consents.php');
  }

  $procedure = trim($_POST['procedure_name'] ?? '');
  $diagnosis = trim($_POST['diagnosis'] ?? '');
  $risks = trim($_POST['risks'] ?? '');
  $benefits = trim($_POST['benefits'] ?? '');
  $alternatives = trim($_POST['alternatives'] ?? '');
  $notes = trim($_POST['notes'] ?? '');
  $signerName = trim($_POST['signer_name'] ?? '');
  $signerRelation = trim($_POST['signer_relation'] ?? '');
  $signatureData = trim($_POST['signature_data'] ?? '');

  if ($procedure === '') {
    flash_set('err','Nama tindakan wajib diisi.');
    redirect('/consents.php?visit_id=' . $visitId);
  }

  if ($signatureData === '') {
    flash_set('err','Tanda tangan pasien/keluarga wajib diisi.');
    redirect('/consents.php?visit_id=' . $visitId);
  }

  try {
    $sigPath = save_consent_signature($signatureData);
  } catch (Throwable $e) {
    flash_set('err','Gagal menyimpan tanda tangan: ' . $e->getMessage());
    redirect('/consents.php?visit_id=' . $visitId);
  }

  if (!$sigPath) {
    flash_set('err','Format tanda tangan tidak valid.');
    redirect('/consents.php?visit_id=' . $visitId);
  }

  $consentNo = next_consent_no();
  db_exec("INSERT INTO consents(visit_id, patient_id, doctor_id, consent_no, procedure_name, diagnosis, risks, benefits, alternatives, notes, signer_name, signer_relation, signature_path, created_at)
           VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
    [$visitId, (int)$visit['patient_id'], (int)$visit['doctor_id'], $consentNo, $procedure, $diagnosis, $risks, $benefits, $alternatives, $notes, $signerName, $signerRelation, $sigPath, now_dt()]
  );

  flash_set('ok','Informed consent dibuat. No: ' . $consentNo);
  redirect('/consents.php?visit_id=' . $visitId);
}

$consentList = [];
if ($visitId > 0) {
  $st = db()->prepare("SELECT * FROM consents WHERE visit_id=? ORDER BY created_at DESC LIMIT 50");
  $st->execute([$visitId]);
  $consentList = $st->fetchAll();
}

$title = 'Informed Consent';
require __DIR__ . '/app/views/partials/header.php';
?>

<style>
  .sig-wrap{border:1px dashed rgba(255,255,255,.2);border-radius:12px;padding:10px;background:rgba(255,255,255,.02)}
  .sig-canvas{width:100%;height:180px;display:block;touch-action:none;background:#fff;border-radius:10px}
  .sig-actions{display:flex;gap:8px;align-items:center;margin-top:8px}
</style>

<div class="card">
  <div class="h1">Informed Consent</div>
  <?php if ($visit): ?>
    <div class="muted">Kunjungan: <?= e($visit['visit_no']) ?> | Pasien: <?= e($visit['mrn'].' - '.$visit['full_name']) ?></div>
  <?php else: ?>
    <div class="muted">Rekap informed consent berdasarkan rentang waktu.</div>
  <?php endif; ?>
</div>

<?php if (!$visit): ?>
  <?php
    list($d1, $d2) = consent_range_dates($range, $start, $end);
    $dt1 = $d1 . ' 00:00:00';
    $dt2 = $d2 . ' 23:59:59';

    $st = db()->prepare("
      SELECT c.*, p.mrn, p.full_name, v.visit_no
      FROM consents c
      JOIN patients p ON p.id=c.patient_id
      LEFT JOIN visits v ON v.id=c.visit_id
      WHERE c.created_at BETWEEN ? AND ?
      ORDER BY c.created_at DESC
      LIMIT 200
    ");
    $st->execute([$dt1, $dt2]);
    $rows = $st->fetchAll();
  ?>

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
    </form>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Daftar Informed Consent</div>
    <table class="table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No Consent</th>
          <th>Pasien</th>
          <th>No Kunjungan</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e($row['created_at']) ?></td>
            <td><?= e($row['consent_no']) ?></td>
            <td><?= e(($row['mrn'] ?? '').' - '.($row['full_name'] ?? '')) ?></td>
            <td><?= e($row['visit_no'] ?? '-') ?></td>
            <td>
              <a class="btn small" href="<?= e(url('/print_consent.php?id='.(int)$row['id'])) ?>" target="_blank">Print</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted">Tidak ada data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php require __DIR__ . '/app/views/partials/footer.php'; ?>
  <?php exit; ?>
<?php endif; ?>

<?php if ($visit): ?>
  <div class="card">
    <div class="h1" style="font-size:16px">Buat Informed Consent</div>
    <form method="post" class="grid" autocomplete="off" id="consentForm">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_consent">
      <input type="hidden" name="visit_id" value="<?= (int)$visitId ?>">
      <input type="hidden" name="signature_data" id="signatureData">

      <div class="col-12">
        <div class="label">Nama Tindakan</div>
        <input class="input" name="procedure_name" placeholder="Contoh: Jahit luka, operasi minor, dll">
      </div>

      <div class="col-12">
        <div class="label">Diagnosa</div>
        <textarea class="input" name="diagnosis" placeholder="Diagnosa pasien (opsional)"></textarea>
      </div>

      <div class="col-12">
        <div class="label">Risiko yang Dijelaskan</div>
        <textarea class="input" name="risks" placeholder="Risiko tindakan"></textarea>
      </div>

      <div class="col-12">
        <div class="label">Manfaat yang Dijelaskan</div>
        <textarea class="input" name="benefits" placeholder="Manfaat tindakan"></textarea>
      </div>

      <div class="col-12">
        <div class="label">Alternatif Tindakan</div>
        <textarea class="input" name="alternatives" placeholder="Alternatif (opsional)"></textarea>
      </div>

      <div class="col-12">
        <div class="label">Catatan Tambahan</div>
        <textarea class="input" name="notes" placeholder="Catatan tambahan (opsional)"></textarea>
      </div>

      <div class="col-6">
        <div class="label">Nama Penanda Tangan</div>
        <input class="input" name="signer_name" value="<?= e($visit['full_name'] ?? '') ?>" placeholder="Nama pasien/keluarga">
      </div>
      <div class="col-6">
        <div class="label">Hubungan dengan Pasien</div>
        <input class="input" name="signer_relation" placeholder="Contoh: Pasien / Orang tua / Suami / Istri">
      </div>

      <div class="col-12">
        <div class="label">Tanda Tangan Pasien/Keluarga</div>
        <div class="sig-wrap">
          <canvas id="sigCanvas" class="sig-canvas"></canvas>
          <div class="sig-actions">
            <button class="btn small secondary" type="button" id="sigClear">Bersihkan</button>
            <div class="muted">Gunakan mouse atau sentuh layar untuk tanda tangan.</div>
          </div>
        </div>
      </div>

      <div class="col-12" style="display:flex;justify-content:flex-end">
        <button class="btn" type="submit">Simpan Consent</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="h1" style="font-size:16px">Riwayat Informed Consent</div>
    <table class="table">
      <thead><tr><th>No Consent</th><th>Tanggal</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($consentList as $row): ?>
          <tr>
            <td><?= e($row['consent_no']) ?></td>
            <td><?= e($row['created_at']) ?></td>
            <td><a class="btn small" href="<?= e(url('/print_consent.php?id='.(int)$row['id'])) ?>" target="_blank">Print</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$consentList): ?><tr><td colspan="3" class="muted">Belum ada consent.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<script>
  (function(){
    const canvas = document.getElementById('sigCanvas');
    const clearBtn = document.getElementById('sigClear');
    const sigInput = document.getElementById('signatureData');
    if (!canvas || !sigInput) return;

    const ctx = canvas.getContext('2d');
    let drawing = false;
    let hasInk = false;

    function resizeCanvas(){
      const ratio = window.devicePixelRatio || 1;
      const rect = canvas.getBoundingClientRect();
      canvas.width = rect.width * ratio;
      canvas.height = rect.height * ratio;
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.scale(ratio, ratio);
      ctx.lineWidth = 2;
      ctx.lineCap = 'round';
      ctx.strokeStyle = '#111';
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, rect.width, rect.height);
    }

    function getPos(e){
      const rect = canvas.getBoundingClientRect();
      const point = e.touches ? e.touches[0] : e;
      return { x: point.clientX - rect.left, y: point.clientY - rect.top };
    }

    function startDraw(e){
      drawing = true;
      const pos = getPos(e);
      ctx.beginPath();
      ctx.moveTo(pos.x, pos.y);
      e.preventDefault();
    }

    function draw(e){
      if (!drawing) return;
      const pos = getPos(e);
      ctx.lineTo(pos.x, pos.y);
      ctx.stroke();
      hasInk = true;
      e.preventDefault();
    }

    function endDraw(){
      if (!drawing) return;
      drawing = false;
      if (hasInk) {
        sigInput.value = canvas.toDataURL('image/png');
      }
    }

    function clearPad(){
      const ratio = window.devicePixelRatio || 1;
      const rect = canvas.getBoundingClientRect();
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.scale(ratio, ratio);
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, rect.width, rect.height);
      sigInput.value = '';
      hasInk = false;
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('mouseleave', endDraw);

    canvas.addEventListener('touchstart', startDraw, {passive:false});
    canvas.addEventListener('touchmove', draw, {passive:false});
    canvas.addEventListener('touchend', endDraw);

    if (clearBtn) clearBtn.addEventListener('click', clearPad);

    const form = document.getElementById('consentForm');
    if (form) {
      form.addEventListener('submit', function(){
        if (!sigInput.value && hasInk) {
          sigInput.value = canvas.toDataURL('image/png');
        }
      });
    }
  })();
</script>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
