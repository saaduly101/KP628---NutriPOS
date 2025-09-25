<?php
require_once __DIR__.'/../backend/auth.php';
auth_require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>NutriPOS – Custom Product Builder + POS Simulator</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <nav class="navbar">
    <div class="navbar-container">
      <div class="logo-dashboard"> 
        <a href="../admin/dashboard.php" class="logo">NutriPOS</a>
        <span class="admin-dashboard">Admin Dashboard</span>
      </div>
      <ul class="navbar-links">
        <li><a href="custom.php" class="nav-button active">Menu Builder</a></li>
        <li><a href="products.php" class="nav-button">Menu Management</a></li>
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

    <div class="builder-section">
      <div class="builder-left">
        <div class="nutripos-builder-container">
          <div class="button-container">
            <input id="productName" placeholder="Product name e.g. Beef Burger"/>
            <button id="addRow" class="btn ghost nutripos-add-ingredient-btn">+ Add Ingredient</button>
          </div>
          <table id="grid" class="grid under nutripos-ingredients-table">
            <thead>
              <tr>
                <th>Ingredient Name</th>
                <th>AFCD Code (optional)</th>
                <th>Notes</th>
                <th>Weight (g)</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id = "ingredients" class="ingredients">
              <!-- Ingredient rows will go here -->
            </tbody>
          </table>
          <div class="button-container nutripos-action-buttons">
            <button id="calcBtn" class="btn primary nutripos-btn-primary">Calculate Nutrition</button>
            <button id="saveBtn" class="btn primary nutripos-btn-primary">Save Product</button>
            <a class="btn ghost nutripos-btn-secondary" href="products.php">View Saved Products</a>
          </div>
        </div>
        <span class="muted">Tip: leave AFCD code empty and just type names—we'll smart-match.</span>

        <div id="qrcode" style="display: none;"></div>
      </div>

      <div class="builder-right">
        <div class="nutripos-builder-container sidebar">
          <h3>Nutrition Totals</h3>
          <div id="nutritionInfo">
            <div class="calculator-icon">
              <img src="../assets/calculator.png" alt="Calculator" style="width:48px; opacity:0.3;"/>
            </div>
            <div>
              Click "Calculate Nutrition" when all ingredients are added!<br>
              Nutrition values will appear here
            </div>
          </div>
          <div id="orderResult" style="margin-top:12px"></div>
          <div id="result" class="totals" style="display:none"></div>
        </div>
      </div>
    </div>
  <div class="hr"></div>

  <h2>POS Simulator (no payment)</h2>
  <p>Add customer email (optional) and “Create Order” to save, get a receipt link & QR. You can also email the receipt.</p>
  <div class="flex">
    <label for="custEmail" class="muted">Customer Email (optional):</label>
    <input id="custEmail" value="customer@example.com" placeholder="customer@example.com" style="flex:1;padding:8px"/>
    <button id="createOrderBtn" class="btn primary">Create Order</button>
  </div>

  </div>





  <script src="qrcode.js"></script>
  <script src="custom_pos_builder.js"></script>

  <script>
  function generateNutritionQR(totals){
    // Stringify nicely for scanning
    const payload = `
    Energy: ${totals["Energy (kJ)"]} kJ (${totals["Calories (kcal)"]} kcal)
    Protein: ${totals["Protein (g)"]} g
    Fat: ${totals["Fat (g)"]} g
    Carbohydrate: ${totals["Carbohydrate (g)"]} g
    Sugars: ${totals["Sugars (g)"]} g
    Sodium: ${totals["Sodium (mg)"]} mg
    `;
  }

  // Example usage after calculating:
  const totals = {
    "Energy (kJ)": 4221.7,
    "Calories (kcal)": 1009.0,
    "Protein (g)": 35.91,
    "Fat (g)": 84.43,
    "Carbohydrate (g)": 27.29,
    "Sugars (g)": 12.04,
    "Sodium (mg)": 836
  };
  generateNutritionQR(totals);

  </script>

  </body>
</html>
