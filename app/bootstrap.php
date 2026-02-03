<?php
require_once __DIR__ . '/helpers.php';
ensure_dirs();

set_error_handler(function($severity, $message, $file, $line){
  log_app('error', 'PHP error', ['severity'=>$severity,'message'=>$message,'file'=>$file,'line'=>$line]);
  return false; // let default handler run too
});

set_exception_handler(function($e){
  log_app('error', 'Uncaught exception', ['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
  http_response_code(500);
  echo "<h3>Terjadi error.</h3><p>Silakan cek log di <code>storage/logs</code>.</p>";
});
