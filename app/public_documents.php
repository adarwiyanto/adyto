<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/controllers/common.php';

/**
 * Build absolute URL (scheme + host) for QR/verification links.
 * Falls back gracefully if executed from CLI.
 */
function absolute_url(string $path = ''): string {
  $path = '/' . ltrim($path, '/');
  $bp = base_path();
  $fullPath = $bp . $path;

  $host = $_SERVER['HTTP_HOST'] ?? '';
  if ($host === '') return $fullPath;

  $scheme = 'http';
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $scheme = 'https';
  // behind proxy
  $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
  if ($xfp) $scheme = explode(',', $xfp)[0];

  return $scheme . '://' . $host . $fullPath;
}

function public_document_get_or_create(string $docType, int $docId, string $docNo = ''): array {
  $docType = trim($docType);
  $docNo = trim($docNo);

  $st = db()->prepare("SELECT * FROM public_documents WHERE doc_type=? AND doc_id=? LIMIT 1");
  $st->execute([$docType, $docId]);
  $row = $st->fetch();
  if ($row) return $row;

  $token = bin2hex(random_bytes(16));
  $now = now_dt();

  $ins = db()->prepare("INSERT INTO public_documents(token, doc_type, doc_id, doc_no, created_at, revoked, revoked_at, note)
                        VALUES(?,?,?,?,?,0,NULL,NULL)");
  $ins->execute([$token, $docType, $docId, $docNo ?: null, $now]);

  $st2 = db()->prepare("SELECT * FROM public_documents WHERE doc_type=? AND doc_id=? LIMIT 1");
  $st2->execute([$docType, $docId]);
  return $st2->fetch() ?: ['token'=>$token,'doc_type'=>$docType,'doc_id'=>$docId,'doc_no'=>$docNo,'created_at'=>$now,'revoked'=>0];
}

function public_document_verify_url(string $token): string {
  return absolute_url('/verify.php?token=' . urlencode($token));
}

/**
 * QR image URL builder. Provider can be configured in settings:
 * - qr_provider: 'qrserver' (default) or 'quickchart'
 * - qr_size: integer (default 180)
 */
function public_document_qr_image_url(string $text, array $settings): string {
  $provider = strtolower(trim((string)($settings['qr_provider'] ?? 'qrserver')));
  $size = (int)($settings['qr_size'] ?? 180);
  if ($size <= 60) $size = 180;
  if ($size > 600) $size = 600;

  if ($provider === 'quickchart') {
    // https://quickchart.io/documentation/qr-codes/
    return 'https://quickchart.io/qr?size=' . $size . '&text=' . urlencode($text);
  }

  // Default: QRServer API (goqr.me)
  // https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=Example
  return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($text);
}

function public_document_log_access(int $publicDocId): void {
  try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $ua = $ua ? substr($ua, 0, 255) : null;
    db_exec("INSERT INTO public_document_access_logs(public_document_id, accessed_at, ip, user_agent) VALUES(?,?,?,?)",
      [$publicDocId, now_dt(), $ip, $ua]
    );
  } catch (Throwable $e) {
    // don't break verification page
    log_app('error', 'public_document_log_access failed', ['err'=>$e->getMessage()]);
  }
}
