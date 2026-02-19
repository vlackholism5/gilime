<?php
declare(strict_types=1);

/**
 * 서울시 버스 노선-정류장 마스터 import
 *
 * 입력: data/inbound/seoul/bus/route_stop_master/서울시 노선 정류장마스터 정보.csv
 * 출력: seoul_bus_route_stop_master UPSERT(route_id + seq_in_route)
 *
 * 실행: php scripts/php/import_seoul_bus_route_stop_master_full.php
 */

require_once __DIR__ . '/../../app/inc/auth/db.php';

function normalizeHeaderKey2(string $s): string {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $s = str_replace([' ', "\t", "\r", "\n", '-', '_', '/', '\\', '(', ')', '[', ']', '.'], '', $s);
  return $s;
}

function pickColIndex2(array $headerMap, array $candidates): ?int {
  foreach ($candidates as $c) {
    $k = normalizeHeaderKey2((string)$c);
    if (isset($headerMap[$k])) return (int)$headerMap[$k];
  }
  return null;
}

function toUtf8_2(string $raw): string {
  if (mb_check_encoding($raw, 'UTF-8')) return $raw;
  return mb_convert_encoding($raw, 'UTF-8', 'EUC-KR');
}

$pdo = pdo();
$csvPath = __DIR__ . '/../../data/inbound/seoul/bus/route_stop_master/서울시 노선 정류장마스터 정보.csv';
if (!is_file($csvPath)) {
  fwrite(STDERR, "ERROR: CSV 파일이 없습니다: {$csvPath}\n");
  exit(1);
}

echo "=== 서울시 버스 노선-정류장 마스터 Import 시작 ===\n";
echo "[1] CSV 읽기: {$csvPath}\n";

$raw = file_get_contents($csvPath);
if ($raw === false) {
  fwrite(STDERR, "ERROR: CSV 읽기 실패\n");
  exit(1);
}
$utf8 = toUtf8_2($raw);
unset($raw);

$lines = preg_split('/\r\n|\r|\n/', $utf8, -1, PREG_SPLIT_NO_EMPTY);
if (!is_array($lines) || count($lines) < 2) {
  fwrite(STDERR, "ERROR: 데이터 행이 없습니다\n");
  exit(1);
}

$headers = str_getcsv((string)array_shift($lines));
$headers = array_map(function ($h) {
  $v = (string)$h;
  if (str_starts_with($v, "\xEF\xBB\xBF")) {
    $v = substr($v, 3);
  }
  return $v;
}, $headers);
$headerMap = [];
foreach ($headers as $idx => $h) {
  $headerMap[normalizeHeaderKey2((string)$h)] = (int)$idx;
}

$idxRouteId = pickColIndex2($headerMap, ['노선ID', '노선_ID', 'route_id', 'routeid', 'busRouteId']);
$idxSeq = pickColIndex2($headerMap, ['정류장순서', '정류장_순서', '순번', '순서', 'seq', 'seq_in_route', 'stationseq']);
$idxStopId = pickColIndex2($headerMap, ['정류장ID', '정류장_ID', 'stop_id', 'stationid', 'nodeid']);
$idxStopName = pickColIndex2($headerMap, ['정류장명', '정류장명칭', '정류장_명칭', 'stop_name', 'stationnm', 'nodenm']);
$idxArsId = pickColIndex2($headerMap, ['ars_id', 'arsid', 'ars번호', 'arsid번호', 'ars']);
$idxDir = pickColIndex2($headerMap, ['방향', '상하행', 'direction', 'direction_text']);

if ($idxRouteId === null || $idxSeq === null || ($idxStopName === null && $idxStopId === null)) {
  fwrite(STDERR, "ERROR: 필수 컬럼(route_id/seq/(stop_name 또는 stop_id))을 찾을 수 없습니다\n");
  fwrite(STDERR, "헤더: " . implode(', ', $headers) . "\n");
  exit(1);
}

echo "[2] UPSERT 준비\n";
$upsert = $pdo->prepare("
  INSERT IGNORE INTO seoul_bus_route_stop_master
    (route_id, seq_in_route, stop_id, stop_name, ars_id, direction_text, raw_json, created_at, updated_at)
  VALUES
    (:route_id, :seq, :stop_id, :stop_name, :ars_id, :direction_text, :raw_json, NOW(), NOW())
");

$inserted = 0;
$ignored = 0;
$skipped = 0;
$total = 0;

foreach ($lines as $line) {
  $total++;
  $row = str_getcsv($line);
  if (!is_array($row) || $row === []) {
    $skipped++;
    continue;
  }

  $routeId = (int)trim((string)($row[$idxRouteId] ?? ''));
  $seq = (int)trim((string)($row[$idxSeq] ?? ''));
  $stopName = $idxStopName !== null ? trim((string)($row[$idxStopName] ?? '')) : '';
  if ($routeId <= 0 || $seq <= 0) {
    $skipped++;
    continue;
  }

  $stopId = null;
  if ($idxStopId !== null) {
    $stopIdRaw = trim((string)($row[$idxStopId] ?? ''));
    $stopIdInt = (int)$stopIdRaw;
    if ($stopIdInt > 0) $stopId = $stopIdInt;
  }
  if ($stopName === '') {
    $stopName = $stopId !== null ? ('정류장ID:' . $stopId) : '미상';
  }
  $arsId = $idxArsId !== null ? trim((string)($row[$idxArsId] ?? '')) : '';
  $directionText = $idxDir !== null ? trim((string)($row[$idxDir] ?? '')) : '';

  $params = [
    ':route_id' => $routeId,
    ':seq' => $seq,
    ':stop_id' => $stopId,
    ':stop_name' => $stopName,
    ':ars_id' => $arsId !== '' ? $arsId : null,
    ':direction_text' => $directionText !== '' ? $directionText : null,
    ':raw_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
  ];
  $attempt = 0;
  $executed = false;
  while ($attempt < 3 && !$executed) {
    try {
      $upsert->execute($params);
      $executed = true;
    } catch (PDOException $e) {
      $msg = (string)$e->getMessage();
      $isLockError = (strpos($msg, '1205') !== false || strpos($msg, '1213') !== false);
      if (!$isLockError || $attempt >= 2) {
        throw $e;
      }
      usleep(200000 * ($attempt + 1));
      $attempt++;
    }
  }
  if (!$executed) {
    $skipped++;
    continue;
  }

  if ($upsert->rowCount() > 0) {
    $inserted++;
  } else {
    $ignored++;
  }
  if ($total % 50000 === 0) {
    echo "  처리 중: {$total} rows\n";
  }
}

$backfillStmt = $pdo->prepare("
  UPDATE seoul_bus_route_stop_master rs
  JOIN seoul_bus_stop_master sm ON sm.stop_id = rs.stop_id
  SET rs.stop_name = sm.stop_name,
      rs.updated_at = NOW()
  WHERE rs.stop_name LIKE '정류장ID:%'
");
$backfillStmt->execute();
$backfilled = (int)$backfillStmt->rowCount();

echo "[3] 완료\n";
echo "  total={$total}, inserted={$inserted}, ignored={$ignored}, skipped={$skipped}, backfilled={$backfilled}\n";
echo "OK: imported route_stop_master {$total} rows\n";
