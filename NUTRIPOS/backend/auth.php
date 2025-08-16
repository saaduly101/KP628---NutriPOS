<?php
// backend/auth.php
require_once __DIR__.'/db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function auth_login(string $email, string $password): bool {
  $email = trim($email);
  $password = (string)$password;

  if ($email === '' || $password === '') return false;

  $pdo = pdo();
  $stmt = $pdo->prepare('SELECT id, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
  $stmt->execute([':email' => $email]);
  $user = $stmt->fetch();

  if (!$user) return false;

  if (!password_verify($password, $user['password_hash'])) {
    return false;
  }

  // Optional: rehash when algorithm changes
  if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $new = password_hash($password, PASSWORD_DEFAULT);
    $upd = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
    $upd->execute([':h'=>$new, ':id'=>$user['id']]);
  }

  $_SESSION['uid']  = (int)$user['id'];
  $_SESSION['role'] = $user['role'];
  $_SESSION['email']= $user['email'];
  return true;
}

function auth_require_admin(): void {
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (!isset($_SESSION['uid'])) {
    header('Location: ../admin/login.php');
    exit;
  }
}

function auth_logout(): void {
  if (session_status() === PHP_SESSION_NONE) session_start();
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
  session_destroy();
}
