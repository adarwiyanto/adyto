<?php

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function config(): array {
  static $cfg = null;
  if ($cfg === null) {
    $file = __DIR__ . '/config.php';
    if (!file_exists($file)) {
      // fallback to sample
      $cfg = require __DIR__ . '/config.sample.php';
    } else {
      $cfg = require $file;
    }
  }
  return $cfg;
}

function base_path(): string {
  $bp = config()['base_path'] ?? '';
  if ($bp === '') return '';
  return rtrim($bp, '/');
}

function url(string $path = ''): string {
  $bp = base_path();
  $path = '/' . ltrim($path, '/');
  return $bp . $path;
}

function redirect(string $path): void {
  header('Location: ' . url($path));
  exit;
}

function ensure_dirs(): void {
  $cfg = config();
  foreach (['paths.logs','paths.backups','uploads.logo_dir','uploads.signature_dir'] as $k) {
    $parts = explode('.', $k);
    $v = $cfg;
    foreach ($parts as $p) $v = $v[$p] ?? null;
    if ($v && !is_dir($v)) @mkdir($v, 0775, true);
  }
}

function log_app(string $level, string $message, array $context = []): void {
  $cfg = config();
  $dir = $cfg['paths']['logs'] ?? (__DIR__ . '/../storage/logs');
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
  $line = sprintf(
    "[%s] %-5s %s %s\n",
    date('Y-m-d H:i:s'),
    strtoupper($level),
    $message,
    $context ? json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : ''
  );
  @file_put_contents($file, $line, FILE_APPEND);
}

function csrf_token(): string {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['_csrf'];
}

function csrf_validate(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
      http_response_code(419);
      echo "CSRF token invalid.";
      exit;
    }
  }
}

function flash_set(string $key, string $msg): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $_SESSION['_flash'][$key] = $msg;
}

function flash_get(string $key): ?string {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $msg = $_SESSION['_flash'][$key] ?? null;
  if (isset($_SESSION['_flash'][$key])) unset($_SESSION['_flash'][$key]);
  return $msg;
}

function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

function input(string $key, $default = '') {
  return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function now_dt(): string { return date('Y-m-d H:i:s'); }

function age_from_dob(?string $dob): ?int {
  if (!$dob) return null;
  try {
    $d = new DateTime($dob);
    $n = new DateTime();
    return (int)$n->diff($d)->y;
  } catch (Throwable $e) { return null; }
}
