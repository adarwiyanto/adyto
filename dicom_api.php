<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/dicom.php';

auth_require();

function dicom_json($payload, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function dicom_patient_exists(int $patientId): bool {
  $st = db()->prepare('SELECT id FROM patients WHERE id=? LIMIT 1');
  $st->execute([$patientId]);
  return (bool)$st->fetch();
}

function dicom_study_patient_id(int $studyId): ?int {
  $st = db()->prepare('SELECT patient_id FROM imaging_studies WHERE id=? LIMIT 1');
  $st->execute([$studyId]);
  $row = $st->fetch();
  return $row ? (int)$row['patient_id'] : null;
}

function dicom_series_patient_id(int $seriesId): ?int {
  $st = db()->prepare('SELECT s.patient_id FROM imaging_series se JOIN imaging_studies s ON s.id=se.study_id WHERE se.id=? LIMIT 1');
  $st->execute([$seriesId]);
  $row = $st->fetch();
  return $row ? (int)$row['patient_id'] : null;
}

function dicom_instance_patient_and_path(int $instanceId): ?array {
  $st = db()->prepare('SELECT s.patient_id, i.file_path, i.file_size FROM imaging_instances i JOIN imaging_series se ON se.id=i.series_id JOIN imaging_studies s ON s.id=se.study_id WHERE i.id=? LIMIT 1');
  $st->execute([$instanceId]);
  $row = $st->fetch();
  if (!$row) return null;
  return ['patient_id' => (int)$row['patient_id'], 'file_path' => $row['file_path'], 'file_size' => (int)$row['file_size']];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'upload_study' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();
  $patientId = (int)($_POST['patient_id'] ?? 0);
  if ($patientId <= 0 || !dicom_patient_exists($patientId)) {
    dicom_json(['ok' => false, 'error' => 'Patient tidak ditemukan'], 404);
  }

  if (!isset($_FILES['dicom_zip']) || !is_array($_FILES['dicom_zip'])) {
    dicom_json(['ok' => false, 'error' => 'File ZIP wajib diunggah'], 400);
  }

  $file = $_FILES['dicom_zip'];
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    dicom_json(['ok' => false, 'error' => 'Upload gagal'], 400);
  }

  $maxBytes = dicom_max_upload_bytes();
  if ((int)$file['size'] > $maxBytes) {
    dicom_json(['ok' => false, 'error' => 'Ukuran file melebihi batas'], 400);
  }

  $origName = $file['name'] ?? '';
  if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'zip') {
    dicom_json(['ok' => false, 'error' => 'Hanya file ZIP yang diizinkan'], 400);
  }

  $tmpZip = $file['tmp_name'];
  $zip = new ZipArchive();
  if ($zip->open($tmpZip) !== true) {
    dicom_json(['ok' => false, 'error' => 'ZIP tidak valid'], 400);
  }

  $storageBase = rtrim(dicom_storage_dir(), '/\\');
  $token = date('YmdHis') . '_' . bin2hex(random_bytes(6));
  $targetDir = $storageBase . DIRECTORY_SEPARATOR . $patientId . DIRECTORY_SEPARATOR . $token;
  if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
    log_app('error', 'Gagal membuat folder DICOM', ['dir' => $targetDir]);
    dicom_json(['ok' => false, 'error' => 'Storage tidak siap'], 500);
  }

  $inserted = 0;
  db()->beginTransaction();
  try {
    $studyMap = [];
    $seriesMap = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
      $stat = $zip->statIndex($i);
      if (!$stat) continue;
      $name = $stat['name'] ?? '';
      if ($name === '' || str_ends_with($name, '/')) continue;
      if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
        continue;
      }
      if (!dicom_is_allowed_filename($name)) continue;

      $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
      if ($safeName === '') $safeName = 'file_' . $i . '.dcm';
      $dest = $targetDir . DIRECTORY_SEPARATOR . $i . '_' . $safeName;

      $content = $zip->getFromIndex($i);
      if ($content === false) continue;
      if (strlen($content) > $maxBytes) continue;
      file_put_contents($dest, $content);

      if (!dicom_sniff_file($dest)) {
        @unlink($dest);
        continue;
      }

      $meta = dicom_parse_tags($dest);
      $studyUid = $meta['study_uid'] ?: 'STUDY_' . sha1((string)$patientId . '|' . $token);
      $seriesUid = $meta['series_uid'] ?: 'SERIES_' . sha1($studyUid . '|' . basename($dest));
      $sopUid = $meta['sop_instance_uid'] ?: 'SOP_' . sha1($seriesUid . '|' . basename($dest) . '|' . microtime(true));

      if (!isset($studyMap[$studyUid])) {
        $st = db()->prepare('SELECT id FROM imaging_studies WHERE study_uid=? LIMIT 1');
        $st->execute([$studyUid]);
        $row = $st->fetch();
        if ($row) {
          $studyId = (int)$row['id'];
        } else {
          $ins = db()->prepare('INSERT INTO imaging_studies(patient_id, study_uid, accession_number, study_date, modality, description, created_at) VALUES(?,?,?,?,?,?,?)');
          $ins->execute([
            $patientId,
            $studyUid,
            null,
            $meta['study_date'] ?: null,
            $meta['modality'] ?: null,
            $meta['study_description'] ?: null,
            now_dt(),
          ]);
          $studyId = (int)db()->lastInsertId();
        }
        $studyMap[$studyUid] = $studyId;
      }

      $studyId = $studyMap[$studyUid];
      $seriesKey = $studyId . ':' . $seriesUid;
      if (!isset($seriesMap[$seriesKey])) {
        $st = db()->prepare('SELECT id FROM imaging_series WHERE study_id=? AND series_uid=? LIMIT 1');
        $st->execute([$studyId, $seriesUid]);
        $row = $st->fetch();
        if ($row) {
          $seriesId = (int)$row['id'];
        } else {
          $ins = db()->prepare('INSERT INTO imaging_series(study_id, series_uid, modality, description, created_at) VALUES(?,?,?,?,?)');
          $ins->execute([$studyId, $seriesUid, $meta['modality'] ?: null, $meta['series_description'] ?: null, now_dt()]);
          $seriesId = (int)db()->lastInsertId();
        }
        $seriesMap[$seriesKey] = $seriesId;
      }
      $seriesId = $seriesMap[$seriesKey];

      $st = db()->prepare('SELECT id FROM imaging_instances WHERE sop_instance_uid=? LIMIT 1');
      $st->execute([$sopUid]);
      if ($st->fetch()) {
        @unlink($dest);
        continue;
      }

      $relPath = $patientId . '/' . $token . '/' . basename($dest);
      $ins = db()->prepare('INSERT INTO imaging_instances(series_id, sop_instance_uid, instance_number, file_path, file_size, created_at) VALUES(?,?,?,?,?,?)');
      $ins->execute([$seriesId, $sopUid, $meta['instance_number'] ?: null, $relPath, filesize($dest), now_dt()]);
      $inserted++;
    }

    $zip->close();
    db()->commit();

    if ($inserted === 0) {
      dicom_json(['ok' => false, 'error' => 'Tidak ada file DICOM valid di ZIP'], 400);
    }

    dicom_json(['ok' => true, 'message' => 'Upload berhasil', 'inserted' => $inserted]);
  } catch (Throwable $e) {
    $zip->close();
    if (db()->inTransaction()) db()->rollBack();
    log_app('error', 'Upload DICOM gagal', ['error' => $e->getMessage()]);
    dicom_json(['ok' => false, 'error' => 'Upload gagal diproses'], 500);
  }
}

