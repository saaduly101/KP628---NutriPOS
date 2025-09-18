<?php
// backend/auth.php
require_once __DIR__.'/db.php';


if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require __DIR__ . '/../../vendor/autoload.php';
use Dotenv\Dotenv;


function auth_login($email, $password) {
    
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

    $pdo = new PDO(
        "mysql:host={$_ENV['DB_SERVERNAME']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare('SELECT id, email, password_hash FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if (!$user || !password_verify($password, $user['password_hash'])) {
    return false;
  }

  $_SESSION['uid'] = (int)$user['id'];
  $_SESSION['email'] = $user['email'];
  return $user;
}

function auth_require_admin(): void {
  if (empty($_SESSION['uid'])) {
    header('Location: ../admin/login.php');
    exit;
  }
}

function auth_logout(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}