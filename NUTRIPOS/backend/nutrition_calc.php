<?php
header('Content-Type: application/json');
require_once __DIR__.'/afcd_search_lib.php'; // <-- shared search

$body = json_decode(file_get_contents('php://input'), true);
$ingredients = $body['ingredients'] ?? [];

$afcd = loadAFCDData('../data/afcd.csv');
if (!$afcd) { echo json_encode(['error'=>'AFCD data not found']); exit; }

// ---------- helpers ----------
function num($v) {
    // robust numeric parsing for AFCD cells like "1,236", "<0.1", "tr", "ND"
    if ($v === null) return 0.0;
    $s = trim((string)$v);
    if ($s === '') return 0.0;
    $s = str_ireplace(['trace','tr','nd','not detected'], '0', $s);
    $s = str_replace([',',' '], '', $s);
    $s = str_replace(['<','>','~','≈'], '', $s);
    // final guard
    return is_numeric($s) ? (float)$s : (float)preg_replace('/[^0-9.\-eE]/', '', $s);
}

function val($row, $keys) {
    // try a list of possible column names
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
    if (isset($byName[$needle])) return $byName[$needle];
    foreach ($byName as $n => $row) {
        if ($needle !== '' && strpos($n, $needle) !== false) return $row;
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

    // pull per-100g values safely
    $energy_kj_100 = val($row, ["Energy with dietary fibre, equated (kJ)","Energy, without dietary fibre, equated (kJ)"]);
    $protein_g_100 = val($row, ["Protein (g)"]);
    $fat_g_100     = val($row, ["Fat, total (g)"]);
    // prefer available carbs (without polyols); fallback to with polyols if empty
    $carb_g_100    = val($row, ["Available carbohydrate, without sugar alcohols (g)"]);
    if ($carb_g_100 <= 0) {
        $carb_g_100 = val($row, ["Available carbohydrate, with sugar alcohols (g)"]);
    }
    $sugars_g_100  = val($row, ["Total sugars (g)"]);
    $sodium_mg_100 = val($row, ["Sodium (Na) (mg)"]);

    // if energy missing/zero, estimate from macros (kJ): protein*17 + carbs*17 + fat*37
    if ($energy_kj_100 <= 0) {
        $energy_kj_100 = ($protein_g_100 * 17.0) + ($carb_g_100 * 17.0) + ($fat_g_100 * 37.0);
    }

    $factor = $grams / 100.0;

    $totals["Energy (kJ)"]   += $energy_kj_100 * $factor;
    $totals["Protein (g)"]   += $protein_g_100 * $factor;
    $totals["Fat (g)"]       += $fat_g_100 * $factor;
    $totals["Carbohydrate (g)"] += $carb_g_100 * $factor;
    $totals["Sugars (g)"]    += $sugars_g_100 * $factor;
    $totals["Sodium (mg)"]   += $sodium_mg_100 * $factor;
}

// calories from kJ (1 kcal = 4.184 kJ). Your previous factor 0.239006 is the same; keeping canonical form.
$cal_kcal = $totals["Energy (kJ)"] / 4.184;
$totals["Calories (kcal)"] = round($cal_kcal, 2);

// Nice rounding for display (optional)
$totals["Energy (kJ)"]   = round($totals["Energy (kJ)"], 1);
$totals["Protein (g)"]   = round($totals["Protein (g)"], 2);
$totals["Fat (g)"]       = round($totals["Fat (g)"], 2);
$totals["Carbohydrate (g)"] = round($totals["Carbohydrate (g)"], 2);
$totals["Sugars (g)"]    = round($totals["Sugars (g)"], 2);
$totals["Sodium (mg)"]   = round($totals["Sodium (mg)"], 0);

echo json_encode(['totals'=>$totals, 'matches'=>$matches]);
?>
