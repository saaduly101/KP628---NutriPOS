<?php
function loadAFCDData($path) {
    static $cache = null;
    if ($cache !== null) return $cache;
    if (!file_exists($path)) return [];

    $csv = fopen($path, 'r');
    if (!$csv) return [];
    $raw_headers = fgetcsv($csv);

    // Clean headers: remove BOM, newlines, trim
    $headers = array_map(function ($h) {
        $h = str_replace("\xEF\xBB\xBF", '', $h);
        $h = preg_replace("/\r|\n/", "", $h);
        return trim($h);
    }, $raw_headers);

    $foods = [];
    while (($row = fgetcsv($csv)) !== false) {
        if (count($row) !== count($headers)) continue;
        $item = array_combine($headers, $row);
        $foods[] = $item;
    }
    fclose($csv);
    $cache = $foods;
    return $foods;
}
?>
