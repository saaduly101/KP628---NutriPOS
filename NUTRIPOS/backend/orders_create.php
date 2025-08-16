<?php
// C:\xampp\htdocs\NutriPOS\backend\orders_create.php
declare(strict_types=1);

header_remove();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

ob_start(); // capture any stray output

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/afcd_cache.php';

function nowUtc(): string {
  return gmdate('Y-m-d H:i:s');
}

/**
 * Minimal fuzzy match: prefer exact code; else case-insensitive substring on Food Name.
 * You can upgrade this later to use tags/synonyms index.
 */
function build_indexes(array $afcd): array {
  $byCode = [];
  $byName = [];
  foreach ($afcd as $row) {
    $code = isset($row['Public Food Key']) ? trim($row['Public Food Key']) : '';
    $name = isset($row['Food Name']) ? strtolower(trim($row['Food Name'])) : '';
    if ($code !== '') $byCode[$code] = $row;
    if ($name !== '') $byName[$name] = $row;
  }
  return [$byCode, $byName];
}

function fuzzy_find_row(string $name, array $byName): ?array {
  $needle = strtolower(trim($name));
  if ($needle === '') return null;
  if (isset($byName[$needle])) return $byName[$needle];
  foreach ($byName as $n => $row) {
    if (strpos($n, $needle) !== false) return $row;
  }
  return null;
}

function calc_totals(array $ingredients, array $afcd): array {
  [$byCode, $byName] = build_indexes($afcd);

  $tot = [
    "Energy (kJ)"   => 0.0,
    "Protein (g)"   => 0.0,
    "Fat (g)"       => 0.0,
    "Carbohydrate (g)" => 0.0,
    "Sugars (g)"    => 0.0,
    "Sodium (mg)"   => 0.0,
  ];
  $matches = [];

  foreach ($ingredients as $it) {
    $name = (string)($it['name'] ?? '');
    $code = (string)($it['afcd_code'] ?? '');
    $grams = (float)($it['grams'] ?? 0);
    if ($grams <= 0) continue;

    $row = null;
    if ($code !== '' && isset($byCode[$code])) {
      $row = $byCode[$code];
    } elseif ($name !== '') {
      $row = fuzzy_find_row($name, $byName);
    }
    if (!$row) continue;

    $matches[] = $row['Food Name'] ?? $name;

    $factor = $grams / 100.0; // AFCD values are per 100g
    $tot["Energy (kJ)"]   += (float)($row["Energy with dietary fibre, equated (kJ)"] ?? 0) * $factor;
    $tot["Protein (g)"]   += (float)($row["Protein (g)"] ?? 0) * $factor;
    $tot["Fat (g)"]       += (float)($row["Fat, total (g)"] ?? 0) * $factor;
    $tot["Carbohydrate (g)"] += (float)($row["Available carbohydrate, without sugar alcohols (g)"] ?? 0) * $factor;
    $tot["Sugars (g)"]    += (float)($row["Total sugars (g)"] ?? 0) * $factor;
    $tot["Sodium (mg)"]   += (float)($row["Sodium (Na) (mg)"] ?? 0) * $factor;
  }

  // calories from kJ
  $tot["Calories (kcal)"] = $tot["Energy (kJ)"] * 0.239006;

  // round to sensible precision for receipt
  foreach ($tot as $k => $v) {
    $tot[$k] = round((float)$v, ($k === "Sodium (mg)" ? 0 : 2));
  }

  return ['totals' => $tot, 'matches' => $matches];
}

// ---------- INPUT ----------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
  // Helpful error on GET for quick checks
  $noise = ob_get_clean();
  echo json_encode(['error' => 'POST required', 'details' => $noise], JSON_UNESCAPED_SLASHES);
  exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
  $noise = ob_get_clean();
  http_response_code(400);
  echo json_encode(['error' => 'Invalid JSON body', 'details' => $noise], JSON_UNESCAPED_SLASHES);
  exit;
}

$customer_email = trim((string)($body['customer_email'] ?? ''));
$items = $body['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
  $noise = ob_get_clean();
  http_response_code(400);
  echo json_encode(['error' => 'No items', 'details' => $noise], JSON_UNESCAPED_SLASHES);
  exit;
}

// expand qty → grams*qty for calc
$ingredients = [];
foreach ($items as $it) {
  $g = (float)($it['grams'] ?? 0);
  $q = (int)($it['qty'] ?? 1);
  $gTot = $g * max(1, $q);
  $ingredients[] = [
    'name'      => (string)($it['name'] ?? ''),
    'afcd_code' => (string)($it['afcd_code'] ?? ''),
    'grams'     => $gTot
  ];
}

// ---------- CALC ----------
$afcd = loadAFCDData(__DIR__ . '/../data/afcd.csv');
if (!$afcd || !is_array($afcd)) {
  $noise = ob_get_clean();
  http_response_code(500);
  echo json_encode(['error' => 'AFCD data not found or unreadable', 'details' => $noise], JSON_UNESCAPED_SLASHES);
  exit;
}

$calc = calc_totals($ingredients, $afcd);
$tot = $calc['totals'] ?? null;
if (!$tot) {
  $noise = ob_get_clean();
  http_response_code(500);
  echo json_encode(['error' => 'Calculation failed', 'details' => $noise], JSON_UNESCAPED_SLASHES);
  exit;
}

// ---------- DB WRITE ----------
try {
  $pdo = pdo();
  $pdo->beginTransaction();

  $ins = $pdo->prepare("
    INSERT INTO orders
      (created_at, customer_email, energy_kj, calories_kcal, protein_g, fat_g, carb_g, sugars_g, sodium_mg)
    VALUES
      (:ts, :email, :kj, :kcal, :p, :f, :c, :sug, :na)
  ");
  $ins->execute([
    ':ts'   => nowUtc(),
    ':email'=> ($customer_email !== '' ? $customer_email : null),
    ':kj'   => $tot['Energy (kJ)'],
    ':kcal' => $tot['Calories (kcal)'],
    ':p'    => $tot['Protein (g)'],
    ':f'    => $tot['Fat (g)'],
    ':c'    => $tot['Carbohydrate (g)'],
    ':sug'  => $tot['Sugars (g)'],
    ':na'   => $tot['Sodium (mg)'],
  ]);

  $order_id = (int)$pdo->lastInsertId();

  $insItem = $pdo->prepare("
    INSERT INTO order_items (order_id, name, afcd_code, grams, qty)
    VALUES (:oid, :n, :code, :g, :q)
  ");

  foreach ($items as $it) {
    $insItem->execute([
      ':oid'  => $order_id,
      ':n'    => (string)($it['name'] ?? ''),
      ':code' => (string)($it['afcd_code'] ?? ''),
      ':g'    => (float)($it['grams'] ?? 0),
      ':q'    => (int)($it['qty'] ?? 1),
    ]);
  }

  $pdo->commit();

  $noise = ob_get_clean(); // any accidental output?
  if ($noise !== '') {
    echo json_encode(['error' => 'Server output detected before JSON', 'details' => $noise], JSON_UNESCAPED_SLASHES);
    exit;
  }

  echo json_encode(['order_id' => $order_id, 'totals' => $tot], JSON_UNESCAPED_SLASHES);
  exit;

} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
  if (ob_get_length() !== false) { ob_end_clean(); }
  http_response_code(500);
  echo json_encode(['error' => 'DB error', 'details' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
  exit;
}

// no closing tag to avoid stray output
