<?php
// backend/order_list.php
declare(strict_types=1);

// Force clean JSON, never HTML
header_remove();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

ob_start(); // capture any accidental output

require_once __DIR__ . '/auth.php';

try {
  auth_require_admin();

  $pdo = pdo();
  $stmt = $pdo->query(
    "SELECT id, created_at, customer_email, calories_kcal, protein_g, fat_g, carb_g, sodium_mg
     FROM orders
     ORDER BY id DESC
     LIMIT 200"
  );
  $rows = $stmt->fetchAll();

  // If any noise was captured (BOM/warnings), surface it in dev-friendly way
  $noise = ob_get_clean(); // end buffer
  if ($noise !== '') {
    // Don’t corrupt JSON: return JSON with an error field
    echo json_encode(['error' => 'Server output detected before JSON', 'details' => $noise], JSON_UNESCAPED_SLASHES);
    exit;
  }

  echo json_encode(['orders' => $rows], JSON_UNESCAPED_SLASHES);
  exit;
}
catch (Throwable $e) {
  // Clear any noise and return structured error
  if (ob_get_length() !== false) { ob_end_clean(); }
  http_response_code(500);
  echo json_encode(['error' => 'Failed to load orders', 'hint' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
  exit;
}

// No closing PHP tag – prevents accidental whitespace.
