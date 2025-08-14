<?php
require_once __DIR__.'/../backend/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  $ok = auth_login($_POST['email'] ?? '', $_POST['password'] ?? '');
  if ($ok){
    header(header: 'Location: dashboard.php');
    exit;
    
  }
  $error = "Invalid credentials";

}
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"/><title>Admin Login</title></head>
<body>
  <h1>Admin Login</h1>
  <?php if (!empty($error)) echo "<p style='color:red'>".htmlspecialchars($error)."</p>"; ?>
  <form method="post" autocomplete="username">
    <input name="email" placeholder="admin@example.com" value="admin@nutripos.local"/><br/>
    <input name="password" type="password" placeholder="Password" value="admin123" autocomplete="current-password"/><br/>
    <button type="submit">Login</button>
  </form>
</body></html>
