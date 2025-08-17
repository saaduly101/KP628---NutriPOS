<?php
require_once __DIR__.'/../backend/auth.php';
auth_require_admin();
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8"/><title>Admin</title>
    <link rel="stylesheet" href="../public/style.css" />
  </head>
  <body>
    <nav class="navbar">
      <div class="navbar-container">
        <div class="logo-dashboard"> 
          <a href="#" class="logo">NutriPOS</a>
          <span class="admin-dashboard">Admin Dashboard</span>
        </div>
        <ul class="navbar-links">
          <li><a href="../public/custom_pos_builder.html" class="nav-button">Menu Builder</a></li>
          <li><a href="../public/products.html" class="nav-button">Menu Management</a></li>
          <li><a href="orders.php" class="nav-button">Order History</a></li>
        </ul>
        <div class="user-section">
          <span class="admin"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
          <a href="logout.php"><button class="logout-btn">Logout</button></a>
        </div>
      </div>
    </nav>

    <h1>Admin Dashboard</h1>
    <p>Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?> | <a href="logout.php">Logout</a></p>
    <ul>
      <li><a href="orders.php">View Orders</a></li>
    </ul>
  </body>
</html>
