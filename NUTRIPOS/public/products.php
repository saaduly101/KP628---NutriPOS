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
</head>
<body>
  <nav class="navbar">
    <div class="navbar-container">
      <div class="logo-dashboard"> 
        <a href="../db/mysql_orders.php" class="logo">NutriPOS</a>
        <span class="admin-dashboard">Admin Dashboard</span>
      </div>
      <ul class="navbar-links">
        <li><a href="menu_management.php" class="nav-button">Menu Builder</a></li>
        <li><a href="products.php" class="nav-button active">Menu Management</a></li>
        <li><a href="../db/mysql_orders.php" class="nav-button">Order History</a></li>
      </ul>
      <div class="user-section">
        <span class="admin"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
        <a href="../admin/logout.php"><button class="logout-btn">Logout</button></a>
      </div>
    </div>
  </nav>

  <div class="main-container">
    <div class="header">
      <div class="title-section">
        <h2>NutriPOS – Menu Management</h2>
        <div class="subtitle">View, edit, or delete saved menu items.</div>
      </div>
    </div>

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
        <a href="menu_management.php" class="btn ghost nutripos-btn-secondary">+ Create New</a>
      </div>
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