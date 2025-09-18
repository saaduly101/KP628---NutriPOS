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
        li.sku, 
        oli.quantity AS line_item_quantity, 
        m.name AS modifier_name, 
        olim.quantity AS modifier_quantity 
    FROM orders o 
    JOIN order_line_items oli ON o.id = oli.order_id 
    JOIN line_items li ON oli.line_item_catalog_object_id = li.line_item_catalog_object_id 
    LEFT JOIN order_line_item_modifiers olim ON oli.id = olim.order_line_item_id 
    LEFT JOIN modifiers m ON olim.modifier_id = m.id 
    WHERE o.id ='" . $order . "'");
    
    // Display order header information
    $first_row = true;
    $current_line_item = null;
    
    while ($row = $result->fetch_assoc()) {
        if ($first_row) {
            echo "<h1>Order Information</h1>";
            echo "Order: " . $row['order_id'] . "<br>";
            echo "Time: " . $row['closed_at'] . "<br>";
            echo "Total: " . $row['total'] . "<br>";
            echo "SKU: " . $row['sku'] . "<br>";

            echo "<h2>Items:</h2>";
            echo "-------------------<br>";
            $first_row = false;
        }
        
        // If this is a new line item
        if ($current_line_item !== $row['line_item_catalog_object_id']) {
            // Add a blank line between items
            if ($current_line_item !== null) {
                echo "<br>";
            }
            
            $current_line_item = $row['line_item_catalog_object_id'];
            
            // Display line item
            $line_item_text = $row['line_item_quantity'] . 'x ' . $row['line_item_name'];
            if (!empty($row['variation_name'])) {
                $line_item_text .= ' (' . $row['variation_name'] . ')';
            }
            if (!empty($row['sku'])) {
           $line_item_text .= ' [SKU: ' . $row['sku'] . ']';
            }
            echo $line_item_text . "<br>";
        }
        
        // Display modifier if it exists
        if (!empty($row['modifier_name'])) {
            $modifier_text = '  • ' . $row['modifier_name'];
            if ($row['modifier_quantity'] > 1) {
                $modifier_text .= ' (' . $row['modifier_quantity'] . 'x)';
            }
            echo $modifier_text . "<br>";
        }
    }
}
?>
