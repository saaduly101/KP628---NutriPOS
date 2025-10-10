<?php

require '../vendor/autoload.php';
require_once __DIR__.'../backend/auth.php';

auth_require_admin();

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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


$mailStatus = null;
// Calculate nutrition data using mysqli (same as main connection)
$nutritionData = null;
if ($order_data !== null && !empty($line_items)) {
    $orderTotals = [
        'Energy (kJ)' => 0, 'Calories (kcal)' => 0, 'Protein (g)' => 0,
        'Fat (g)' => 0, 'Carbohydrate (g)' => 0, 'Sugars (g)' => 0, 'Sodium (mg)' => 0
    ];

    foreach ($line_items as $item) {
        $catalogId = mysqli_real_escape_string($conn, $item['catalog_id']);

        // Get product mapping using mysqli
        $sql = "SELECT product_id, serve_multiplier FROM square_catalog_map WHERE catalog_object_id = '$catalogId'";
        $result = mysqli_query($conn, $sql);

        if ($result && ($mapping = mysqli_fetch_assoc($result))) {
            $productId = (int)$mapping['product_id'];
            $multiplier = (float)($mapping['serve_multiplier'] ?? 1.0);
            $quantity = (float)$item['quantity'];

            // Try to get cached nutrition totals first
            $sql = "SELECT energy_kj, calories_kcal, protein_g, fat_g, carb_g, sugars_g, sodium_mg FROM product_nutrition_totals WHERE product_id = $productId";
            $result = mysqli_query($conn, $sql);

            if ($result && ($cachedTotals = mysqli_fetch_assoc($result))) {
                // Use cached values
                foreach ($orderTotals as $key => $value) {
                    $fieldMap = [
                        'Energy (kJ)' => 'energy_kj',
                        'Calories (kcal)' => 'calories_kcal',
                        'Protein (g)' => 'protein_g',
                        'Fat (g)' => 'fat_g',
                        'Carbohydrate (g)' => 'carb_g',
                        'Sugars (g)' => 'sugars_g',
                        'Sodium (mg)' => 'sodium_mg'
                    ];

                    if (isset($cachedTotals[$fieldMap[$key]])) {
                        $orderTotals[$key] += (float)$cachedTotals[$fieldMap[$key]] * $quantity * $multiplier;
                    }
                }
                mysqli_free_result($result);
            } else {
                // Calculate from ingredients if no cached totals
                mysqli_free_result($result); // Free the cached totals result first
                $sql = "SELECT afcd_code, grams_per_unit FROM product_ingredients WHERE product_id = $productId";
                $result = mysqli_query($conn, $sql);
                $ingredients = mysqli_fetch_all($result, MYSQLI_ASSOC);

                if (!empty($ingredients)) {
                    require_once __DIR__ . '/backend/nutrition_lib.php';
                    if (function_exists('afcd_calc_totals')) {
                        $afcdItems = [];
                        foreach ($ingredients as $ing) {
                            $code = trim((string)($ing['afcd_code'] ?? ''));
                            $grams = (float)($ing['grams_per_unit'] ?? 0) * $multiplier * $quantity;
                            if ($code !== '' && $grams > 0) {
                                $afcdItems[] = ['afcd_code' => $code, 'grams' => $grams];
                            }
                        }

                        $calc = afcd_calc_totals($afcdItems);
                        $totals = $calc['totals'] ?? [];

                        foreach ($orderTotals as $key => $value) {
                            $orderTotals[$key] += (float)($totals[$key] ?? 0);
                        }
                    }
                }
                mysqli_free_result($result);
            }
        }
    }
    mysqli_close($conn);

    $nutritionData = [
        'id' => $order_data['id'],
        'created_at' => null,
        'energy_kj' => round($orderTotals['Energy (kJ)'], 1),
        'calories_kcal' => round($orderTotals['Calories (kcal)']),
        'protein_g' => round($orderTotals['Protein (g)'], 2),
        'fat_g' => round($orderTotals['Fat (g)'], 2),
        'carb_g' => round($orderTotals['Carbohydrate (g)'], 2),
        'sugars_g' => round($orderTotals['Sugars (g)'], 2),
        'sodium_mg' => round($orderTotals['Sodium (mg)']),
    ];
}

