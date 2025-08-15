<?php
header('Content-Type: application/json');
require_once __DIR__.'/db.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['error'=>'missing id']); exit; }

$pdo = pdo();
$o = $pdo->prepare("SELECT * FROM orders WHERE id = :id");
$o->execute([':id'=>$id]);
$order = $o->fetch();
if (!$order) { echo json_encode(['error'=>'not found']); exit; }

$it = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :id");
$it->execute([':id'=>$id]);
$items = $it->fetchAll();

echo json_encode(['order'=>$order, 'items'=>$items]);
