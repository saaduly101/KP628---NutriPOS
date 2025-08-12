<?php
header('Content-Type: application/json');
$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) { echo json_encode(['error'=>'Invalid JSON']); exit; }

$name = trim($payload['name'] ?? '');
$ingredients = $payload['ingredients'] ?? [];
$id = $payload['id'] ?? null;

if ($name === '' || count($ingredients) === 0) {
    echo json_encode(['error'=>'Name and at least one ingredient are required']); exit;
}

$path = '../data/products.json';
$products = file_exists($path) ? json_decode(file_get_contents($path), true) : ['products'=>[]];
if (!$products) $products = ['products'=>[]];

// Update or create
if ($id) {
    foreach ($products['products'] as &$p) {
        if ($p['id'] === $id) {
            $p['name'] = $name;
            $p['ingredients'] = $ingredients;
            $p['updated_at'] = gmdate('c');
            file_put_contents($path, json_encode($products, JSON_PRETTY_PRINT));
            echo json_encode(['product'=>$p]); exit;
        }
    }
}

// create new
$id = bin2hex(random_bytes(8));
$product = ['id'=>$id, 'name'=>$name, 'ingredients'=>$ingredients, 'updated_at'=>gmdate('c')];
$products['products'][] = $product;
file_put_contents($path, json_encode($products, JSON_PRETTY_PRINT));
echo json_encode(['product'=>$product]);
?>
