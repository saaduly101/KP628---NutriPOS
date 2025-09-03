<?php
// ---- HARDENED JSON API Preamble ----
ob_clean(); // drop any BOM/whitespace accidentally sent earlier
header('Content-Type: application/json; charset=utf-8');

// In production, do not echo warnings/notices; capture them instead:
ini_set('display_errors', '0');
error_reporting(E_ALL);

// collect warnings/notices into an array
$__err = [];
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$__err){
  $__err[] = "$errstr in $errfile:$errline";
  return true; // handled
});

// a helper to emit JSON safely and exit
function jexit($arr){
  // If JSON fails due to invalid UTF-8, try to scrub
  $json = json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR);
  if ($json === false){
    $arr2 = ['error'=>'json_encode failed: '.json_last_error_msg()];
    $json = json_encode($arr2);
  }
  // make sure no buffered output contaminates JSON
  while (ob_get_level() > 0) ob_end_clean();
  header('Content-Type: application/json; charset=utf-8');
  echo $json;
  exit;
}
// -------------------------------------

require_once 'afcd_cache.php';

// If you want to see raw warnings for debugging, call ?debug=1
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) jexit(['error'=>'Invalid JSON body', 'debug'=>$debug ? $__err : null]);

$ingredients = $body['ingredients'] ?? [];

$afcd = loadAFCDData('../data/afcd.csv');
if (!$afcd) jexit(['error'=>'AFCD data not found', 'debug'=>$debug ? $__err : null]);

// ---------- helpers ----------
function num($v) {
  if ($v === null) return 0.0;
  $s = trim((string)$v);
  if ($s === '') return 0.0;
  $s = str_ireplace(['trace','tr','nd','not detected'], '0', $s);
  $s = str_replace([',',' '], '', $s);
  $s = str_replace(['<','>','~','≈'], '', $s);
  return is_numeric($s) ? (float)$s : (float)preg_replace('/[^0-9.\-eE]/', '', $s);
}
function val($row, $keys) {
  foreach ($keys as $k) {
    if (array_key_exists($k, $row)) return num($row[$k]);
  }
  return 0.0;
}
// ---------- /helpers ----------

// Build indexes
$byCode = [];
$byName = [];
foreach ($afcd as $row) {
  $code = trim($row['Public Food Key'] ?? '');
  $name = strtolower(trim($row['Food Name'] ?? ''));
  if ($code) $byCode[$code] = $row;
  if ($name) $byName[$name] = $row;
}

function fuzzy_find($name, $byName) {
  $needle = strtolower(trim($name));
  if ($needle === '') return null;
  if (isset($byName[$needle])) return $byName[$needle];
  foreach ($byName as $n => $row) {
    if (strpos($n, $needle) !== false) return $row;
  }
  return null;
}

$totals = [
  "Energy (kJ)" => 0.0,
  "Protein (g)" => 0.0,
  "Fat (g)" => 0.0,
  "Carbohydrate (g)" => 0.0,
  "Sugars (g)" => 0.0,
  "Sodium (mg)" => 0.0
];
$matches = [];

foreach ($ingredients as $ing) {
  $grams = (float)($ing['grams'] ?? 0);
  if ($grams <= 0) continue;

  $row = null;
  if (!empty($ing['afcd_code']) && isset($byCode[$ing['afcd_code']])) {
    $row = $byCode[$ing['afcd_code']];
  } elseif (!empty($ing['name'])) {
    $row = fuzzy_find($ing['name'], $byName);
  }
  if (!$row) continue;

  $matches[] = $row['Food Name'];

  $energy_kj_100 = val($row, ["Energy with dietary fibre, equated (kJ)","Energy, without dietary fibre, equated (kJ)"]);
  $protein_g_100 = val($row, ["Protein (g)"]);
  $fat_g_100     = val($row, ["Fat, total (g)"]);
  $carb_g_100    = val($row, ["Available carbohydrate, without sugar alcohols (g)"]);
  if ($carb_g_100 <= 0) {
    $carb_g_100 = val($row, ["Available carbohydrate, with sugar alcohols (g)"]);
  }
  $sugars_g_100  = val($row, ["Total sugars (g)"]);
  $sodium_mg_100 = val($row, ["Sodium (Na) (mg)"]);

  if ($energy_kj_100 <= 0) {
    $energy_kj_100 = ($protein_g_100 * 17.0) + ($carb_g_100 * 17.0) + ($fat_g_100 * 37.0);
  }

  $factor = $grams / 100.0;
  $totals["Energy (kJ)"] += $energy_kj_100 * $factor;
  $totals["Protein (g)"] += $protein_g_100 * $factor;
  $totals["Fat (g)"]     += $fat_g_100 * $factor;
  $totals["Carbohydrate (g)"] += $carb_g_100 * $factor;
  $totals["Sugars (g)"]  += $sugars_g_100 * $factor;
  $totals["Sodium (mg)"] += $sodium_mg_100 * $factor;
}

$totals["Calories (kcal)"] = round(($totals["Energy (kJ)"] / 4.184), 2);

// Final rounding for display consistency
$totals["Energy (kJ)"]   = round($totals["Energy (kJ)"], 1);
$totals["Protein (g)"]   = round($totals["Protein (g)"], 2);
$totals["Fat (g)"]       = round($totals["Fat (g)"], 2);
$totals["Carbohydrate (g)"] = round($totals["Carbohydrate (g)"], 2);
$totals["Sugars (g)"]    = round($totals["Sugars (g)"], 2);
$totals["Sodium (mg)"]   = round($totals["Sodium (mg)"], 0);

// If any warnings were captured, include them under "debug" (only if ?debug=1)
$out = ['totals'=>$totals, 'matches'=>$matches];
if ($debug && !empty($__err)) $out['debug'] = $__err;

restore_error_handler();
jexit($out);
