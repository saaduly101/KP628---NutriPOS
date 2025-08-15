<?php
// backend/db.php
function config(){
  static $cfg = null;
  if ($cfg === null){
    $base = __DIR__.'/../config/';
    $cfg = require $base.'config.php';
    $local = $base.'local.php';
    if (file_exists($local)){
      $cfg = array_replace_recursive($cfg, require $local);
    }
  }
  return $cfg;
}

function pdo(){
  static $pdo = null;
  if ($pdo) return $pdo;

  $c = config()['db'];
  if ($c['driver'] === 'mysql'){
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
  } else {
    throw new RuntimeException('Unsupported driver');
  }
  $pdo = new PDO($dsn, $c['user'], $c['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  return $pdo;
}
