<?php
require_once __DIR__ . '/helpers.php';

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $cfg = config()['db'];
  $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $cfg['host'], (int)$cfg['port'], $cfg['name'], $cfg['charset'] ?? 'utf8mb4'
  );

  $opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];

  try {
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $opt);
  } catch (Throwable $e) {
    log_app('error', 'DB connection failed', ['err' => $e->getMessage()]);
    throw $e;
  }
  return $pdo;
}

function db_exec(string $sql, array $params = []): void {
  $stmt = db()->prepare($sql);
  $stmt->execute($params);
}
