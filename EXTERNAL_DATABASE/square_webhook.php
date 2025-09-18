<?php

require '../../vendor/autoload.php';

use Square\SquareClient;
use Square\Environments;
use Square\Exceptions\SquareException;
use Square\Orders\Requests\GetOrdersRequest;
use Dotenv\Dotenv;

function cleanSquareAPIOrderUpdatedResponse($response) {
    $order_removeKeys = array('discounts', 'state', 'location_id', 'source', 'customer_id', 'taxes', 'net_amounts', 'tenders', 'version', 'created_at', 'updated_at', 'total_tax_money', 'total_discount_money', 'total_tip_money', 'total_service_charge_money', 'net_amount_due_money');
    $lineitems_removeKeys = array('applied_discounts', 'uid', 'catalog_version', 'note', 'item_type', 'applied_taxes', 'base_price_money', 'variation_total_price_money', 'gross_sales_money', 'total_tax_money', 'total_discount_money', 'total_money', 'total_service_charge_money');
    $modifiers_removeKeys = array('uid', 'catalog_version', 'base_price_money', 'total_price_money');

    foreach($order_removeKeys as $key) {
        unset($response['order'][$key]);
    }

    foreach($response['order']['line_items'] as &$line_item) {        
        foreach($lineitems_removeKeys as $key) {
            unset($line_item[$key]);
        }

        if (isset($line_item['modifiers'])) {
            foreach($line_item['modifiers'] as &$modifier) {
                foreach($modifiers_removeKeys as $key) {
                    unset($modifier[$key]);
                }
            }
        }
    }
    
    return $response;
}

// Load the .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Sets Timezone
date_default_timezone_set($_ENV["TIMEZONE"]);

$squareEnvironment = $_ENV['SQUARE_ENVIRONMENT'] === 'PRODUCTION' ? Environments::Production : Environments::Sandbox;

$squareAccessToken = $squareEnvironment === Environments::Production ? $_ENV['SQUARE_ACCESS_TOKEN'] : $_ENV['SQUARE_ACCESS_TOKEN_SANDBOX'];
$squareWebhookSignatureKey = $squareEnvironment === Environments::Production ? $_ENV['SQUARE_WEBHOOK_SIGNATURE_KEY'] : $_ENV['SQUARE_WEBHOOK_SIGNATURE_KEY_SANDBOX'];

$client = new SquareClient(
    token: $squareAccessToken,
    options: ['baseUrl' => $squareEnvironment->value // Used by default
    ]
);

$signatureKey    = $squareWebhookSignatureKey;
$notificationUrl = $_ENV["SQUARE_NOTIFICATION_URL"];
$signatureHeader = $_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] ?? '';
$rawBody         = file_get_contents('php://input');

// Build payload exactly as Square signs it
$payload = $notificationUrl . $rawBody;

// Compute HMAC-SHA256 and Base64
$computed = base64_encode(hash_hmac('sha256', $payload, $signatureKey, true));

