<?php
header('Content-Type: application/json');
$path = '../data/products.json';
$products = file_exists($path) ? json_decode(file_get_contents($path), true) : ['products'=>[]];
if (!$products) $products = ['products'=>[]];
echo json_encode($products);
?>
