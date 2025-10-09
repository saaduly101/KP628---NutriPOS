<?php
require '../../db.php';
$pdo = pdo();
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users(email,password_hash,role) VALUES(?,?,?)")
    ->execute(['admin@nutripos.local',$hash,'admin']);
echo "done";
