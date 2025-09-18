<?php
// backend/nutrition_lib.php
require_once __DIR__.'/afcd_cache.php';

function afcd_num($v){
  if ($v === null) return 0.0;
  $s = trim((string)$v);
  if ($s === '') return 0.0;
  $s = str_ireplace(['trace','tr','nd','not detected'], '0', $s);
  $s = str_replace([',',' '], '', $s);
  $s = str_replace(['<','>','~','≈'], '', $s);
  return is_numeric($s) ? (float)$s : (float)preg_replace('/[^0-9.\-eE]/', '', $s);
}
function afcd_val($row, $keys){
  foreach ($keys as $k){ if (array_key_exists($k, $row)) return afcd_num($row[$k]); }
  return 0.0;
}
function afcd_build_indexes($rows){
  $byCode = []; $byName = [];
  foreach ($rows as $row){
    $code = trim($row['Public Food Key'] ?? '');
    $name = strtolower(trim($row['Food Name'] ?? ''));
    if ($code) $byCode[$code] = $row;
    if ($name) $byName[$name] = $row;
  }
  return [$byCode, $byName];
}
function afcd_fuzzy_find($name, $byName){
  $needle = strtolower(trim($name));
  if ($needle === '') return null;
  if (isset($byName[$needle])) return $byName[$needle];
  foreach ($byName as $n => $row){ if (strpos($n, $needle) !== false) return $row; }
  return null;
}

/**
 * @param array $ingredients  [{name, afcd_code, grams}, ...]  grams are per row as shown to user
 * @return array ['totals'=>..., 'matches'=>[names...]]
 */
function afcd_calc_totals(array $ingredients){
  $rows = loadAFCDData(__DIR__.'/../data/afcd.csv'); // relative to backend/
  if (!$rows) return ['error'=>'AFCD data not found'];

  static $byCode=null, $byName=null;
  if ($byCode === null || $byName === null){
    [$byCode, $byName] = afcd_build_indexes($rows);
  }

  $totals = [
    "Energy (kJ)" => 0.0,
    "Protein (g)" => 0.0,
    "Fat (g)" => 0.0,
    "Carbohydrate (g)" => 0.0,
    "Sugars (g)" => 0.0,
    "Sodium (mg)" => 0.0
  ];
  $matches=[];

  foreach ($ingredients as $ing){
    $grams = (float)($ing['grams'] ?? 0);
    if ($grams <= 0) continue;

    $row = null;
    if (!empty($ing['afcd_code']) && isset($byCode[$ing['afcd_code']])){
      $row = $byCode[$ing['afcd_code']];
    } elseif (!empty($ing['name'])) {
      $row = afcd_fuzzy_find($ing['name'], $byName);
    }
    if (!$row) continue;

    $matches[] = $row['Food Name'];

    $energy_kj_100 = afcd_val($row, ["Energy with dietary fibre, equated (kJ)","Energy, without dietary fibre, equated (kJ)"]);
    $protein_g_100 = afcd_val($row, ["Protein (g)"]);
    $fat_g_100     = afcd_val($row, ["Fat, total (g)"]);
    $carb_g_100    = afcd_val($row, ["Available carbohydrate, without sugar alcohols (g)"]);
    if ($carb_g_100 <= 0){
      $carb_g_100 = afcd_val($row, ["Available carbohydrate, with sugar alcohols (g)"]);
    }
    $sugars_g_100  = afcd_val($row, ["Total sugars (g)"]);
    $sodium_mg_100 = afcd_val($row, ["Sodium (Na) (mg)"]);

    if ($energy_kj_100 <= 0){
      $energy_kj_100 = ($protein_g_100*17) + ($carb_g_100*17) + ($fat_g_100*37);
    }

    $factor = $grams / 100.0;
    $totals["Energy (kJ)"]        += $energy_kj_100 * $factor;
    $totals["Protein (g)"]        += $protein_g_100 * $factor;
    $totals["Fat (g)"]            += $fat_g_100 * $factor;
    $totals["Carbohydrate (g)"]   += $carb_g_100 * $factor;
    $totals["Sugars (g)"]         += $sugars_g_100 * $factor;
    $totals["Sodium (mg)"]        += $sodium_mg_100 * $factor;
  }

  // calories
  $totals["Calories (kcal)"] = $totals["Energy (kJ)"] / 4.184;

  // nice rounding for display (keep raw-ish for DB if you want)
  $totals["Energy (kJ)"]      = round($totals["Energy (kJ)"], 1);
  $totals["Calories (kcal)"]  = round($totals["Calories (kcal)"], 2);
  $totals["Protein (g)"]      = round($totals["Protein (g)"], 2);
  $totals["Fat (g)"]          = round($totals["Fat (g)"], 2);
  $totals["Carbohydrate (g)"] = round($totals["Carbohydrate (g)"], 2);
  $totals["Sugars (g)"]       = round($totals["Sugars (g)"], 2);
  $totals["Sodium (mg)"]      = round($totals["Sodium (mg)"], 0);

  return ['totals'=>$totals, 'matches'=>$matches];
}

// Lookup AFCD code + grams per unit for a Square item from catalog_map table
function catalog_map_lookup(mysqli $conn, ?string $catalogObjectId, ?string $sku, ?string $name) {
    // Try catalog_object_id first
    if ($catalogObjectId) {
        $stmt = $conn->prepare("SELECT afcd_code, grams_per_unit FROM catalog_map WHERE catalog_object_id = ? LIMIT 1");
        $stmt->bind_param("s", $catalogObjectId);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) return $row;
    }
    // Try SKU if available
    if ($sku) {
        $stmt = $conn->prepare("SELECT afcd_code, grams_per_unit FROM catalog_map WHERE sku = ? LIMIT 1");
        $stmt->bind_param("s", $sku);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) return $row;
    }
    // Fallback to name
    if ($name) {
        $stmt = $conn->prepare("SELECT afcd_code, grams_per_unit FROM catalog_map WHERE name = ? LIMIT 1");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) return $row;
    }
    return null;
}