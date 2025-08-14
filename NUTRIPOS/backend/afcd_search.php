<?php
header('Content-Type: application/json');
require_once 'afcd_cache.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode(['results'=>[]]); exit; }

$rows = loadAFCDData('../data/afcd.csv');
$q_l = mb_strtolower($q);

// Synonyms & expansions (extensible)
$synonyms = [
  'bbq' => ['barbecue','barbeque','barbecue sauce','sauce barbecue','bbq sauce'],
  'patty' => ['hamburger patty','beef patty','burger patty','pattie'],
  'burger' => ['hamburger','cheeseburger','beef burger'],
  'dice' => ['diced','cubed'],
  'roll' => ['bun','white roll'],
  'wrap' => ['tortilla','tortilla wrap','large tortilla wrap'],
  'mince' => ['minced','ground beef','beef mince','minced beef'],
  'sauce' => ['ketchup','tomato sauce'],
  'chips' => ['fries','french fries'],
  'soda' => ['soft drink','cola','fizzy drink'],
  'lasagna' => ['lasagne']
];

function expand_terms($q, $synonyms){
  $terms = [$q];
  foreach ($synonyms as $k=>$alts){
    if (mb_strpos(mb_strtolower($q), $k) !== false){
      $terms = array_merge($terms, $alts);
    }
  }
  return array_values(array_unique($terms));
}

// Normalize
function norm($s){ return preg_replace('/\s+/', ' ', trim(mb_strtolower($s))); }

// Score computation
function score_row($name, $code, $category, $q, $expanded){
  $n = norm($name);
  $qnorm = norm($q);

  // Exact and code boosts
  if ($code && stripos($code, $q) === 0) return 200;
  if ($n === $qnorm) return 180;
  if (strpos($n, $qnorm) === 0) return 160;

  $score = 0;

  // Token overlap
  $ntoks = preg_split('/[^a-z0-9]+/i', $n, -1, PREG_SPLIT_NO_EMPTY);
  $qtoks = preg_split('/[^a-z0-9]+/i', $qnorm, -1, PREG_SPLIT_NO_EMPTY);
  $overlap = count(array_intersect($ntoks, $qtoks));
  $score += $overlap * 20;

  // Contains
  if (strpos($n, $qnorm) !== false) $score += 40;

  // Expanded terms contains
  foreach ($expanded as $ex){
    $exn = norm($ex);
    if (strpos($n, $exn) !== false) $score += 25;
  }

  // Typo tolerance (Levenshtein up to 2)
  if (function_exists('levenshtein')){
    $dist = levenshtein(substr($n,0,64), substr($qnorm,0,64));
    if ($dist <= 2) $score += (50 - $dist*10);
  }

  // Category boost for relevant classes
  if ($category){
    $cl = mb_strtolower($category);
    if (strpos($cl, 'beef') !== false && strpos($qnorm,'beef') !== false) $score += 20;
    if (strpos($cl, 'hamburger') !== false || strpos($cl, 'mixed dishes') !== false) $score += 10;
  }

  // Shorter names slightly boosted
  $score += max(0, 30 - strlen($name)/8);

  return $score;
}

$expanded = expand_terms($q, $synonyms);

$results = [];
foreach ($rows as $row) {
    $name = $row['Food Name'] ?? '';
    $code = $row['Public Food Key'] ?? '';
    $category = $row['Classification'] ?? '';
    if ($name === '') continue;

    $s = score_row($name, $code, $category, $q_l, $expanded);
    if ($s <= 0) continue;

    // Tags from heuristics (not in AFCD): infer simple tags
    $tags = [];
    $ln = mb_strtolower($name);
    if (strpos($ln,'cheese') !== false) $tags[] = 'Cheeseburger';
    if (strpos($ln,'hamburger') !== false || strpos($ln,'burger') !== false) $tags[] = 'Burger';
    if (strpos($ln,'lasagne') !== false || strpos($ln,'lasagna') !== false) $tags[] = 'Lasagna';
    if (strpos($ln,'beef') !== false) $tags[] = 'Beef';

    $results[] = [
        'code' => $code,
        'name' => $name,
        'category' => $category,
        'score' => $s,
        'tags' => $tags,
        'energy_kj' => floatval($row['Energy with dietary fibre, equated (kJ)'] ?? 0),
        'protein_g' => floatval($row['Protein (g)'] ?? 0),
        'fat_g' => floatval($row['Fat, total (g)'] ?? 0),
        'carb_g' => floatval($row['Available carbohydrate, without sugar alcohols (g)'] ?? 0),
        'sugars_g' => floatval($row['Total sugars (g)'] ?? 0),
        'sodium_mg' => floatval($row['Sodium (Na) (mg)'] ?? 0),
    ];
}

// Sort by score desc then name asc
usort($results, function($a,$b){
    if ($a['score'] == $b['score']) return strcmp($a['name'], $b['name']);
    return ($a['score'] > $b['score']) ? -1 : 1;
});

$results = array_slice($results, 0, 20);
echo json_encode(['results'=>$results, 'query'=>$q, 'expanded'=>$expanded]);
?>
