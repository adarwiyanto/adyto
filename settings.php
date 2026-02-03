<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin']);
$u = auth_user();
$settings = get_settings();
csrf_validate();

function save_upload(string $field, string $dirRel, array $allowedExt): ?string {
  if (empty($_FILES[$field]['name'])) return null;
  if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;

  $name = $_FILES[$field]['name'];
  $tmp = $_FILES[$field]['tmp_name'];
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) return null;

  $dirAbs = __DIR__ . '/storage/uploads/' . $dirRel;
  if (!is_dir($dirAbs)) @mkdir($dirAbs, 0775, true);

  $fn = $field . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
  $dest = $dirAbs . '/' . $fn;
  if (!move_uploaded_file($tmp, $dest)) return null;

  return '/storage/uploads/' . $dirRel . '/' . $fn;
}

if (is_post()) {
  set_setting('brand_title', trim($_POST['brand_title'] ?? 'Praktek dr. Agus'));
  set_setting('brand_badge', trim($_POST['brand_badge'] ?? 'Adena Medical System'));
  set_setting('footer_text', trim($_POST['footer_text'] ?? '© 2026 Adena Medical System ver 1.1'));

  set_setting('clinic_name', trim($_POST['clinic_name'] ?? ''));
  set_setting('clinic_address', trim($_POST['clinic_address'] ?? ''));
  set_setting('clinic_sip', trim($_POST['clinic_sip'] ?? ''));

  set_setting('custom_css', (string)($_POST['custom_css'] ?? ''));
  set_setting('card_css', (string)($_POST['card_css'] ?? ''));

  // QR verification settings (optional)
  $qrProvider = trim((string)($_POST['qr_provider'] ?? 'qrserver'));
  if (!in_array($qrProvider, ['qrserver','quickchart'], true)) $qrProvider = 'qrserver';
  set_setting('qr_provider', $qrProvider);

  $qrSize = (int)($_POST['qr_size'] ?? 180);
  if ($qrSize < 60) $qrSize = 180;
  if ($qrSize > 600) $qrSize = 600;
  set_setting('qr_size', (string)$qrSize);


  $logo = save_upload('logo', 'logo', ['png','jpg','jpeg','webp']);
  if ($logo) set_setting('logo_path', $logo);

  $sig = save_upload('signature', 'signature', ['png','jpg','jpeg','webp']);
  if ($sig) set_setting('signature_path', $sig);

  flash_set('ok','Setting tersimpan.');
  redirect('/settings.php');
}

$settings = get_settings();
$title = "Kop Surat & Theme";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Kop Surat & Theme</div>
  <div class="muted">Ubah header/footer, logo, SIP, tanda tangan, dan custom CSS tanpa edit file di luar.</div>
</div>

<div class="card">
  <form method="post" enctype="multipart/form-data" class="grid">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <div class="col-6">
      <div class="label">Judul Header</div>
      <input class="input" name="brand_title" value="<?= e($settings['brand_title'] ?? 'Praktek dr. Agus') ?>">
    </div>
    <div class="col-6">
      <div class="label">Badge Header</div>
      <input class="input" name="brand_badge" value="<?= e($settings['brand_badge'] ?? 'Adena Medical System') ?>">
    </div>

    <div class="col-12">
      <div class="label">Footer</div>
      <input class="input" name="footer_text" value="<?= e($settings['footer_text'] ?? '© 2026 Adena Medical System ver 1.1') ?>">
    </div>

    <div class="col-6">
      <div class="label">Nama Tempat Praktek (kop print)</div>
      <input class="input" name="clinic_name" value="<?= e($settings['clinic_name'] ?? '') ?>">
    </div>
    <div class="col-6">
      <div class="label">Nomor SIP (kop print)</div>
      <input class="input" name="clinic_sip" value="<?= e($settings['clinic_sip'] ?? '') ?>">
    </div>
    <div class="col-12">
      <div class="label">Alamat Tempat Praktek (kop print)</div>
      <input class="input" name="clinic_address" value="<?= e($settings['clinic_address'] ?? '') ?>">
    </div>

    <div class="col-6">
      <div class="label">Logo (png/jpg/webp)</div>
      <input class="input" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp">
      <?php if (!empty($settings['logo_path'])): ?>
        <div class="muted" style="margin-top:6px">Saat ini: <code><?= e($settings['logo_path']) ?></code></div>
      <?php endif; ?>
    </div>

    <div class="col-6">
      <div class="label">Tanda tangan (png/jpg/webp)</div>
      <input class="input" type="file" name="signature" accept=".png,.jpg,.jpeg,.webp">
      <?php if (!empty($settings['signature_path'])): ?>
        <div class="muted" style="margin-top:6px">Saat ini: <code><?= e($settings['signature_path']) ?></code></div>
      <?php endif; ?>
    </div>

    <div class="col-12">
      <div class="label">Custom CSS (opsional)</div>
      <textarea class="input" name="custom_css" placeholder="Tulis CSS di sini..."><?= e($settings['custom_css'] ?? '') ?></textarea>
      <div class="muted" style="margin-top:6px">Tip: cukup override variabel :root atau class tertentu. Tidak perlu edit file di luar.</div>
    </div>
    <div class="col-12">
      <div class="label">Card CSS (Kartu Berobat - opsional)</div>
      <textarea class="input" name="card_css" placeholder="CSS khusus kartu berobat..."><?= e($settings['card_css'] ?? '') ?></textarea>
      <div class="muted" style="margin-top:6px">CSS ini hanya diterapkan pada halaman kartu berobat (KTP). Anda bisa ubah warna tema, font, dan layout tanpa edit file.</div>
    <div class="col-12">
      <div class="label">QR Verifikasi Dokumen (opsional)</div>
      <div class="muted">QR akan dibuat dari link verifikasi publik. Jika internet kantor tidak stabil, Anda dapat mengganti provider.</div>
    </div>

    <div class="col-6">
      <div class="label">QR Provider</div>
      <select class="input" name="qr_provider">
        <option value="qrserver" <?= (($settings['qr_provider'] ?? 'qrserver')==='qrserver') ? 'selected' : '' ?>>QRServer (api.qrserver.com)</option>
        <option value="quickchart" <?= (($settings['qr_provider'] ?? 'qrserver')==='quickchart') ? 'selected' : '' ?>>QuickChart (quickchart.io)</option>
      </select>
    </div>

    <div class="col-6">
      <div class="label">QR Size (px)</div>
      <input class="input" name="qr_size" value="<?= e($settings['qr_size'] ?? '180') ?>" placeholder="180">
      <div class="muted" style="margin-top:6px">Rekomendasi 160–220 untuk hasil print.</div>
    </div>

    </div>


    <div class="col-12" style="display:flex;justify-content:flex-end">
      <button class="btn" type="submit">Simpan</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>
