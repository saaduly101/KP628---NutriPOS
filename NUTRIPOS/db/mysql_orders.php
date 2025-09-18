<?php
// NUTRIPOS/db/mysql_orders.php — Order History (title left, stats right; collapsible day groups)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require '../../vendor/autoload.php';
use Dotenv\Dotenv;

/** Load env */
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

/** DB connection */
$conn = mysqli_connect($_ENV['DB_SERVERNAME'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_NAME']);
if (!$conn) { http_response_code(500); exit("Connection failed: " . mysqli_connect_error()); }

/**
 * Fetch orders with per-order items count.
 */
$sql = "
  SELECT 
    o.id,
    o.closed_at,
    o.total,
    DATE(o.closed_at) AS day_key,
    (
      SELECT COALESCE(SUM(oli.quantity), 0)
      FROM order_line_items oli
      WHERE oli.order_id = o.id
    ) AS items_count
  FROM orders o
  ORDER BY o.closed_at DESC
";
$res = $conn->query($sql);
$orders = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

/** Group by day and compute per-day summary + global summary */
$groups = [];           // day_key => ['orders'=>[], 'orders_count'=>X, 'items_sold'=>Y, 'day_total'=>Z]
$total_orders = 0;
$total_revenue = 0.0;

foreach ($orders as $row) {
  $day = $row['day_key'];
  if (!isset($groups[$day])) {
    $groups[$day] = ['orders'=>[], 'orders_count'=>0, 'items_sold'=>0, 'day_total'=>0.0];
  }
  $groups[$day]['orders'][] = $row;
  $groups[$day]['orders_count'] += 1;
  $groups[$day]['items_sold']  += (int)($row['items_count'] ?? 0);
  $groups[$day]['day_total']   += (float)$row['total'];

  $total_orders += 1;
  $total_revenue += (float)$row['total'];
}

/** Helpers */
function day_label($dayStr) {
  $day = new DateTime($dayStr);
  $today = new DateTime('today');
  $yesterday = (new DateTime('today'))->modify('-1 day');
  if ($day->format('Y-m-d') === $today->format('Y-m-d')) return 'Today';
  if ($day->format('Y-m-d') === $yesterday->format('Y-m-d')) return 'Yesterday';
  return $day->format('l, F j, Y'); // e.g., Monday, September 15, 2025
}
function is_today($dayStr) {
  return (new DateTime($dayStr))->format('Y-m-d') === (new DateTime('today'))->format('Y-m-d');
}

/** Close DB */
mysqli_close($conn);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>NutriPOS – Order History</title>
  <link rel="stylesheet" href="../public/style.css" />
  <style>
    /* Page shell */
    .page-wrap { max-width: 1120px;  margin: 120px auto 24px auto; padding: 0 16px; }
    .subtle { color:#6b7280; font-size:13px; margin-top:4px; }

    /* Header row: title on left, stats on right */
    .header-bar {
      display:flex;
      justify-content:space-between; /* left-right dispersion */
      align-items:center;            /* On the same level as the title */
      gap:16px;
      margin-bottom:16px;
    }
    .header-left h2 { margin:0; }
    .header-left .subtle { margin-top:4px; }

    .stats-bar { display:flex; gap:12px; }
    .stat-card {
      min-width:140px;
      background:#f9fafb;
      border:1px solid #e5e7eb;
      border-radius:10px;
      padding:8px 12px;
      text-align:center;
    }
    .stat-card .label { color:#6b7280; font-size:12px; }
    .stat-card .value { font-weight:700; }
    .green { color:#10b981; }

    /* Day group (accordion) */
    .day-group { background:#fff; border:1px solid #e5e7eb; border-radius:12px; margin-bottom:14px; overflow:hidden; }
    .day-header {
      display:flex; justify-content:space-between; align-items:center; gap:12px;
      padding:12px 14px; background:#f9fafb; cursor:pointer; user-select:none;
    }
    .day-left { display:flex; align-items:center; gap:10px; }
    .caret {
      width: 0; height: 0; border-left:6px solid transparent; border-right:6px solid transparent;
      border-top:8px solid #6b7280; transition: transform .15s ease;
    }
    .day-group.open .caret { transform: rotate(180deg); } /* up when open */
    .day-title { font-weight:600; }
    .day-meta { color:#6b7280; font-size:13px; }
    .day-total { color:#10b981; font-weight:700; white-space:nowrap; }

    /* Orders list (rows) */
    .orders-wrap { overflow:hidden; }
    .orders-list { padding:6px; display:none; }
    .day-group.open .orders-list { display:block; }

    .order-row {
      display:flex; justify-content:space-between; align-items:center;
      background:#fff; border:1px solid #eef2f7; border-radius:10px;
      padding:10px 12px; margin:8px 6px; text-decoration:none; color:inherit;
      transition: box-shadow .15s ease, transform .05s ease;
    }
    .order-row:hover { box-shadow:0 2px 10px rgba(0,0,0,0.06); transform: translateY(-1px); }
    .order-left { display:flex; align-items:center; gap:12px; min-width:0; }
    .order-icon { width:24px; height:24px; border-radius:6px; background:#eef2ff; color:#4f46e5; display:flex; align-items:center; justify-content:center; font-weight:700; }
    .order-info { min-width:0; }
    .order-title { font-weight:600; }
    .order-sub { color:#6b7280; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    .order-right { text-align:right; }
    .order-total { color:#10b981; font-weight:700; }
    .order-pay { color:#6b7280; font-size:12px; }

    /* Hide old table if any */
    table { display:none; }
  </style>
</head>
<body>
  <!-- Navbar -->
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
        <span class="admin"><?= htmlspecialchars($_SESSION['email'] ?? 'admin') ?></span>
        <a href="../logout.php"><button class="logout-btn">Logout</button></a>
      </div>
    </div>
  </nav>

  <main class="page-wrap">
    <!-- Title (left) + Stats (right) in one row -->
    <div class="header-bar">
      <div class="header-left">
        <h2>Order History</h2>
        <div class="subtle">Daily sales and transaction records</div>
      </div>
      <div class="stats-bar">
        <div class="stat-card">
          <div class="label">Total Orders</div>
          <div class="value"><?= (int)$total_orders ?></div>
        </div>
        <div class="stat-card">
          <div class="label">Total Revenue</div>
          <div class="value green">$<?= number_format($total_revenue, 2) ?></div>
        </div>
      </div>
    </div>

    <!-- Day groups -->
    <?php foreach ($groups as $dayKey => $g): ?>
      <?php $open = is_today($dayKey); ?>
      <section class="day-group<?= $open ? ' open' : '' ?>" data-day="<?= htmlspecialchars($dayKey) ?>">
        <div class="day-header" role="button" tabindex="0" aria-expanded="<?= $open ? 'true' : 'false' ?>">
          <div class="day-left">
            <div class="caret" aria-hidden="true"></div>
            <div>
              <div class="day-title"><?= htmlspecialchars(day_label($dayKey)) ?></div>
              <div class="day-meta">
                <?= (int)$g['orders_count'] ?> <?= $g['orders_count'] == 1 ? 'order' : 'orders' ?> ·
                <?= (int)$g['items_sold'] ?> <?= $g['items_sold'] == 1 ? 'item sold' : 'items sold' ?>
              </div>
            </div>
          </div>
          <div class="day-total">$<?= number_format($g['day_total'], 2) ?> <span class="day-meta">Daily Total</span></div>
        </div>

        <div class="orders-wrap" aria-hidden="<?= $open ? 'false' : 'true' ?>">
          <div class="orders-list">
            <?php foreach ($g['orders'] as $o): ?>
              <?php
                $timeStr = (new DateTime($o['closed_at']))->format('h:i A');
                $items   = (int)($o['items_count'] ?? 0);
              ?>
              <a class="order-row" href="../order.php?order=<?= urlencode($o['id']) ?>">
                <div class="order-left">
                  <div class="order-icon">▣</div>
                  <div class="order-info">
                    <div class="order-title">Order #<?= htmlspecialchars($o['id']) ?></div>
                    <div class="order-sub"><?= $items ?> <?= $items === 1 ? 'item' : 'items' ?> · <?= htmlspecialchars($timeStr) ?></div>
                  </div>
                </div>
                <div class="order-right">
                  <div class="order-total">$<?= number_format((float)$o['total'], 2) ?></div>
                  <div class="order-pay">Card</div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endforeach; ?>

    <?php if (empty($groups)): ?>
      <p class="subtle">No orders yet.</p>
    <?php endif; ?>
  </main>

  <script>
    // Accordion toggle
    document.querySelectorAll('.day-group').forEach(function(group) {
      var header = group.querySelector('.day-header');
      var wrap = group.querySelector('.orders-wrap');

      function toggle() {
        var isOpen = group.classList.toggle('open');
        header.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        wrap.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
      }

      header.addEventListener('click', toggle);
      header.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
      });
    });
  </script>
</body>
</html>
