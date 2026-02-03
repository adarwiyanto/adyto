<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../db.php';

function get_settings(): array {
  $rows = db()->query("SELECT `key`,`value` FROM settings")->fetchAll();
  $s = [];
  foreach ($rows as $r) $s[$r['key']] = $r['value'];
  return $s;
}

function set_setting(string $key, string $value): void {
  $stmt = db()->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
  $stmt->execute([$key,$value]);
}
