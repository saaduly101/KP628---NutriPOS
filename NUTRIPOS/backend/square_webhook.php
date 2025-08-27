<?php

require '../../vendor/autoload.php';

use Square\SquareClient;
use Square\Environments;
use Square\Exceptions\SquareException;
use Square\Orders\Requests\GetOrdersRequest;
use Dotenv\Dotenv;

// Load the .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$client = new SquareClient(
    token: $_ENV['SQUARE_ACCESS_TOKEN'],
    options: ['baseUrl' => Environments::Sandbox->value // Used by default
    ]
);

$signatureKey    = $_ENV["SQUARE_WEBHOOK_SIGNATURE_KEY"];
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
        file_put_contents("square_orders.txt", "Order Completed: $orderId\n", FILE_APPEND);

        $response = $client->orders->get(
            new GetOrdersRequest([
                'orderId' => $orderId,
            ]),
        );

        file_put_contents("square_response.json", "$response\n");
    }
} else {
    http_response_code(403);
}

?>