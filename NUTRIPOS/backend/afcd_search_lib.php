<?php
// backend/afcd_search_lib.php
require_once __DIR__.'/afcd_cache.php';

function afcd_synonyms(): array {
  return [
    'bbq'      => ['barbecue','barbeque','barbecue sauce','sauce barbecue','bbq sauce'],
    'ketchup'  => ['tomato sauce','catsup'],
    'patty'    => ['hamburger patty','beef patty','burger patty','pattie'],
    'burger'   => ['hamburger','cheeseburger','beef burger'],
    'roll'     => ['bun','white roll','bread roll'],
    'wrap'     => ['tortilla','tortilla wrap','large tortilla wrap'],
    'mince'    => ['minced','ground beef','beef mince','minced beef'],
    'chips'    => ['fries','french fries'],
    'soda'     => ['soft drink','cola','fizzy drink'],
    'lasagna'  => ['lasagne'],
    'beetroot' => ['beet'],
    'lettuce'  => ['iceberg lettuce','iceberg'],
    'mayo'     => ['mayonnaise'],
  ];
}

function afcd_norm(string $s): string {
  $s = mb_strtolower($s);
  $s = preg_replace('/\s+/', ' ', trim($s));
  return $s;
}

function afcd_expand_terms(string $q, array $syn): array {
  $terms = [$q];
  foreach ($syn as $k=>$alts){
    if (mb_strpos(mb_strtolower($q), $k) !== false) {
      $terms = array_merge($terms, $alts);
    }
  }
  // if single bare words, add a couple of common expansions
  if (preg_match('/\bbeef\b/i', $q)) $terms[] = 'beef, all cuts';
  if (preg_match('/\bbun\b/i', $q))  $terms[] = 'bread roll';
  return array_values(array_unique($terms));
}

function afcd_score_row(string $name, string $code, string $category, string $q, array $expanded): int {
  $n = afcd_norm($name);
  $qnorm = afcd_norm($q);

  if ($code && stripos($code, $q) === 0) return 200;
  if ($n === $qnorm) return 180;
  if (strpos($n, $qnorm) === 0) return 160;

  $score = 0;

  // token overlap
  $ntoks = preg_split('/[^a-z0-9]+/i', $n, -1, PREG_SPLIT_NO_EMPTY);
  $qtoks = preg_split('/[^a-z0-9]+/i', $qnorm, -1, PREG_SPLIT_NO_EMPTY);
  $overlap = count(array_intersect($ntoks, $qtoks));
  $score += $overlap * 20;

  if (strpos($n, $qnorm) !== false) $score += 40;

  foreach ($expanded as $ex){
    $exn = afcd_norm($ex);
    if ($exn && strpos($n, $exn) !== false) $score += 25;
  }

  if (function_exists('levenshtein')){
    $dist = levenshtein(substr($n,0,64), substr($qnorm,0,64));
    if ($dist <= 2) $score += (50 - $dist*10);
  }

  if ($category){
    $cl = mb_strtolower($category);
    if (strpos($cl, 'beef') !== false && strpos($qnorm,'beef') !== false) $score += 20;
    if (strpos($cl, 'hamburger') !== false || strpos($cl, 'mixed dishes') !== false) $score += 10;
    if (strpos($cl, 'savoury sauces') !== false && (strpos($qnorm,'bbq')!==false || strpos($qnorm,'sauce')!==false)) $score += 10;
  }

  // shorter names get a slight bump
  $score += max(0, 30 - strlen($name)/8);

  return $score;
}

/** Return top N results */
function afcd_search(string $query, array $rows, int $limit=20): array {
  $q = trim($query);
  if (mb_strlen($q) < 2) return [];
  $syn = afcd_synonyms();
  $expanded = afcd_expand_terms($q, $syn);

  $res = [];
  foreach ($rows as $row){
    $name = $row['Food Name'] ?? '';
    if ($name === '') continue;
    $code = $row['Public Food Key'] ?? '';
    $category = $row['Classification'] ?? '';
    $score = afcd_score_row($name, $code, $category, $q, $expanded);
    if ($score <= 0) continue;

    // quick tag hints
    $tags = [];
    $ln = mb_strtolower($name);
    if (str_contains($ln,'cheese')) $tags[] = 'Cheese';
    if (str_contains($ln,'hamburger') || str_contains($ln,'burger')) $tags[] = 'Burger';
    if (str_contains($ln,'lasagne') || str_contains($ln,'lasagna')) $tags[] = 'Lasagna';
    if (str_contains($ln,'beef')) $tags[] = 'Beef';
    if (str_contains($ln,'barbecue') || str_contains($ln,'bbq')) $tags[] = 'BBQ sauce';

    $res[] = [
      'code'       => $code,
      'name'       => $name,
      'category'   => $category,
      'score'      => $score,
      'tags'       => $tags,
      'energy_kj'  => (float)($row['Energy with dietary fibre, equated (kJ)'] ?? 0),
      'protein_g'  => (float)($row['Protein (g)'] ?? 0),
      'fat_g'      => (float)($row['Fat, total (g)'] ?? 0),
      'carb_g'     => (float)($row['Available carbohydrate, without sugar alcohols (g)'] ?? 0),
      'sugars_g'   => (float)($row['Total sugars (g)'] ?? 0),
      'sodium_mg'  => (float)($row['Sodium (Na) (mg)'] ?? 0),
      '_row'       => $row, // carry through for pick-best
    ];
  }

  usort($res, fn($a,$b)=> ($a['score']===$b['score']) ? strcmp($a['name'],$b['name']) : (($a['score']>$b['score'])?-1:1));
  return array_slice($res, 0, $limit);
}

/** Best single row for a free text query (returns the raw AFCD row or null) */
function afcd_best_row(string $query, array $rows): ?array {
  $hits = afcd_search($query, $rows, 1);
  if (!$hits) return null;
  return $hits[0]['_row'] ?? null;
}
