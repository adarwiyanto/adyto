<?php
require_once __DIR__ . '/helpers.php';

function dicom_storage_dir(): string {
  $cfg = config();
  $dir = $cfg['uploads']['dicom_dir'] ?? (__DIR__ . '/../storage/uploads/dicom');
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  $htaccess = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.htaccess';
  if (!file_exists($htaccess)) {
    @file_put_contents($htaccess, "Deny from all\n");
  }
  return $dir;
}

function dicom_max_upload_bytes(): int {
  $cfg = config();
  $mb = (int)($cfg['uploads']['dicom_max_upload_mb'] ?? 100);
  if ($mb < 10) $mb = 10;
  return $mb * 1024 * 1024;
}

function dicom_is_allowed_filename(string $name): bool {
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  return in_array($ext, ['dcm', 'dicom', 'ima', ''], true);
}

function dicom_sniff_file(string $path): bool {
  if (!is_file($path)) return false;
  $fh = @fopen($path, 'rb');
  if (!$fh) return false;
  $head = fread($fh, 256);
  fclose($fh);
  if ($head === false) return false;
  if (strlen($head) >= 132 && substr($head, 128, 4) === 'DICM') return true;
  return preg_match('/[\x08\x10\x18\x20]/', $head) === 1;
}

function dicom_parse_tags(string $path): array {
  $result = [
    'study_uid' => null,
    'series_uid' => null,
    'sop_instance_uid' => null,
    'study_date' => null,
    'modality' => null,
    'study_description' => null,
    'series_description' => null,
    'instance_number' => null,
    'patient_name' => null,
    'patient_id' => null,
    'patient_birth_date' => null,
    'patient_sex' => null,
    'window_center' => null,
    'window_width' => null,
  ];

  $targetTags = [
    '00100010' => 'patient_name',
    '00100020' => 'patient_id',
    '00100030' => 'patient_birth_date',
    '00100040' => 'patient_sex',
    '00080020' => 'study_date',
    '00080060' => 'modality',
    '00081030' => 'study_description',
    '0020000D' => 'study_uid',
    '0020000E' => 'series_uid',
    '00080018' => 'sop_instance_uid',
    '00200013' => 'instance_number',
    '0008103E' => 'series_description',
    '00281050' => 'window_center',
    '00281051' => 'window_width',
  ];

  $raw = @file_get_contents($path, false, null, 0, 512000);
  if ($raw === false || strlen($raw) < 8) return $result;

  $offset = 0;
  if (strlen($raw) > 132 && substr($raw, 128, 4) === 'DICM') {
    $offset = 132;
  }

  $len = strlen($raw);
  while ($offset + 8 <= $len) {
    $group = unpack('v', substr($raw, $offset, 2))[1];
    $element = unpack('v', substr($raw, $offset + 2, 2))[1];
    $vr = substr($raw, $offset + 4, 2);
    $offset += 4;

    $longVr = in_array($vr, ['OB','OW','OF','SQ','UT','UN','OD','OL','UC','UR'], true);
    if (preg_match('/^[A-Z0-9]{2}$/', $vr) !== 1) {
      $length = unpack('V', substr($raw, $offset, 4))[1] ?? 0;
      $offset += 4;
    } elseif ($longVr) {
      $offset += 2;
      $length = unpack('V', substr($raw, $offset, 4))[1] ?? 0;
      $offset += 4;
    } else {
      $length = unpack('v', substr($raw, $offset, 2))[1] ?? 0;
      $offset += 2;
    }

    if ($length === 0xFFFFFFFF) break;
    if ($length < 0 || $offset + $length > $len) break;

    $tag = sprintf('%04X%04X', $group, $element);
    if (isset($targetTags[$tag])) {
      $value = trim(str_replace("\0", '', substr($raw, $offset, $length)));
      $field = $targetTags[$tag];
      if ($field === 'instance_number') {
        $result[$field] = is_numeric($value) ? (int)$value : null;
      } else {
        $result[$field] = $value !== '' ? $value : null;
      }

      if ($field === 'patient_birth_date' && $result[$field]) {
        $rawDob = preg_replace('/[^0-9]/', '', (string)$result[$field]);
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $rawDob, $m)) {
          $result[$field] = $m[1] . '-' . $m[2] . '-' . $m[3];
        } else {
          $result[$field] = null;
        }
      }

      if ($field === 'patient_sex' && $result[$field]) {
        $sx = strtoupper(substr((string)$result[$field], 0, 1));
        $result[$field] = ($sx === 'M') ? 'L' : (($sx === 'F') ? 'P' : null);
      }
    }

    $offset += $length;
  }

  return $result;
}
