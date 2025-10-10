<?php
// backend/orders_get.php (hardened)
// Returns clean JSON only, with recipe-first nutrition totals.

declare(strict_types=1);

// --- Always send JSON, never echo warnings/notices to the client ---
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
ini_set('display_errors', '0');            // don't print PHP warnings to output
error_reporting(E_ALL);                    // still log them
ob_start();                                // capture accidental output

use Dotenv\Dotenv;

try {
  // ---- Composer autoload (try common locations) ----
  $autoloads = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
  ];
  $loaded = false;
  foreach ($autoloads as $a) {
    if (file_exists($a)) { require_once $a; $loaded = true; break; }
  }
  if (!$loaded) throw new RuntimeException('Composer autoload not found');

  // ---- Load .env from project root or parent ----
  $envLoaded = false;
  if (file_exists(__DIR__ . '/..' . '/.env')) {
    Dotenv::createImmutable(__DIR__ . '/..')->load(); $envLoaded = true;
  } elseif (file_exists(__DIR__ . '/../../.env')) {
    Dotenv::createImmutable(__DIR__ . '/../../')->load(); $envLoaded = true;
  }
  if (!$envLoaded) {
    // allow server env as fallback
    foreach (['DB_SERVERNAME','DB_USERNAME','DB_PASSWORD','DB_NAME'] as $k) {
      if (empty($_ENV[$k]) && getenv($k)) $_ENV[$k] = getenv($k);
    }
  }
  foreach (['DB_SERVERNAME','DB_USERNAME','DB_PASSWORD','DB_NAME'] as $k) {
    if (empty($_ENV[$k])) throw new RuntimeException("DB env missing: {$k}");
  }

  // ---- Connect REMOTE DB ----
  $pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $_ENV['DB_SERVERNAME'], $_ENV['DB_NAME']),
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
  );

  // ---- Input: id or 'latest' ----
