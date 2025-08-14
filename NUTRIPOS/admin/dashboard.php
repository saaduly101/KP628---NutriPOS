<?php
require_once __DIR__.'/../backend/auth.php';
auth_require_admin();
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"/><title>Admin</title></head>
<body>
  <h1>Admin Dashboard</h1>
  <p>Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?> | <a href="logout.php">Logout</a></p>
  <ul>
    <li><a href="orders.php">View Orders</a></li>
  </ul>
</body></html>
