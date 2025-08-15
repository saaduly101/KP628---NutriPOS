<?php
header('Content-Type: application/json');
require_once __DIR__.'/afcd_search_lib.php';

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2){ echo json_encode(['results'=>[], 'query'=>$q]); exit; }

$rows = loadAFCDData(__DIR__.'/../data/afcd.csv'); // consistent path
$results = afcd_search($q, $rows, 20);
echo json_encode(['results'=>$results, 'query'=>$q], JSON_UNESCAPED_UNICODE);
