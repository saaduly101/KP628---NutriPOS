<?php
// NUTRIPOS/order.php — Order detail page with the same navbar/look as mysql_orders.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;

/** Load env from project root */
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

/** DB connection */
$mysqli = mysqli_connect($_ENV['DB_SERVERNAME'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_NAME']);
if (!$mysqli) {
  http_response_code(500);
  exit("Database connection failed: " . mysqli_connect_error());
}

/** Require ?order= */
if (empty($_GET['order'])) {
  http_response_code(400);
  exit("Missing required parameter: order. Example: order.php?order=123");
}
$orderId = $_GET['order'];

/** Query order header + items + modifiers */
$sql = "
SELECT 
  o.id AS order_id,
  o.closed_at,
  o.total,
  li.line_item_catalog_object_id,
  li.name AS line_item_name,
  li.variation_name,
  oli.quantity AS line_item_quantity,
  m.name AS modifier_name,
  COALESCE(olim.quantity, 1) AS modifier_quantity
FROM orders o
JOIN order_line_items oli ON o.id = oli.order_id
JOIN line_items li ON oli.line_item_catalog_object_id = li.line_item_catalog_object_id
LEFT JOIN order_line_item_modifiers olim ON oli.id = olim.order_line_item_id
LEFT JOIN modifiers m ON olim.modifier_id = m.id
WHERE o.id = ?
ORDER BY li.line_item_catalog_object_id, m.name
";
$stmt = $mysqli->prepare($sql);
if (!$stmt) { http_response_code(500); exit("Prepare failed: " . $mysqli->error); }
$stmt->bind_param("s", $orderId);
if (!$stmt->execute()) { http_response_code(500); exit("Execute failed: " . $stmt->error); }
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  echo "<h2>No data</h2>Order not found: " . htmlspecialchars($orderId);
  exit;
}

/** Group items */
$rows   = $result->fetch_all(MYSQLI_ASSOC);
$header = $rows[0];
$grouped = [];
foreach ($rows as $r) {
  $key = $r['line_item_catalog_object_id'] ?: uniqid('li_', true);
  if (!isset($grouped[$key])) {
    $grouped[$key] = [
      'qty'       => $r['line_item_quantity'],
      'name'      => $r['line_item_name'],
      'variation' => $r['variation_name'],
      'mods'      => []
    ];
  }
  if (!empty($r['modifier_name'])) {
    $grouped[$key]['mods'][] = [
      'name' => $r['modifier_name'],
      'qty'  => (int)$r['modifier_quantity'] ?: 1
    ];
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>NutriPOS – Order Detail</title>

  <!-- IMPORTANT: from NUTRIPOS/ root to CSS in NUTRIPOS/public/ -->
  <link rel="stylesheet" href="public/style.css?v=1" />

  <!-- Page-only cosmetics (won't touch navbar styles) -->
  <style>
    .page-wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
    .card {
      background:#fff; border:1px solid #e5e7eb; border-radius:12px;
      padding:16px 20px; margin-bottom:20px;
    }
    .muted { color:#6b7280; }
    .idmono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
    .item{ padding:10px 0; border-bottom:1px dashed #e5e7eb; }
    .mods{ margin-left:16px; }
    .dot{ margin-right:6px; }
  </style>
</head>
<body>
  <!-- Navbar: EXACTLY the same structure/classes as the list page -->
  <nav class="navbar">
    <div class="navbar-container">
      <div class="logo-dashboard">
        <a href="#" class="logo">NutriPOS</a>
        <span class="admin-dashboard">Admin Dashboard</span>
      </div>
      <ul class="navbar-links">
        <li><a href="public/custom_pos_builder.html" class="nav-button">Menu Builder</a></li>
        <li><a href="public/products.html" class="nav-button">Menu Management</a></li>
        <li><a href="db/mysql_orders.php" class="nav-button">Order History</a></li>
      </ul>
      <div class="user-section">
        <span class="admin"><?= htmlspecialchars($_SESSION['email'] ?? 'Admin') ?></span>
        <a href="logout.php"><button class="logout-btn">Logout</button></a>
      </div>
    </div>
  </nav>

  <div class="page-wrap">
    <div class="card">
      <h1>Order <span class="idmono">#<?= htmlspecialchars($header['order_id']) ?></span></h1>
      <div class="muted">
        Time: <?= htmlspecialchars($header['closed_at'] ?? '') ?><br>
        Total: <?= htmlspecialchars($header['total'] ?? '$0.00') ?>
      </div>
    </div>

    <div class="card">
      <h2>Items</h2>
      <?php foreach ($grouped as $g): ?>
        <?php
          $line = htmlspecialchars($g['qty'] . 'x ' . $g['name']);
          if (!empty($g['variation'])) $line .= ' (' . htmlspecialchars($g['variation']) . ')';
        ?>
        <div class="item">
          <div><?= $line ?></div>
          <?php if (!empty($g['mods'])): ?>
            <div class="mods">
              <?php foreach ($g['mods'] as $m): ?>
                <?php $suffix = ($m['qty'] ?? 1) > 1 ? ' ('.(int)$m['qty'].'x)' : ''; ?>
                <div><span class="dot">•</span><?= htmlspecialchars($m['name']) . $suffix ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <p style="margin-top:10px;">
        <a href="db/mysql_orders.php">&larr; Back to orders</a>
      </p>
    </div>
  </div>
</body>
</html>
