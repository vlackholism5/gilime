<?php
declare(strict_types=1);

/**
 * 서울시 버스 노선 마스터 import
 *
 * 입력: data/inbound/seoul/bus/route_master/서울시 노선마스터 정보.csv
 * 출력: seoul_bus_route_master UPSERT
 *
 * 실행: php scripts/php/import_seoul_bus_route_master_full.php
 */

require_once __DIR__ . '/../../app/inc/auth/db.php';

function normalizeHeaderKey(string $s): string {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $s = str_replace([' ', "\t", "\r", "\n", '-', '_', '/', '\\', '(', ')', '[', ']', '.'], '', $s);
  return $s;
}

function pickColIndex(array $headerMap, array $candidates): ?int {
  foreach ($candidates as $c) {
    $k = normalizeHeaderKey((string)$c);
    if (isset($headerMap[$k])) return (int)$headerMap[$k];
  }
  return null;
}

function toUtf8(string $raw): string {
  if (mb_check_encoding($raw, 'UTF-8')) return $raw;
  return mb_convert_encoding($raw, 'UTF-8', 'EUC-KR');
}

$pdo = pdo();
$csvPath = __DIR__ . '/../../data/inbound/seoul/bus/route_master/서울시 노선마스터 정보.csv';
if (!is_file($csvPath)) {
  fwrite(STDERR, "ERROR: CSV 파일이 없습니다: {$csvPath}\n");
  exit(1);
}

echo "=== 서울시 버스 노선 마스터 Import 시작 ===\n";
echo "[1] CSV 읽기: {$csvPath}\n";

$raw = file_get_contents($csvPath);
if ($raw === false) {
  fwrite(STDERR, "ERROR: CSV 읽기 실패\n");
  exit(1);
}
$utf8 = toUtf8($raw);
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
  $headerMap[normalizeHeaderKey((string)$h)] = (int)$idx;
}

$idxRouteId = pickColIndex($headerMap, ['노선ID', '노선_ID', '노선id', 'route_id', 'routeid', 'busRouteId']);
$idxRouteName = pickColIndex($headerMap, ['노선명', '노선명칭', '노선_명칭', 'route_name', 'routeno', 'busRouteNm']);
$idxRouteType = pickColIndex($headerMap, ['노선유형', '노선타입', 'route_type', 'routeType']);
$idxStart = pickColIndex($headerMap, ['기점', '기점정류장명', 'start_stop_name', 'st_stanm']);
$idxEnd = pickColIndex($headerMap, ['종점', '종점정류장명', 'end_stop_name', 'ed_stanm']);
$idxTerm = pickColIndex($headerMap, ['배차간격', '배차간격분', 'term_min', 'term']);
$idxFirst = pickColIndex($headerMap, ['첫차시간', 'first_bus_time', 'firstbus_tm']);
$idxLast = pickColIndex($headerMap, ['막차시간', 'last_bus_time', 'lastbus_tm']);
$idxCorp = pickColIndex($headerMap, ['운수사', '운수회사', 'corp_name', 'corpnm', '회사명']);

if ($idxRouteId === null || $idxRouteName === null) {
  fwrite(STDERR, "ERROR: 필수 컬럼(route_id/route_name)을 찾을 수 없습니다\n");
  fwrite(STDERR, "헤더: " . implode(', ', $headers) . "\n");
  exit(1);
}

echo "[2] UPSERT 준비\n";
$upsert = $pdo->prepare("
  INSERT INTO seoul_bus_route_master
    (route_id, route_name, route_type, start_stop_name, end_stop_name, term_min, first_bus_time, last_bus_time, corp_name, raw_json, created_at, updated_at)
  VALUES
    (:route_id, :route_name, :route_type, :start_stop_name, :end_stop_name, :term_min, :first_bus_time, :last_bus_time, :corp_name, :raw_json, NOW(), NOW())
  ON DUPLICATE KEY UPDATE
    route_name = VALUES(route_name),
    route_type = VALUES(route_type),
    start_stop_name = VALUES(start_stop_name),
    end_stop_name = VALUES(end_stop_name),
    term_min = VALUES(term_min),
    first_bus_time = VALUES(first_bus_time),
    last_bus_time = VALUES(last_bus_time),
    corp_name = VALUES(corp_name),
    raw_json = VALUES(raw_json),
    updated_at = NOW()
");

$upserted = 0;
$skipped = 0;
$total = 0;

foreach ($lines as $line) {
  $total++;
  $row = str_getcsv($line);
  if (!is_array($row) || $row === []) {
    $skipped++;
    continue;
  }

  $routeIdRaw = trim((string)($row[$idxRouteId] ?? ''));
  $routeName = trim((string)($row[$idxRouteName] ?? ''));
  $routeId = (int)$routeIdRaw;
  if ($routeId <= 0 || $routeName === '') {
    $skipped++;
    continue;
  }

  $routeType = $idxRouteType !== null ? trim((string)($row[$idxRouteType] ?? '')) : '';
  $startStop = $idxStart !== null ? trim((string)($row[$idxStart] ?? '')) : '';
  $endStop = $idxEnd !== null ? trim((string)($row[$idxEnd] ?? '')) : '';
  $termRaw = $idxTerm !== null ? trim((string)($row[$idxTerm] ?? '')) : '';
  $firstBus = $idxFirst !== null ? trim((string)($row[$idxFirst] ?? '')) : '';
  $lastBus = $idxLast !== null ? trim((string)($row[$idxLast] ?? '')) : '';
  $corp = $idxCorp !== null ? trim((string)($row[$idxCorp] ?? '')) : '';

  $termMin = null;
  if ($termRaw !== '') {
    $termDigits = preg_replace('/[^0-9]/', '', $termRaw);
    if ($termDigits !== null && $termDigits !== '') {
      $termMin = (int)$termDigits;
    }
  }

  $upsert->execute([
    ':route_id' => $routeId,
    ':route_name' => $routeName,
    ':route_type' => $routeType !== '' ? $routeType : null,
    ':start_stop_name' => $startStop !== '' ? $startStop : null,
    ':end_stop_name' => $endStop !== '' ? $endStop : null,
    ':term_min' => $termMin,
    ':first_bus_time' => $firstBus !== '' ? $firstBus : null,
    ':last_bus_time' => $lastBus !== '' ? $lastBus : null,
    ':corp_name' => $corp !== '' ? $corp : null,
    ':raw_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
  ]);

  $upserted++;
}

echo "[3] 완료\n";
echo "  total={$total}, upserted={$upserted}, skipped={$skipped}\n";
echo "OK: imported route_master {$total} rows\n";
