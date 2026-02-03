<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

function session_boot(): void {
  $cfg = config();
  if (session_status() === PHP_SESSION_NONE) {
    session_name($cfg['security']['session_name'] ?? 'AMSSESSID');
    session_start();
  }
}

function auth_user(): ?array {
  session_boot();
  return $_SESSION['user'] ?? null;
}

function auth_require(): void {
  if (!auth_user()) redirect('/login.php');
}

function auth_require_role(array $roles): void {
  auth_require();
  $u = auth_user();
  if (!$u || !in_array($u['role'], $roles, true)) {
    http_response_code(403);
    echo "Forbidden.";
    exit;
  }
}

function auth_login(string $username, string $password): bool {
  session_boot();
  $stmt = db()->prepare("SELECT id, username, password_hash, role, full_name FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
  $stmt->execute([$username]);
  $u = $stmt->fetch();
  if (!$u) return false;
  if (!password_verify($password, $u['password_hash'])) return false;

  session_regenerate_id(true);
  $_SESSION['user'] = [
    'id' => (int)$u['id'],
    'username' => $u['username'],
    'role' => $u['role'],
    'full_name' => $u['full_name'] ?: $u['username'],
  ];
  db_exec("INSERT INTO audit_logs(created_at, user_id, action, meta) VALUES(?,?,?,?)", [now_dt(), $u['id'], 'login', json_encode(['ip'=>$_SERVER['REMOTE_ADDR'] ?? ''], JSON_UNESCAPED_UNICODE)]);
  return true;
}

function auth_logout(): void {
  session_boot();
  $u = $_SESSION['user']['id'] ?? null;
  if ($u) db_exec("INSERT INTO audit_logs(created_at, user_id, action, meta) VALUES(?,?,?,?)", [now_dt(), $u, 'logout', '{}']);
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();
}
