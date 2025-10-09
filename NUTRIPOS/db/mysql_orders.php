<?php

require '../../vendor/autoload.php';
require_once __DIR__.'/../backend/auth.php';
auth_require_admin();

use Dotenv\Dotenv;

// Load the .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Create Database Connection
$conn = mysqli_connect($_ENV['DB_SERVERNAME'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_NAME']);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// get all orders
$sql = "
  SELECT
    o.id,
    o.closed_at,
    o.total,
    DATE(o.closed_at) AS order_date,
    COALESCE(SUM(oli.quantity), 0) AS item_count
  FROM orders o
  LEFT JOIN order_line_items oli ON o.id = oli.order_id
  GROUP BY o.id
  ORDER BY o.closed_at DESC
";

$result = $conn->query($sql);
$orders = [];
$total_orders = 0;
$total_revenue = 0;

// Group orders by date and calculate total 
while ($row = $result->fetch_assoc()) {
  $date = $row['order_date'];
  if (!isset($orders[$date])) {
      $orders[$date] = [];
  }
  $orders[$date][] = $row;
  $total_orders++;
  $total_revenue += (float)$row['total'];
}

//Date format helper
function formatDate($date) {
  $today = date('Y-m-d');
  $yesterday = date('Y-m-d', strtotime('-1 day'));
  
  if ($date == $today) return 'Today';
  if ($date == $yesterday) return 'Yesterday';
  
  return date('l, F j, Y', strtotime($date));
}

//Time format
function formatTime($datetime) {
  return date('h:i A', strtotime($datetime));
}

// calculate daily revenue
function getDailyStats($dayOrders) {
  $orderCount = count($dayOrders);
  $itemsSold = 0;
  $dayTotal = 0;
  
  foreach ($dayOrders as $order) {
      $itemsSold += $order['item_count'];
      $dayTotal += (float)$order['total'];
  }
  
  return [
      'orders' => $orderCount,
      'items' => $itemsSold,
      'total' => $dayTotal
  ];
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NutriPOS – Order History</title>
  <link rel="stylesheet" href="../public/style.css" />
</head>
<body>
  <nav class="navbar">
    <div class="navbar-container">
      <div class="logo-dashboard"> 
        <a href="../admin/dashboard.php" class="logo">NutriPOS</a>
        <span class="admin-dashboard">Admin Dashboard</span>
      </div>
      <ul class="navbar-links">
        <li><a href="../public/custom_pos_builder.php" class="nav-button">Menu Builder</a></li>
        <li><a href="../public/products.php" class="nav-button">Menu Management</a></li>
        <li><a href="../db/mysql_orders.php" class="nav-button active">Order History</a></li>
      </ul>
      <div class="user-section">
        <span class="admin"><?php echo htmlspecialchars($_SESSION['email'] ?? 'admin'); ?></span>
        <a href="../admin/logout.php"><button class="logout-btn">Logout</button></a>
      </div>
    </div>
  </nav>

  <div class="main-container">
    <div class="header">
      <div class="title-section">
        <h2>Order History</h2>
        <div class="subtitle">Daily sales and transaction records</div>
      </div>
      <div class="stats">
        <div class="stat-box total-orders">
          <div class="stat-label">Total Orders</div>
          <div class="stat-value total-orders"><?php echo $total_orders; ?></div>
        </div>
        <div class="stat-box">
          <div class="stat-label">Total Revenue</div>
          <div class="stat-value revenue">$<?php echo number_format($total_revenue, 2); ?></div>
        </div>
      </div>
    </div>

    <div class="nutripos-builder-container">
      <?php if (empty($orders)): ?>
        <div class="no-orders">No orders yet.</div>
      <?php else: ?>
        <?php foreach ($orders as $date => $dayOrders): ?>
            <?php 
              $stats = getDailyStats($dayOrders);
              $isToday = ($date == date('Y-m-d'));
            ?>
            <div class="day-section <?php echo $isToday ? 'open' : ''; ?>">
              <div class="day-header" onclick="toggleDay(this.parentElement)">
                <div class="day-left">
                  <div class="arrow"></div>
                  <div class="day-info">
                    <h3><?php echo formatDate($date); ?></h3>
                    <div class="day-meta">
                      <?php echo $stats['orders']; ?> <?php echo $stats['orders'] == 1 ? 'order' : 'orders'; ?> · 
                      <?php echo $stats['items']; ?> <?php echo $stats['items'] == 1 ? 'item sold' : 'items sold'; ?>
                    </div>
                  </div>
                </div>
                <div class="day-total">
                  $<?php echo number_format($stats['total'], 2); ?> 
                  <p class="day-meta">Daily Total</p>
                </div>
              </div>
              <div class="orders-list">
                <?php foreach ($dayOrders as $order): ?>
                  <a href="../order.php?order=<?php echo urlencode($order['id']); ?>" class="order-item">
                    <div class="order-left">
                      <div class="order-icon">🧾</div>
                      <div class="order-details">
                        <h4>Order #<?php echo htmlspecialchars($order['id']); ?></h4>
                        <div class="order-sub">
                          <?php echo $order['item_count']; ?> <?php echo $order['item_count'] == 1 ? 'item' : 'items'; ?> · 
                          <?php echo formatTime($order['closed_at']); ?>
                        </div>
                      </div>
                    </div>
                    <div class="order-right">
                      <div class="order-total">$<?php echo number_format((float)$order['total'], 2); ?></div>
                      <div class="payment-method">Card</div>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function toggleDay(element) {
      element.classList.toggle('open');
    }
  </script>
</body>
</html>