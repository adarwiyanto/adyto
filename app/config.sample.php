<?php
return [
  'installed' => false,

  // base URL path, contoh: /AMS atau /praktek_mandiri (tanpa trailing slash)
  'base_path' => '/AMS',

  'db' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'praktek_db',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
  ],

  'security' => [
    'session_name' => 'AMSSESSID',
    'csrf_key' => 'change_me',
  ],

  'uploads' => [
    'logo_dir' => __DIR__ . '/../storage/uploads/logo',
    'signature_dir' => __DIR__ . '/../storage/uploads/signature',
  ],

  'paths' => [
    'logs' => __DIR__ . '/../storage/logs',
    'backups' => __DIR__ . '/../storage/backups',
  ],

  // Google Drive (opsional) - isi jika ingin upload backup
  'gdrive' => [
    'enabled' => false,
    'service_account_json' => __DIR__ . '/../storage/credentials/gdrive_service_account.json',
    'folder_id' => '',
  ],
];