// Constant-time compare to prevent timing attacks
if (hash_equals($computed, $signatureHeader)) {
    http_response_code(200);

    $webhook_data = json_decode($rawBody, true);
   
    $orderId = $webhook_data['data']['object']['order_updated']['order_id'];
    $orderState = $webhook_data['data']['object']['order_updated']['state'];

    if ($orderState != "COMPLETED") {
        exit;
    } else {
        // Create Database Connection
        $conn = mysqli_connect($_ENV['DB_SERVERNAME'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_NAME']);

        // Check Database Connection
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }

        $response = $client->orders->get(
            new GetOrdersRequest([
                'orderId' => $orderId,
            ]),
        );

        $response = json_decode($response, true);
        $response = cleanSquareAPIOrderUpdatedResponse($response);

        $orderId = $response['order']['id'];

        // Setting Order Value and Currency to display correctly
        $fmt = new NumberFormatter($_ENV['LOCALE'], NumberFormatter::CURRENCY);
        $orderValue = $response['order']['total_money']['amount'] ?? 0;
        $orderCurrency = $response['order']['total_money']['currency'] ?? "AUD";
        $orderCurrencyValue = $fmt->formatCurrency($orderValue / 100, $orderCurrency);

        // Setting Order Time to display correctly
        $orderTime = $response['order']['closed_at'];
        $orderTime = new DateTime($orderTime, new DateTimeZone("UTC"));
        $orderTime->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $orderLocalTime = $orderTime->format(DateTime::ATOM);

        // Check if order already exists
        $check_stmt = $conn->prepare("SELECT id FROM orders WHERE id = ?");
        $check_stmt->bind_param("s", $orderId);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $check_stmt->close();
            exit("Order already exists");
        }
        $check_stmt->close();
        
        // Insert Order into DB using prepared statement
        $order_stmt = $conn->prepare("INSERT INTO orders (id, closed_at, total) VALUES (?, ?, ?)");
        $order_stmt->bind_param("sss", $orderId, $orderLocalTime, $orderCurrencyValue);
        
        if (!$order_stmt->execute()) {
            error_log("Error inserting order: " . $order_stmt->error);
        }
        $order_stmt->close();
        
        // Insert Line Items into DB using prepared statement
        $line_item_stmt = $conn->prepare("INSERT IGNORE INTO line_items (line_item_catalog_object_id, name, variation_name) VALUES (?, ?, ?)");
        $order_line_item_stmt = $conn->prepare("INSERT INTO order_line_items (order_id, line_item_catalog_object_id, quantity) VALUES (?, ?, ?)");
        
        foreach($response['order']['line_items'] as $line_item) {
            $line_item_stmt->bind_param("sss", 
                $line_item['catalog_object_id'], 
                $line_item['name'],
                $line_item['variation_name']
            );

            if (!$line_item_stmt->execute()) {
                error_log("Error inserting line item: " . $line_item_stmt->error);
            }

            $order_line_item_stmt->bind_param("ssi", 
                $orderId, 
                $line_item['catalog_object_id'], 
                $line_item['quantity']
            );
            
            if ($order_line_item_stmt->execute()) {
                $order_line_item_id = $conn->insert_id; // Get the auto-incremented ID
            } else {
                error_log("Error inserting order line item: " . $order_line_item_stmt->error);
            }

            // Prepare statements for modifier operations
            $modifier_check_stmt = $conn->prepare("SELECT id FROM modifiers WHERE modifier_catalog_object_id = ?");
            $modifier_insert_stmt = $conn->prepare("INSERT INTO modifiers (modifier_catalog_object_id, name) VALUES (?, ?)");
            $order_line_item_modifier_stmt = $conn->prepare("INSERT INTO order_line_item_modifiers (order_line_item_id, modifier_id, quantity) VALUES (?, ?, ?)");
            
            foreach($line_item['modifiers'] as $modifier) {
                $modifier_id = null;
                
                // Check if modifier exists
                $modifier_check_stmt->bind_param("s", $modifier['catalog_object_id']);
                $modifier_check_stmt->execute();
                $result = $modifier_check_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Modifier exists, get its ID
                    $row = $result->fetch_assoc();
                    $modifier_id = $row['id'];
                } else {
                    // Insert new modifier
                    $modifier_insert_stmt->bind_param("ss", 
                        $modifier['catalog_object_id'], 
                        $modifier['name']
                    );
                    
                    if ($modifier_insert_stmt->execute()) {
                        $modifier_id = $conn->insert_id;
                    } else {
                        error_log("Error inserting modifier: " . $modifier_insert_stmt->error);
                    }
                }
                
                // Only proceed if we have a valid modifier_id
                if ($modifier_id) {
                    $order_line_item_modifier_stmt->bind_param("iii", 
                        $order_line_item_id, 
                        $modifier_id, 
                        $modifier['quantity']
                    );
                    
                    if (!$order_line_item_modifier_stmt->execute()) {
                        error_log("Error inserting order line item modifier: " . $order_line_item_modifier_stmt->error);
                    }
                }
            }
            $modifier_check_stmt->close();
            $modifier_insert_stmt->close();
            $order_line_item_modifier_stmt->close();
        }
        $line_item_stmt->close();
        $order_line_item_stmt->close();
    }
} else {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

require_once __DIR__ . '/../backend/nutrition_lib.php';
require_once __DIR__ . '/../backend/nutrition_calc_service.php';
require_once __DIR__ . '/../backend/square_fetch_order.php';

// 1) Read webhook
$raw = file_get_contents("php://input");
$event = json_decode($raw, true);
if (!$event || !isset($event['data'])) {
    http_response_code(400);
    exit("invalid payload");
}

// 2) Extract order ID
$orderId = $event['data']['id'] ?? ($event['data']['object']['order']['id'] ?? null);
if (!$orderId) {
    http_response_code(200);
    exit("no order id");
}

// 3) Fetch full order from Square API
$order = fetch_square_order($_ENV['SQUARE_ACCESS_TOKEN'], $orderId);
if (!$order) {
    http_response_code(200);
    exit("fetch fail");
}

// 4) Connect to DB
$conn = mysqli_connect(
    $_ENV['DB_SERVERNAME'],
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD'],
    $_ENV['DB_NAME']
);
if (!$conn) {
    http_response_code(500);
    exit("DB fail");
}

// 5) Map Square line items → AFCD items
$items = [];
foreach ($order['line_items'] ?? [] as $li) {
    $qty = (int)$li['quantity'];
    $name = $li['name'];
    $catObjId = $li['catalog_object_id'] ?? null;
    $sku = $li['variation_name'] ?? null;

    $map = catalog_map_lookup($conn, $catObjId, $sku, $name);
    if (!$map) continue;

    $items[] = [
        'name'      => $name,
        'afcd_code' => $map['afcd_code'],
        'grams'     => $map['grams_per_unit'] * $qty,
        'qty'       => $qty
    ];
}

if (!$items) {
    http_response_code(200);
    exit("no mapped items");
}

// 6) Calculate nutrition totals
$calc = calc_totals_from_items($conn, $items);
$tot = $calc['totals'];

// 7) Insert order into DB
$stmt = $conn->prepare("
    INSERT INTO orders (created_at, customer_email, energy_kj, calories_kcal, protein_g, fat_g, carb_g, sugars_g, sodium_mg)
    VALUES (NOW(),?,?,?,?,?,?,?,?)
");
$customerEmail = $order['customer_id'] ?? null; // You can fetch email via Customers API if needed
$stmt->bind_param(
    "sddddddd",
    $customerEmail,
    $tot['Energy (kJ)'],
    $tot['Calories (kcal)'],
    $tot['Protein (g)'],
    $tot['Fat (g)'],
    $tot['Carbohydrate (g)'],
    $tot['Sugars (g)'],
    $tot['Sodium (mg)']
);
$stmt->execute();
$newOrderId = $stmt->insert_id;

// 8) Insert order items
$istmt = $conn->prepare("
    INSERT INTO order_items (order_id, name, afcd_code, grams, qty)
    VALUES (?,?,?,?,?)
");
foreach ($items as $it) {
    $istmt->bind_param("issdi", $newOrderId, $it['name'], $it['afcd_code'], $it['grams'], $it['qty']);
    $istmt->execute();
}

// 9) Build nutrition payload
$payload = "NutriPOS #{$newOrderId}\n"
    ."Energy: ".number_format($tot['Energy (kJ)'],1)." kJ (".number_format($tot['Calories (kcal)'],1)." kcal)\n"
    ."Protein: ".number_format($tot['Protein (g)'],2)." g\n"
    ."Fat: ".number_format($tot['Fat (g)'],2)." g\n"
    ."Carb: ".number_format($tot['Carbohydrate (g)'],2)." g\n"
    ."Sugars: ".number_format($tot['Sugars (g)'],2)." g\n"
    ."Sodium: ".number_format($tot['Sodium (mg)'],0)." mg";

// 10) Send email receipt
if ($customerEmail) {
    $data = [
        'email'   => $customerEmail,
        'subject' => "NutriPOS receipt: ".$newOrderId,
        'comment' => $payload
    ];
    $ch = curl_init(__DIR__ . '/../backend/email_receipt.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// 11) Finish
mysqli_close($conn);
http_response_code(200);
echo "ok";


?>