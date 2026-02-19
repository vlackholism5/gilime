<?php
/**
 * Subway G1 import from CSV (LOAD DATA 권한 없을 때 대안)
 * - data/derived/seoul/subway/subway_station_match_v1.csv → subway_stations_master
 * - data/derived/seoul/subway/subway_edges_g1_v1.csv → subway_edges_g1
 * 실행: php scripts/import_subway_g1_from_csv.php
 * v0.8-03. SoT: docs/OPS/OPS_DB_MIGRATIONS.md
 */
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/db.php';

$repoRoot = dirname(__DIR__, 2);
$stationCsv = $repoRoot . '/data/derived/seoul/subway/subway_station_match_v1.csv';
$edgesCsv   = $repoRoot . '/data/derived/seoul/subway/subway_edges_g1_v1.csv';

if (!file_exists($stationCsv)) {
    die("ERROR: CSV 없음: {$stationCsv}\n");
}
if (!file_exists($edgesCsv)) {
    die("ERROR: CSV 없음: {$edgesCsv}\n");
}

$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Subway G1 import (CSV → DB) ===\n\n";

// ----- Stations -----
$fp = fopen($stationCsv, 'r');
if (!$fp) die("ERROR: station CSV open failed\n");
$header = fgetcsv($fp);
if (!$header) { fclose($fp); die("ERROR: no header\n"); }
// BOM strip
if (isset($header[0]) && substr($header[0], 0, 3) === "\xef\xbb\xbf") {
    $header[0] = substr($header[0], 3);
}
$colMap = [];
foreach ($header as $i => $h) {
    $colMap[trim((string)$h)] = $i;
}
$required = ['osm_name', 'osm_lat', 'osm_lon', 'osm_full_id', 'station_cd', 'station_name', 'line_code', 'fr_code', 'match_level', 'confidence', 'reason'];
$missing = array_diff($required, array_keys($colMap));
if ($missing !== []) {
    fclose($fp);
    die("ERROR: station CSV missing required headers: " . implode(', ', $missing) . "\nActual headers: " . implode(', ', array_keys($colMap)) . "\n");
}
$val = function (array $row, string $name) use ($colMap): string {
    $idx = $colMap[$name] ?? -1;
    return $idx >= 0 ? trim((string)($row[$idx] ?? '')) : '';
};
$ins = $pdo->prepare("
  INSERT INTO subway_stations_master (station_cd, station_name, line_code, fr_code, lat, lon, osm_full_id, match_confidence, meta_json)
  VALUES (:station_cd, :station_name, :line_code, :fr_code, :lat, :lon, :osm_full_id, :match_confidence, :meta_json)
  ON DUPLICATE KEY UPDATE
    station_name = VALUES(station_name), line_code = VALUES(line_code), fr_code = VALUES(fr_code),
    lat = VALUES(lat), lon = VALUES(lon), osm_full_id = VALUES(osm_full_id),
    match_confidence = VALUES(match_confidence), meta_json = VALUES(meta_json), updated_at = NOW()
");
$stationCount = 0;
$blankLineCodeCount = 0;
while (($row = fgetcsv($fp)) !== false) {
    $station_cd = $val($row, 'station_cd');
    $station_name = $val($row, 'station_name');
    $line_code = $val($row, 'line_code');
    if ($line_code === '') $blankLineCodeCount++;
    if ($station_cd === '') {
        $station_cd = ($line_code !== '' ? $line_code : 'X') . '_' . ($station_name !== '' ? $station_name : 'unknown');
    }
    $lat = $val($row, 'osm_lat');
    $lon = $val($row, 'osm_lon');
    $meta = [
        'match_level' => $val($row, 'match_level') ?: 'NONE',
        'reason' => $val($row, 'reason'),
        'osm_name' => $val($row, 'osm_name'),
    ];
    if ($line_code !== '') {
        $meta['line_code_source'] = 'from_csv';
    }
    $ins->execute([
        ':station_cd' => $station_cd,
        ':station_name' => $station_name ?: '',
        ':line_code' => $line_code ?: '',
        ':fr_code' => $val($row, 'fr_code') ?: null,
        ':lat' => ($lat !== '' && is_numeric($lat)) ? (float)$lat : null,
        ':lon' => ($lon !== '' && is_numeric($lon)) ? (float)$lon : null,
        ':osm_full_id' => $val($row, 'osm_full_id') ?: null,
        ':match_confidence' => ($val($row, 'confidence') !== '' && is_numeric($val($row, 'confidence'))) ? (float)$val($row, 'confidence') : null,
        ':meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
    ]);
    $stationCount++;
}
fclose($fp);
echo "[1] subway_stations_master: processed={$stationCount} inserted/upserted={$stationCount} blank_line_code_in_csv={$blankLineCodeCount}\n";

// ----- Edges -----
$fp = fopen($edgesCsv, 'r');
if (!$fp) die("ERROR: edges CSV open failed\n");
$header = fgetcsv($fp);
if (!$header) { fclose($fp); die("ERROR: no header\n"); }
$insE = $pdo->prepare("
  INSERT INTO subway_edges_g1 (line_code, from_station_cd, to_station_cd, distance_m, time_sec, meta_json)
  VALUES (:line_code, :from_station_cd, :to_station_cd, :distance_m, :time_sec, NULL)
");
$edgeCount = 0;
while (($row = fgetcsv($fp)) !== false) {
    if (count($row) < 5) continue;
    $line_code = trim($row[0] ?? '');
    $from_cd = trim($row[1] ?? '');
    $to_cd = trim($row[2] ?? '');
    if ($line_code === '' || $from_cd === '' || $to_cd === '') continue;
    $dist = (trim($row[3] ?? '') !== '' && is_numeric(trim($row[3]))) ? (float)trim($row[3]) : null;
    $time_sec = (trim($row[4] ?? '') !== '' && is_numeric(trim($row[4]))) ? (int)trim($row[4]) : null;
    $insE->execute([
        ':line_code' => $line_code,
        ':from_station_cd' => $from_cd,
        ':to_station_cd' => $to_cd,
        ':distance_m' => $dist,
        ':time_sec' => $time_sec,
    ]);
    $edgeCount++;
}
fclose($fp);
echo "[2] subway_edges_g1: {$edgeCount} rows\n";

// ----- Backfill line_code (hardened: only unambiguous mappings) -----
$nameToLineCodes = [];
$lineCodesInEdges = [];
foreach ($pdo->query("SELECT line_code, from_station_cd AS station_name FROM subway_edges_g1 UNION ALL SELECT line_code, to_station_cd FROM subway_edges_g1") as $r) {
    $name = trim((string)($r['station_name'] ?? ''));
    $lc = trim((string)($r['line_code'] ?? ''));
    if ($name === '' || $lc === '') continue;
    $nameToLineCodes[$name] = $nameToLineCodes[$name] ?? [];
    if (!in_array($lc, $nameToLineCodes[$name], true)) {
        $nameToLineCodes[$name][] = $lc;
    }
    $lineCodesInEdges[$lc] = true;
}
$edgeLineCodes = array_keys($lineCodesInEdges);

$blankBefore = (int)$pdo->query("SELECT COUNT(*) AS c FROM subway_stations_master WHERE line_code IS NULL OR TRIM(line_code) = ''")->fetch(PDO::FETCH_ASSOC)['c'];
$filledByEdges = 0;
$filledByCdPrefix = 0;
$ambiguousNames = [];
$unresolvedNames = [];

$updStation = $pdo->prepare("UPDATE subway_stations_master SET line_code = :lc, meta_json = :meta WHERE id = :id");
$updMeta = $pdo->prepare("UPDATE subway_stations_master SET meta_json = :meta WHERE id = :id");

foreach ($pdo->query("SELECT id, station_cd, station_name, meta_json FROM subway_stations_master WHERE line_code IS NULL OR TRIM(line_code) = ''") as $s) {
    $id = (int)$s['id'];
    $name = trim((string)($s['station_name'] ?? ''));
    $cd = trim((string)($s['station_cd'] ?? ''));
    $meta = json_decode((string)($s['meta_json'] ?? '{}'), true) ?: [];

    $lcs = $nameToLineCodes[$name] ?? [];
    if (count($lcs) === 1) {
        $lc = $lcs[0];
        $meta['line_code_source'] = 'edges_unique';
        unset($meta['line_code_candidates']);
        $updStation->execute([':lc' => $lc, ':meta' => json_encode($meta, JSON_UNESCAPED_UNICODE), ':id' => $id]);
        $filledByEdges++;
    } elseif (count($lcs) > 1) {
        sort($lcs);
        $meta['line_code_source'] = 'ambiguous';
        $meta['line_code_candidates'] = $lcs;
        $updMeta->execute([':meta' => json_encode($meta, JSON_UNESCAPED_UNICODE), ':id' => $id]);
        $ambiguousNames[] = $name;
    } else {
        $derived = null;
        if (preg_match('/^([0-9]{2})/', $cd, $m)) {
            $derived = (string)(int)$m[1];
            if ($derived === '0') $derived = $m[1];
        }
        if ($derived !== null && in_array($derived, $edgeLineCodes, true)) {
            $meta['line_code_source'] = 'cd_prefix';
            unset($meta['line_code_candidates']);
            $updStation->execute([':lc' => $derived, ':meta' => json_encode($meta, JSON_UNESCAPED_UNICODE), ':id' => $id]);
            $filledByCdPrefix++;
        } else {
            $meta['line_code_source'] = 'unresolved';
            $updMeta->execute([':meta' => json_encode($meta, JSON_UNESCAPED_UNICODE), ':id' => $id]);
            $unresolvedNames[] = $name;
        }
    }
}

echo "[3] line_code backfill: filled_by_edges={$filledByEdges} filled_by_cd_prefix={$filledByCdPrefix} ambiguous=" . count($ambiguousNames) . " unresolved=" . count($unresolvedNames) . "\n";

$qaDir = $repoRoot . '/data/derived/seoul/subway';
if (!is_dir($qaDir)) {
    mkdir($qaDir, 0755, true);
}
$totalStations = (int)$pdo->query("SELECT COUNT(*) AS c FROM subway_stations_master")->fetch(PDO::FETCH_ASSOC)['c'];
$qa = [
    'total_stations' => $totalStations,
    'blank_before' => $blankBefore,
    'filled_by_edges' => $filledByEdges,
    'filled_by_cd_prefix' => $filledByCdPrefix,
    'ambiguous_station_names_topN' => array_slice(array_unique($ambiguousNames), 0, 50),
    'unresolved_station_names_topN' => array_slice(array_unique($unresolvedNames), 0, 50),
];
file_put_contents($qaDir . '/_qa_station_line_code_backfill_v1.json', json_encode($qa, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "[4] QA report: data/derived/seoul/subway/_qa_station_line_code_backfill_v1.json\n";

echo "\nOK. Verify: php scripts/php/run_validate_station_line_code.php, sql/validate/v0.8-05_validate_station_line_code_quality.sql\n";
