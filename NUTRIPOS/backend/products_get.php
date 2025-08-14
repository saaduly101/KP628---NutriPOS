<?php
header('Content-Type: application/json');
$id = $_GET['id'] ?? '';
$path = '../data/products.json';
$products = file_exists($path) ? json_decode(file_get_contents($path), true) : ['products'=>[]];
foreach ($products['products'] as $p) {
    if ($p['id'] === $id) { echo json_encode(['product'=>$p]); exit; }
}
echo json_encode(['error'=>'Not found']);
?>
