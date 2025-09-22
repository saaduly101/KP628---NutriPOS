<?php
require_once __DIR__.'/../backend/auth.php';
auth_require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>NutriPOS – Saved Products</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    table { width:100%; border-collapse: collapse; }
    th, td { padding: 8px; border-bottom: 1px solid #eee; text-align:left; }
    .btn { padding:6px 10px; border:1px solid #ccc; border-radius:6px; background:#fff; cursor:pointer; }
    .btn.danger { border-color:#e33; color:#e33; }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="navbar-container">
      <div class="logo-dashboard"> 
        <a href="../admin/dashboard.php" class="logo">NutriPOS</a>
        <span class="admin-dashboard">Admin Dashboard</span>
      </div>
      <ul class="navbar-links">
        <li><a href="custom_pos_builder.php" class="nav-button">Menu Builder</a></li>
        <li><a href="products.php" class="nav-button active">Menu Management</a></li>
        <li><a href="../db/mysql_orders.php" class="nav-button">Order History</a></li>
      </ul>
      <div class="user-section">
        <span class="admin"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
        <a href="../admin/logout.php"><button class="logout-btn">Logout</button></a>
      </div>
    </div>
  </nav>

  <h2>NutriPOS – Saved Products</h2>
  <div class="nutripos-builder-container">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Ingredients</th>
          <th>Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="list">
        <tr>
          <td colspan="4">Loading...</td>
        </tr>
      </tbody>
    </table>

    <div class="button-container nutripos-action-buttons">
      <a href="custom_pos_builder.php" class="btn ghost nutripos-btn-secondary">+ Create New</a>
    </div>
  </div>  

  <script src="products.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      listProducts();
    });
  </script>
</body>
</html>
