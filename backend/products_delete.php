<?php
header('Content-Type: application/json');
$id = $_GET['id'] ?? '';
$path = '../data/products.json';
$products = file_exists($path) ? json_decode(file_get_contents($path), true) : ['products'=>[]];
$products['products'] = array_values(array_filter($products['products'], function($p) use ($id){ return $p['id'] !== $id; }));
file_put_contents($path, json_encode($products, JSON_PRETTY_PRINT));
echo json_encode(['ok'=>true]);
?>
