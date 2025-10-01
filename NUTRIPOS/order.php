<?php

require '../vendor/autoload.php';
require_once __DIR__.'../backend/auth.php';

auth_require_admin();

use Dotenv\Dotenv;

// Load the .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Create Database Connection
$conn = mysqli_connect($_ENV['DB_SERVERNAME'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_NAME']);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// prepaare the variable
$order_data = null;
$line_items = [];
$error_message = null;


// Check if 'order' parameter exists
if (isset($_GET['order'])) {
    $order = $_GET['order'];

    $result = $conn->query("SELECT 
        o.id AS order_id, 
        TIME(o.closed_at) AS order_time,
        DATE(o.closed_at) AS order_date, 
        o.total, 
        oli.id AS line_item_id,
        li.line_item_catalog_object_id, 
        li.name AS line_item_name, 
        li.variation_name,
        cm.sku AS sku,
        oli.quantity AS line_item_quantity, 
        m.name AS modifier_name, 
        olim.quantity AS modifier_quantity 
    FROM orders o 
    JOIN order_line_items oli ON o.id = oli.order_id 
    JOIN line_items li ON oli.line_item_catalog_object_id = li.line_item_catalog_object_id 
    LEFT JOIN order_line_item_modifiers olim ON oli.id = olim.order_line_item_id 
    LEFT JOIN modifiers m ON olim.modifier_id = m.id
    LEFT JOIN catalog_map cm ON cm.catalog_object_id = li.line_item_catalog_object_id
    WHERE o.id ='" . $order . "'
    ORDER BY oli.id, m.name"
    );

    
    $current_line_item = null;
    $line_item_index = -1;

    //Date format helper
    function formatDate($date) {
        return date('l, F j, Y', strtotime($date));
    }

    //Time format
    function formatTime($datetime) {
        return date('h:i A', strtotime($datetime));
    }
    
    while ($row = $result->fetch_assoc()) {
        if ($order_data === null) {
            $order_data = [
                'id' => $row['order_id'],
                'order_date' => formatDate($row['order_date']),
                'order_time' => formatTime($row['order_time']),
                'total' => $row['total'],
            ];
        }

        // If this is a new line item
        if ($current_line_item !== $row['line_item_id']) {  
            $current_line_item = $row['line_item_id'];
            $line_item_index++;
        
            $line_items[$line_item_index] = [
                'line_item_id'   => (int)$row['line_item_id'],
                'catalog_id'     => $row['line_item_catalog_object_id'],
                'name'           => $row['line_item_name'],
                'variation_name' => $row['variation_name'],
                'sku'            => $row['sku'] ?? null,
                'quantity'       => (int)$row['line_item_quantity'],
                'modifiers'      => []
            ];
        }
        
        // Add modifier if it exists
        if (!empty($row['modifier_name'])) {
            $line_items[$line_item_index]['modifiers'][] = [
                'name' => $row['modifier_name'],
                'quantity' => (int)($row['modifier_quantity'] ?? 1)
            ];
        }
    }
    
    if ($order_data === null) {
        $error_message = "Order not found.";
    }
} else {
    $error_message = "No order ID provided.";
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details</title>
    <link rel="stylesheet" href="./public/style.css">
</head>
<body>
    <?php if ($error_message): ?>
        <p class="error"><?php echo $error_message; ?></p>
    <?php else: ?>

    <nav class="navbar">
        <div class="navbar-container">
          <div class="logo-dashboard"> 
            <a href="../admin/dashboard.php" class="logo">NutriPOS</a>
            <span class="admin-dashboard">Admin Dashboard</span>
          </div>
          <ul class="navbar-links">
            <li><a href="./public/custom_pos_builder.php" class="nav-button">Menu Builder</a></li>
            <li><a href="./public/products.php" class="nav-button">Menu Management</a></li>
            <li><a href="./db/mysql_orders.php" class="nav-button active">Order History</a></li>
          </ul>
          <div class="user-section">
            <span class="admin"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
            <a href="./admin/logout.php"><button class="logout-btn">Logout</button></a>
          </div>
        </div>
    </nav>

    <div class="main-container" style="max-width: 960px"    >
        <?php if ($error_message): ?>
            <div class="order-error">
                <h2>❌ <?php echo htmlspecialchars($error_message); ?></h2>
                <a href="./db/mysql_orders.php" class="receipt-btn primary">← Back to Order History</a>
            </div>
        <?php else: ?>
            <div class="order-details-card">
                <!-- Order Header -->
                <div class="order-header">
                    <h2 class="order-title">📋 Order Details</h2>
                    <div class="order-meta">
                        <div class="meta-item">
                            <span class="meta-label">Order ID</span>
                            <span class="meta-value order-id-value"><?php echo htmlspecialchars($order_data['id']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Date & Time</span>
                            <span class="meta-value"><?php echo htmlspecialchars($order_data['order_date']); ?></span>
                            <span class="meta-value"><?php echo htmlspecialchars($order_data['order_time']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Payment Method</span>
                            <span class="meta-value">Card</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Status</span>
                            <span class="meta-value">Completed</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Total Amount</span>
                            <span class="meta-value" style="color: #10B981; font-size: 20px;">
                                <?php echo htmlspecialchars($order_data['total']); ?>
                            </span>
                        </div>
                    </div>
                    <hr>
                </div>
                
                <!-- Order Content -->
                <div class="order-content">
                    <h3 class="section-title">Order Items</h3>
                    
                    <?php if (empty($line_items)): ?>
                        <div class="no-items">
                            <p>No items found for this order.</p>
                        </div>
                    <?php else: ?>
                        <div class="items-list">
                            <?php foreach ($line_items as $item): ?>
                                <div class="line-item">
                                    <div class="item-main">
                                        <div class="item-details">
                                            <div class="item-name">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </div>
                                            <?php if (!empty($item['variation_name'])): ?>
                                                <div class="item-variation">
                                                    <?php echo htmlspecialchars($item['variation_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="item-quantity">
                                            x<?php echo htmlspecialchars($item['quantity']); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($item['modifiers'])): ?>
                                        <div class="item-modifiers">
                                            <?php foreach ($item['modifiers'] as $modifier): ?>
                                                <div class="modifier">
                                                    <span><?php echo htmlspecialchars($modifier['name']); ?></span>
                                                    <?php if ($modifier['quantity'] > 1): ?>
                                                        <span class="modifier-quantity">
                                                            <?php echo $modifier['quantity']; ?>x
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Order Summary -->
                    <div class="order-summary">
                        <div class="summary-row">
                            <span class="summary-label">Items (<?php echo count($line_items); ?>)</span>
                            <span class="summary-value">
                                <?php 
                                $total_items = 0;
                                foreach ($line_items as $item) {
                                    $total_items += $item['quantity'];
                                }
                                echo $total_items . ' items';
                                ?>
                            </span>
                        </div>

                        <div class="order-nutrition-card" id="orderNutrition">
                            <h3 class="section-title">Nutrition Summary</h3>
                            <div id="nutriContent" class="nutri-content"></div>
                        </div>

                        <div class="summary-row">
                            <span class="summary-label">Payment Method</span>
                            <span class="summary-value">Card Payment</span>
                        </div>
                        <div class="summary-row total">
                            <span class="summary-label">Total Amount</span>
                            <span class="summary-value">
                                <?php echo htmlspecialchars($order_data['total']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>


            <!-- print and return button -->
            <div class="receipt-actions">
                <button onclick="window.print()" class="receipt-btn primary">Print Receipt</button>
                <button onclick="window.print()" class="receipt-btn primary">Email Receipt</button>
                <button class="receipt-btn primary">Create a QR Code</button>
                <button class="receipt-btn primary" style="background-color: grey;"><a href="./db/mysql_orders.php" style="color: inherit; text-decoration: none;">← Back to Order History</a></button>
            </div>
        <?php endif; ?>
    </div>

<script>
(function () {
  // Get order id from the PHP-rendered span
  var idEl = document.querySelector('.order-id-value');
  if (!idEl) return;
  var id = (idEl.textContent || '').trim();
  if (!id) return;

  // format helpers
  function fmt(n, d) { n = Number(n); return isFinite(n) ? n.toFixed(d ?? 2) : '0'; }
  function show(html) {
    var card = document.getElementById('orderNutrition');
    var content = document.getElementById('nutriContent');
    if (card && content) {
      content.innerHTML = html;
      card.style.display = '';
    }
  }

  fetch('./backend/orders_get.php?id=' + encodeURIComponent(id) + '&t=' + Date.now(), { cache: 'no-store' })
    .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
    .then(function (data) {
      if (!data || data.error) throw new Error(data && data.error || 'No data');
      var o = data.order || {};
      
      var html = ''
        + '<div><strong>Energy:</strong> ' + fmt(o.energy_kj, 1) + ' kJ (' + fmt(o.calories_kcal, 0) + ' kcal)</div>'
        + '<div><strong>Protein:</strong> ' + fmt(o.protein_g) + ' g</div>'
        + '<div><strong>Fat:</strong> ' + fmt(o.fat_g) + ' g</div>'
        + '<div><strong>Carbs:</strong> ' + fmt(o.carb_g) + ' g</div>'
        + '<div><strong>Sugars:</strong> ' + fmt(o.sugars_g) + ' g</div>'
        + '<div><strong>Sodium:</strong> ' + fmt(o.sodium_mg, 0) + ' mg</div>';

      show(html);
    })
    .catch(function () {
      show('<div class="muted">Nutrition data not available.</div>');
    });
})();
</script>

</body>
</html>
<?php endif; ?>


