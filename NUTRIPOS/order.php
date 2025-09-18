<?php

require '../vendor/autoload.php';

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
        o.closed_at, 
        o.total, 
        li.line_item_catalog_object_id, 
        li.name AS line_item_name, 
        li.variation_name,
        /* li.sku, */
        oli.quantity AS line_item_quantity, 
        m.name AS modifier_name, 
        olim.quantity AS modifier_quantity 
    FROM orders o 
    JOIN order_line_items oli ON o.id = oli.order_id 
    JOIN line_items li ON oli.line_item_catalog_object_id = li.line_item_catalog_object_id 
    LEFT JOIN order_line_item_modifiers olim ON oli.id = olim.order_line_item_id 
    LEFT JOIN modifiers m ON olim.modifier_id = m.id 
    WHERE o.id ='" . $order . "'");
    
    $current_line_item = null;
    $line_item_index = -1;
    
    while ($row = $result->fetch_assoc()) {
        if ($order_data === null) {
            $order_data = [
                'id' => $row['order_id'],
                'closed_at' => $row['closed_at'],
                'total' => $row['total'],
                'line_items' => []
            ];
        }

        // If this is a new line item
        if ($current_line_item !== $row['line_item_catalog_object_id']) {
            // Add a blank line between items
            $current_line_item = $row['line_item_catalog_object_id'];
            $line_item_index++;

            $line_items[$line_item_index] = [
                'catalog_id' => $row['line_item_catalog_object_id'],
                'name' => $row['line_item_name'],
                'variation_name' => $row['variation_name'],
                'quantity' => $row['line_item_quantity'],
                'modifiers' => []
            ];
        }
        
        // Add modifier if it exists
        if (!empty($row['modifier_name'])) {
            $line_items[$line_item_index]['modifiers'][] = [
                'name' => $row['modifier_name'],
                'quantity' => $row['modifier_quantity']
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
<DOCTYPE html>
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
            <li><a href="../public/custom_pos_builder.html" class="nav-button">Menu Builder</a></li>
            <li><a href="../public/products.html" class="nav-button">Menu Management</a></li>
            <li><a href="../db/mysql_orders.php" class="nav-button active">Order History</a></li>
          </ul>
          <div class="user-section">
            <span class="admin"><?php echo htmlspecialchars($_SESSION['email'] ?? 'admin'); ?></span>
            <a href="../logout.php"><button class="logout-btn">Logout</button></a>
          </div>
        </div>
    </nav>
    
    <div class="main-container" style="padding: 20px;">
        <?php if ($error_message): ?>
            <div class="order-error">
                <h2>❌ <?php echo htmlspecialchars($error_message); ?></h2>
                <a href="./db/mysql_orders.php" class="receipt-btn primary">← Back to Order History</a>
            </div>
        <?php else: ?>

            <!-- receipt  -->
            <div class="receipt-wrapper">
                <div class="receipt-paper">

                    <!-- Receipt Header -->
                    <div class="receipt-top">
                        <div class="receipt-logo">NutriPOS</div>
                        <div class="receipt-type">Order Receipt</div>
                        <div class="receipt-thanks">Thank you for your order!</div>
                    </div>

                    <!-- Order info -->
                    <div class="order-details">
                        <div>
                            <span>Order #:</span>
                            <span><?php echo htmlspecialchars($order_data['id']); ?></span>
                        </div>
                        <div>
                            <span>Date & Time:</span>
                            <span><?php echo $order_data['closed_at']; ?></span>
                        </div>
                    </div>

                    <!-- detail -->
                    <div class="receipt-items">
                        <div class="receipt-items-title">Items Ordered</div>
                        
                        <?php foreach ($line_items as $item): ?>
                            <div class="receipt-line-item">
                                <div class="item-primary">
                                    <span>
                                        <?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['name']); ?>
                                        <?php if (!empty($item['variation_name'])): ?>
                                            <div class="item-variant">(<?php echo htmlspecialchars($item['variation_name']); ?>)</div>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php foreach ($item['modifiers'] as $modifier): ?>
                                    <div class="item-addon">
                                        <span>+ <?php echo htmlspecialchars($modifier['name']); ?></span>
                                        <?php if ($modifier['quantity'] > 1): ?>
                                            <span>(<?php echo $modifier['quantity']; ?>x)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- total price -->
                    <div class="receipt-summary">
                        <div class="receipt-total">
                            <span>TOTAL:</span>
                            <span><?php echo htmlspecialchars($order_data['total']); ?></span>
                        </div>
                        
                        <div class="receipt-footer">
                            <div>Payment Method: Card</div>
                            <div class="receipt-divider">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</div>
                            <div class="receipt-farewell">Have a great day! 😊</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- print and return button -->
            <div class="receipt-actions">
                <button onclick="window.print()" class="receipt-btn primary">🖨️ Print Receipt</button>
                <a href="./db/mysql_orders.php" class="receipt-btn secondary">← Back to Order History</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php endif; ?>
