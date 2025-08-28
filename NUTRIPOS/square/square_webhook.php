<?php

require '../../vendor/autoload.php';

use Square\SquareClient;
use Square\Environments;
use Square\Exceptions\SquareException;
use Square\Orders\Requests\GetOrdersRequest;
use Dotenv\Dotenv;

function cleanSquareAPIOrderUpdatedResponse($response) {
    $response = json_decode($response, true);

    $order_removeKeys = array('state', 'location_id', 'source', 'customer_id', 'taxes', 'net_amounts', 'tenders', 'version', 'created_at', 'updated_at', 'total_money', 'total_tax_money', 'total_discount_money', 'total_tip_money', 'total_service_charge_money', 'net_amount_due_money');
    $lineitems_removeKeys = array('uid', 'catalog_version', 'note', 'item_type', 'applied_taxes', 'base_price_money', 'variation_total_price_money', 'gross_sales_money', 'total_tax_money', 'total_discount_money', 'total_money', 'total_service_charge_money');
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

    $response = json_encode($response, JSON_PRETTY_PRINT);
    
    return $response;
}

// Load the .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$squareEnvironment = $_ENV['SQUARE_ENVIRONMENT'] === 'PRODUCTION' ? Environments::Production : Environments::Sandbox;

$client = new SquareClient(
    token: $_ENV['SQUARE_ACCESS_TOKEN'],
    options: ['baseUrl' => $squareEnvironment->value // Used by default
    ]
);

$signatureKey    = $_ENV["SQUARE_WEBHOOK_SIGNATURE_KEY"];
$notificationUrl = $_ENV["SQUARE_NOTIFICATION_URL"];
$signatureHeader = $_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] ?? '';
$rawBody         = file_get_contents('php://input');

// Testing
// file_put_contents("square_payload".date("his").".json", "$rawBody\n");

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
        return;
    } else {
        file_put_contents("square_orders.txt", "Order Completed: $orderId\n", FILE_APPEND);

        $response = $client->orders->get(
            new GetOrdersRequest([
                'orderId' => $orderId,
            ]),
        );

        $response = cleanSquareAPIOrderUpdatedResponse($response);

        file_put_contents("square_response".date("his").".json", "$response\n");
    }
} else {
    http_response_code(403);
}

?>