if ($action === 'list_studies') {
  $patientId = (int)($_GET['patient_id'] ?? 0);
  if ($patientId <= 0 || !dicom_patient_exists($patientId)) dicom_json(['ok' => false, 'error' => 'Patient tidak ditemukan'], 404);

  $st = db()->prepare('SELECT id, patient_id, study_uid, accession_number, study_date, modality, description, created_at FROM imaging_studies WHERE patient_id=? ORDER BY created_at DESC');
  $st->execute([$patientId]);
  dicom_json(['ok' => true, 'data' => $st->fetchAll()]);
}

if ($action === 'list_series') {
  $studyId = (int)($_GET['study_id'] ?? 0);
  $patientId = dicom_study_patient_id($studyId);
  if (!$patientId) dicom_json(['ok' => false, 'error' => 'Study tidak ditemukan'], 404);

  $st = db()->prepare('SELECT id, study_id, series_uid, modality, description, created_at FROM imaging_series WHERE study_id=? ORDER BY id ASC');
  $st->execute([$studyId]);
  dicom_json(['ok' => true, 'data' => $st->fetchAll()]);
}

if ($action === 'list_instances') {
  $seriesId = (int)($_GET['series_id'] ?? 0);
  $patientId = dicom_series_patient_id($seriesId);
  if (!$patientId) dicom_json(['ok' => false, 'error' => 'Series tidak ditemukan'], 404);

  $st = db()->prepare('SELECT i.id, i.series_id, i.sop_instance_uid, i.instance_number, i.file_size, i.created_at, s.study_date, s.modality, s.description AS study_description, se.description AS series_description, p.full_name AS patient_name FROM imaging_instances i JOIN imaging_series se ON se.id=i.series_id JOIN imaging_studies s ON s.id=se.study_id JOIN patients p ON p.id=s.patient_id WHERE i.series_id=? ORDER BY COALESCE(i.instance_number,999999), i.id');
  $st->execute([$seriesId]);
  $items = $st->fetchAll();
  foreach ($items as &$item) {
    $item['image_id'] = 'wadouri:' . url('/dicom_api.php?action=wadouri&instance_id=' . (int)$item['id']);
  }
  dicom_json(['ok' => true, 'data' => $items]);
}

if ($action === 'wadouri') {
  $instanceId = (int)($_GET['instance_id'] ?? 0);
  $row = dicom_instance_patient_and_path($instanceId);
  if (!$row) {
    http_response_code(404);
    echo 'Not found';
    exit;
  }

  $base = realpath(dicom_storage_dir());
  $full = realpath(rtrim(dicom_storage_dir(), '/\\') . DIRECTORY_SEPARATOR . $row['file_path']);
  if (!$base || !$full || !str_starts_with($full, $base) || !is_file($full)) {
    http_response_code(404);
    echo 'Not found';
    exit;
  }

  header('Content-Type: application/dicom');
  header('Content-Length: ' . filesize($full));
  header('X-Content-Type-Options: nosniff');
  header('Content-Disposition: inline; filename="instance-' . $instanceId . '.dcm"');
  readfile($full);
  exit;
}

dicom_json(['ok' => false, 'error' => 'Action tidak dikenal'], 400);