$id = $_GET['id'] ?? ($_GET['order'] ?? '');
$id = trim((string)$id);
if ($id === '' || strcasecmp($id, 'latest') === 0) {
  $row = $pdo->query("SELECT id FROM orders ORDER BY COALESCE(closed_at, created_at) DESC, id DESC LIMIT 1")->fetch();
  if (!$row) { ob_clean(); echo json_encode(['error'=>'No orders found']); exit; }
  $id = $row['id'];
}

  if (!preg_match('/^[A-Za-z0-9\-\_=]+$/', $id)) {
    ob_clean(); echo json_encode(['error'=>'invalid id']); exit;
  }

  // ---- Order meta (optional) ----
  $created_at = null;
  $st = $pdo->prepare("SELECT closed_at FROM orders WHERE id = ? LIMIT 1");
  $st->execute([$id]);
  if ($r = $st->fetch()) {
    if (!empty($r['closed_at'])) $created_at = gmdate('Y-m-d\TH:i:s\Z', strtotime($r['closed_at']));
  }

  // ---- Load order line items (safe default = []) ----
  $li = $pdo->prepare("
    SELECT
      oli.id                          AS order_line_item_id,
      oli.order_id,
      oli.line_item_catalog_object_id AS catalog_object_id,
      CAST(oli.quantity AS CHAR)      AS quantity_str,
      li.name                         AS line_item_name,
      li.variation_name
    FROM order_line_items oli
    JOIN line_items li ON li.line_item_catalog_object_id = oli.line_item_catalog_object_id
    WHERE oli.order_id = ?
    ORDER BY oli.id
  ");
  $li->execute([$id]);
  $orderLineItems = $li->fetchAll() ?: [];   // << never null

  if (empty($orderLineItems)) {
    ob_clean();
    echo json_encode(['order'=>['id'=>$id,'created_at'=>$created_at],'items'=>[]], JSON_UNESCAPED_SLASHES);
    exit;
  }

  // ---- Collect catalog IDs ----
  $catalogIds = array_values(array_unique(array_map(
    fn($r) => (string)($r['catalog_object_id'] ?? ''), $orderLineItems
  )));
  $catalogIds = array_values(array_filter($catalogIds, fn($v)=>$v!==''));  // no empties

  // ---- Direct mapping: catalog_map (variation -> AFCD + grams) ----
  $catalogMap = [];
  if ($catalogIds) {
    $in = implode(',', array_fill(0, count($catalogIds), '?'));
    $cm = $pdo->prepare("SELECT catalog_object_id, afcd_code, grams_per_unit, name FROM catalog_map WHERE catalog_object_id IN ($in)");
    $cm->execute($catalogIds);
    foreach ($cm->fetchAll() as $row) {
      $catalogMap[$row['catalog_object_id']] = $row;
    }
  }

  // ---- Recipe mapping: square_catalog_map -> products -> product_ingredients ----
  $squareToProduct = []; $productIds = [];
  if ($catalogIds) {
    $in = implode(',', array_fill(0, count($catalogIds), '?'));
    $scm = $pdo->prepare("SELECT catalog_object_id, product_id, serve_multiplier FROM square_catalog_map WHERE catalog_object_id IN ($in)");
    $scm->execute($catalogIds);
    foreach ($scm->fetchAll() as $row) {
      $pid = (int)$row['product_id'];
      if ($pid > 0) {
        $squareToProduct[$row['catalog_object_id']] = [
          'product_id' => $pid,
          'serve_multiplier' => (float)($row['serve_multiplier'] ?? 1.0),
        ];
        $productIds[] = $pid;
      }
    }
  }
  $productIds = array_values(array_unique(array_filter($productIds)));

  $products = []; $ingredientsByProduct = []; $productTotals = []; $productGrams = [];
  if ($productIds) {
    $pin = implode(',', array_fill(0, count($productIds), '?'));

    // product names
    $ps = $pdo->prepare("SELECT id, name FROM products WHERE id IN ($pin)");
    $ps->execute($productIds);
    foreach ($ps->fetchAll() as $p) $products[(int)$p['id']] = $p;

    // ingredients
    $ings = $pdo->prepare("SELECT product_id, afcd_code, grams_per_unit FROM product_ingredients WHERE product_id IN ($pin) ORDER BY id");
    $ings->execute($productIds);
    foreach ($ings->fetchAll() as $ing) {
      $pid = (int)$ing['product_id'];
      if (!isset($ingredientsByProduct[$pid])) $ingredientsByProduct[$pid] = [];
      $ingredientsByProduct[$pid][] = $ing;
    }

    // cached totals
    $ts = $pdo->prepare("
      SELECT product_id, energy_kj, calories_kcal, protein_g, fat_g, carb_g, sugars_g, sodium_mg
      FROM product_nutrition_totals WHERE product_id IN ($pin)
    ");
    $ts->execute($productIds);
    foreach ($ts->fetchAll() as $t) {
      $pid = (int)$t['product_id'];
      $productTotals[$pid] = [
        'Energy (kJ)'      => (float)$t['energy_kj'],
        'Calories (kcal)'  => (float)$t['calories_kcal'],
        'Protein (g)'      => (float)$t['protein_g'],
        'Fat (g)'          => (float)$t['fat_g'],
        'Carbohydrate (g)' => (float)$t['carb_g'],
        'Sugars (g)'       => (float)$t['sugars_g'],
        'Sodium (mg)'      => (float)$t['sodium_mg'],
      ];
    }

    // grams per product
    $gs = $pdo->prepare("SELECT product_id, SUM(grams_per_unit) AS g FROM product_ingredients WHERE product_id IN ($pin) GROUP BY product_id");
    $gs->execute($productIds);
    foreach ($gs->fetchAll() as $g) {
      $productGrams[(int)$g['product_id']] = (float)($g['g'] ?? 0);
    }
  }

  // ---- Compute totals ----
  $itemsForUI = [];
  $orderTotals = [
    'Energy (kJ)' => 0, 'Calories (kcal)' => 0, 'Protein (g)' => 0,
    'Fat (g)' => 0, 'Carbohydrate (g)' => 0, 'Sugars (g)' => 0, 'Sodium (mg)' => 0
  ];

  foreach ($orderLineItems as $row) {
    $catalogId = (string)($row['catalog_object_id'] ?? '');
    $qty = (float)str_replace(',', '.', (string)($row['quantity_str'] ?? '1'));
    $lineName = (string)($row['line_item_name'] ?? '');

    // Prefer RECIPE if mapped
    // Prefer RECIPE if mapped
if ($catalogId && isset($squareToProduct[$catalogId])) {
  $pid  = $squareToProduct[$catalogId]['product_id'];
  $mult = $squareToProduct[$catalogId]['serve_multiplier'] ?: 1.0;
  $pname = $products[$pid]['name'] ?? $name;

  // Build ingredient list for display
  $recipeRows = $ingredientsByProduct[$pid] ?? [];
  $ingList = [];          // will send to UI
  $gramsOne = 0.0;        // grams per ONE product (unscaled by qty)

  // Optional: resolve AFCD names (if your lib provides it)
  $afcdToName = [];
  if (!empty($recipeRows)) {
    // if you have a helper to map codes -> names, use it here:
    // require_once __DIR__ . '/afcd_cache.php';
    // $afcdToName = afcd_get_names(array_column($recipeRows, 'afcd_code')); // <-- adapt if you have it
  }

  foreach ($recipeRows as $ing) {
    $code  = trim((string)($ing['afcd_code'] ?? ''));
    $gOne  = (float)($ing['grams_per_unit'] ?? 0);
    $gramsScaled = $gOne * $mult * $qty;

    $gramsOne += $gOne;

    $ingList[] = [
      // If you don’t have names handy, send the code as the "name" so the UI shows something
      'name'      => $afcdToName[$code] ?? $code,
      'afcd_code' => $code,
      'grams'     => $gramsScaled,
    ];
  }

  // Totals: cached or computed
  if (isset($productTotals[$pid])) {
    foreach ($orderTotals as $k => $_) {
      $orderTotals[$k] += ($productTotals[$pid][$k] ?? 0) * $qty * $mult;
    }
  } else if (!empty($recipeRows)) {
    require_once __DIR__ . '/nutrition_lib.php';
    if (function_exists('afcd_calc_totals')) {
      $afcdItems = [];
      foreach ($recipeRows as $ing) {
        $code = trim((string)($ing['afcd_code'] ?? ''));
        $g    = (float)($ing['grams_per_unit'] ?? 0) * $mult * $qty;
        if ($code !== '' && $g > 0) $afcdItems[] = ['afcd_code'=>$code, 'grams'=>$g];
      }
      $calc = afcd_calc_totals($afcdItems);
      $t = $calc['totals'] ?? [];
      foreach ($orderTotals as $k => $_) $orderTotals[$k] += (float)($t[$k] ?? 0);
    }
  }

  $itemsForUI[] = [
    'name'        => $pname,
    'afcd_code'   => '',
    'grams'       => $gramsOne * $qty * $mult,   // per-unit grams × qty
    'qty'         => $qty,
    'ingredients' => $ingList                    // <<< send to UI
  ];
  continue;
}


    $itemsForUI = [];
$orderTotals = [
  'Energy (kJ)'=>0,'Calories (kcal)'=>0,'Protein (g)'=>0,'Fat (g)'=>0,'Carbohydrate (g)'=>0,'Sugars (g)'=>0,'Sodium (mg)'=>0
];

foreach ($orderLineItems as $row) {
  $catalogId = (string)($row['catalog_object_id'] ?? '');
  $qty  = (float)str_replace(',', '.', (string)($row['quantity_str'] ?? '1'));
  $name = (string)($row['line_item_name'] ?? '');

  // Prefer recipe mapping
  if ($catalogId && isset($squareToProduct[$catalogId])) {
    $pid  = $squareToProduct[$catalogId]['product_id'];
    $mult = $squareToProduct[$catalogId]['serve_multiplier'] ?: 1.0;
    $pname = $products[$pid]['name'] ?? $name;

    // Always build an ingredients list for display
    $recipeRows = $ingredientsByProduct[$pid] ?? [];
    $ingList = [];
    $gramsOne = 0.0;

    foreach ($recipeRows as $ing) {
      $gOne = (float)($ing['grams_per_unit'] ?? 0);
      $gramsScaled = $gOne * $mult * $qty;
      $gramsOne += $gOne;
      $ingList[] = [
        'name'      => $ing['afcd_code'] ?: '',   // or store AFCD name if you also persisted it
        'afcd_code' => $ing['afcd_code'] ?? '',
        'grams'     => $gramsScaled,
      ];
    }

    // Totals: use cached when available, else compute on the fly from recipeRows
    if (isset($productTotals[$pid])) {
      foreach ($orderTotals as $k => $_) {
        $orderTotals[$k] += ($productTotals[$pid][$k] ?? 0) * $qty * $mult;
      }
    } else if (!empty($recipeRows)) {
      require_once __DIR__ . '/nutrition_lib.php';
      if (function_exists('afcd_calc_totals')) {
        $afcdItems = [];
        foreach ($recipeRows as $ing) {
          $code = trim((string)($ing['afcd_code'] ?? ''));
          $g    = (float)($ing['grams_per_unit'] ?? 0) * $mult * $qty;
          if ($code !== '' && $g > 0) $afcdItems[] = ['afcd_code'=>$code, 'grams'=>$g];
        }
        $calc = afcd_calc_totals($afcdItems);
        $t = $calc['totals'] ?? [];
        foreach ($orderTotals as $k => $_) {
          $orderTotals[$k] += (float)($t[$k] ?? 0);
        }
      }
    }
  }
    $itemsForUI[] = [
      'name'       => $pname,
      'afcd_code'  => '',
      'grams'      => $gramsOne * $qty * $mult,   // sum of recipe grams per unit × qty
      'qty'        => $qty,
      'ingredients'=> $ingList                    // <<< NEW
    ];
    continue;
  }

    // Prefer RECIPE if mapped
if ($catalogId && isset($squareToProduct[$catalogId])) {
  $pid  = $squareToProduct[$catalogId]['product_id'];
  $mult = $squareToProduct[$catalogId]['serve_multiplier'] ?: 1.0;
  $pname = $products[$pid]['name'] ?? $name;

  // Build ingredient list for display
  $recipeRows = $ingredientsByProduct[$pid] ?? [];
  $ingList = [];          // will send to UI
  $gramsOne = 0.0;        // grams per ONE product (unscaled by qty)

  // Optional: resolve AFCD names (if your lib provides it)
  $afcdToName = [];
  if (!empty($recipeRows)) {
    // if you have a helper to map codes -> names, use it here:
    // require_once __DIR__ . '/afcd_cache.php';
    // $afcdToName = afcd_get_names(array_column($recipeRows, 'afcd_code')); // <-- adapt if you have it
  }

  foreach ($recipeRows as $ing) {
    $code  = trim((string)($ing['afcd_code'] ?? ''));
    $gOne  = (float)($ing['grams_per_unit'] ?? 0);
    $gramsScaled = $gOne * $mult * $qty;

    $gramsOne += $gOne;

    $ingList[] = [
      // If you don’t have names handy, send the code as the "name" so the UI shows something
      'name'      => $afcdToName[$code] ?? $code,
      'afcd_code' => $code,
      'grams'     => $gramsScaled,
    ];
  }

  // Totals: cached or computed
  if (isset($productTotals[$pid])) {
    foreach ($orderTotals as $k => $_) {
      $orderTotals[$k] += ($productTotals[$pid][$k] ?? 0) * $qty * $mult;
    }
  } else if (!empty($recipeRows)) {
    require_once __DIR__ . '/nutrition_lib.php';
    if (function_exists('afcd_calc_totals')) {
      $afcdItems = [];
      foreach ($recipeRows as $ing) {
        $code = trim((string)($ing['afcd_code'] ?? ''));
        $g    = (float)($ing['grams_per_unit'] ?? 0) * $mult * $qty;
        if ($code !== '' && $g > 0) $afcdItems[] = ['afcd_code'=>$code, 'grams'=>$g];
      }
      $calc = afcd_calc_totals($afcdItems);
      $t = $calc['totals'] ?? [];
      foreach ($orderTotals as $k => $_) $orderTotals[$k] += (float)($t[$k] ?? 0);
    }
  }

  $itemsForUI[] = [
    'name'        => $pname,
    'afcd_code'   => '',
    'grams'       => $gramsOne * $qty * $mult,   // per-unit grams × qty
    'qty'         => $qty,
    'ingredients' => $ingList                    // <<< send to UI
  ];
  continue;
}
  }

  // ---- Build order summary ----
  $order = [
    'id'            => $id,
    'created_at'    => $created_at,
    'energy_kj'     => round($orderTotals['Energy (kJ)'], 1),
    'calories_kcal' => round($orderTotals['Calories (kcal)']),
    'protein_g'     => round($orderTotals['Protein (g)'], 2),
    'fat_g'         => round($orderTotals['Fat (g)'], 2),
    'carb_g'        => round($orderTotals['Carbohydrate (g)'], 2),
    'sugars_g'      => round($orderTotals['Sugars (g)'], 2),
    'sodium_mg'     => round($orderTotals['Sodium (mg)']),
  ];


  // ---- Clean any stray output and send JSON ----
  ob_clean();
  echo json_encode(['order'=>$order, 'items'=>$itemsForUI], JSON_UNESCAPED_SLASHES);
  exit;

}
catch (Throwable $e) {
  // Make sure the client still sees valid JSON, not a PHP warning page
  ob_clean();
  http_response_code(500);
  echo json_encode(['error'=>'Failed', 'hint'=>$e->getMessage()], JSON_UNESCAPED_SLASHES);
  exit;
}