// Only send email when the Email Receipt button is clicked
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $mail = new PHPMailer(true); // Enable exceptions

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = $_ENV['EMAIL_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['EMAIL_USERNAME'];
        $mail->Password = $_ENV['EMAIL_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $_ENV['EMAIL_PORT'];

        // Sender and recipient settings
        $mail->setFrom($_ENV['EMAIL_USERNAME'], 'NutriPOS');
        $mail->addAddress($_POST['email']);
        $mail->addBCC($_ENV['EMAIL_BCC'], 'NutriPOS');


        // Sending HTML email
        $mail->isHTML(true);
        $mail->Subject = 'NutriPOS Order: ' . $order_data['id'];
        
        $itemsHtml = '';
        foreach ($line_items as $item) {
            $name = htmlspecialchars($item['name'] ?? '');
            $variation = !empty($item['variation_name']) ? ' (' . htmlspecialchars($item['variation_name']) . ')' : '';
            $qty = htmlspecialchars((string)($item['quantity'] ?? 0));

            $modifiersHtml = '';
            if (!empty($item['modifiers'])) {
                $modifiersHtml .= '<ul class="modifiers">';
                foreach ($item['modifiers'] as $modifier) {
                    $modName = htmlspecialchars($modifier['name'] ?? '');
                    $modQty = (int)($modifier['quantity'] ?? 1);
                    $qtySuffix = $modQty > 1 ? ' <span class="modifier-quantity">' . $modQty . 'x</span>' : '';
                    $modifiersHtml .= '<li>' . $modName . $qtySuffix . '</li>';
                }
                $modifiersHtml .= '</ul>';
            }

            $itemsHtml .= '<li>' . $name . $variation . ' <span class="item-quantity">x' . $qty . '</span>' . $modifiersHtml . '</li>';
        }

        $nutritionHtml = '';
        if ($nutritionData) {
            $nutritionHtml = <<<HTML
                    <h4>Nutrition Summary:</h4>
                    <ul>
                        <li><strong>Energy:</strong> {$nutritionData['energy_kj']} kJ ({$nutritionData['calories_kcal']} kcal)</li>
                        <li><strong>Protein:</strong> {$nutritionData['protein_g']} g</li>
                        <li><strong>Fat:</strong> {$nutritionData['fat_g']} g</li>
                        <li><strong>Carbs:</strong> {$nutritionData['carb_g']} g</li>
                        <li><strong>Sugars:</strong> {$nutritionData['sugars_g']} g</li>
                        <li><strong>Sodium:</strong> {$nutritionData['sodium_mg']} mg</li>
                    </ul>
                    HTML;
        }

        $mail->Body = <<<HTML
                    <h3>Your Receipt from NutriPOS!</h3>
                    <p>{$order_data['order_date']} at {$order_data['order_time']}</p>
                    <p>Total: {$order_data['total']}</p>
                    <h4>Items:</h4>
                    <ul>
                    {$itemsHtml}
                    </ul>
                    {$nutritionHtml}
                    HTML;

    
        $mail->send();
        $mailStatus = '✅ Receipt has been sent to the provided email address!';
    } catch (Exception $e) {
        $mailStatus = '❌ Email could not be sent.<br>' . htmlspecialchars($e->getMessage());
    }
}

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

    <div class="main-container" style="max-width: 960px">
        <?php if ($mailStatus !== null): ?>
            <div class="email-status-card">
                <p style="margin: 15px;">
                    <?php echo $mailStatus ?>
                </p>
            </div>
        <?php endif; ?>
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


            <!-- email, print, and return buttons -->
            <div class="receipt-actions">
                <form method="post" action="?order=<?php echo htmlspecialchars($order_data['id']); ?>" style="display:inline;">
                    <input type="text" name="email" placeholder="Enter email address" class="form-text-input" required>
                    <button type="submit" name="send_email" class="receipt-btn primary">Email Receipt</button>
                </form>
                <button onclick="window.print()" class="receipt-btn primary">Print Receipt</button>
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


