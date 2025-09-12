<?php

require '../../vendor/autoload.php';

use Square\SquareClient;
use Square\Environments;
use Square\Exceptions\SquareException;
use Square\Orders\Requests\GetOrdersRequest;
use Dotenv\Dotenv;

function cleanSquareAPIOrderUpdatedResponse($response) {
    $order_removeKeys = array('state', 'location_id', 'source', 'customer_id', 'taxes', 'net_amounts', 'tenders', 'version', 'created_at', 'updated_at', 'total_tax_money', 'total_discount_money', 'total_tip_money', 'total_service_charge_money', 'net_amount_due_money');
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
        return;
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

        $sql = "INSERT INTO orders (id, `Timestamp`, Value) 
        VALUES ('" . $response['order']['id'] . "', '" . $orderLocalTime . "', '" . $orderCurrencyValue . "')";
        
        if ($conn->query($sql) === TRUE) {
            echo "New record created successfully";
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }

        $response = json_encode($response, JSON_PRETTY_PRINT);
    }
} else {
	// header("HTTP/1.1 403 Forbidden");
	// exit("403 Forbidden - You don't have permission to access this resource.");
    echo "Forbidden 403";
    http_response_code(403);
}

?>