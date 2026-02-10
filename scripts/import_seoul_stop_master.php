<?php
declare(strict_types=1);

/**
 * v0.6-10: 서울시 버스 정류장 마스터 CSV → seoul_bus_stop_master upsert
 * 입력: data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv (euc-kr)
 * 중복 실행 시 결과 동일(idempotent)
 */

$baseDir = dirname(__DIR__);
require_once $baseDir . '/app/inc/db.php';

$csvRel = 'data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv';
$csvPath = $baseDir . '/' . $csvRel;

if (!is_file($csvPath)) {
  fwrite(STDERR, "File not found: {$csvRel}\n");
  exit(1);
}

$raw = file_get_contents($csvPath);
$utf8 = mb_convert_encoding($raw, 'UTF-8', 'EUC-KR');
unset($raw);

$lines = preg_split('/\r\n|\r|\n/', $utf8, -1, PREG_SPLIT_NO_EMPTY);
if (count($lines) < 2) {
  fwrite(STDERR, "CSV has no data rows\n");
  exit(1);
}

// 헤더: 정류장_ID, 정류장_명칭, 정류장_유형, 정류장_번호, 위도, 경도, 자치구정류장코드분류_시군구_코드_등
$header = str_getcsv(array_shift($lines), ',', '"');
$pdo = pdo();

$upsert = $pdo->prepare("
  INSERT INTO seoul_bus_stop_master (stop_id, stop_name, district_code, lat, lng, raw_json, created_at, updated_at)
  VALUES (:stop_id, :stop_name, :district_code, :lat, :lng, :raw_json, NOW(), NOW())
  ON DUPLICATE KEY UPDATE
    stop_name = VALUES(stop_name),
    district_code = VALUES(district_code),
    lat = VALUES(lat),
    lng = VALUES(lng),
    raw_json = VALUES(raw_json),
    updated_at = NOW()
");

$rowNum = 0;
$inserted = 0;
$updated = 0;

foreach ($lines as $line) {
  $rowNum++;
  $cols = str_getcsv($line, ',', '"');
  if (count($cols) < 6) {
    continue;
  }
  $stopId = trim($cols[0] ?? '');
  if ($stopId === '') {
    continue;
  }
  $stopIdInt = (int) $stopId;
  if ($stopIdInt <= 0) {
    continue;
  }
  $stopName = trim($cols[1] ?? '');
  $districtCode = isset($cols[6]) ? trim($cols[6]) : null;
  if ($districtCode === '') {
    $districtCode = null;
  }
  $lat = isset($cols[4]) && $cols[4] !== '' ? (float) $cols[4] : null;
  $lng = isset($cols[5]) && $cols[5] !== '' ? (float) $cols[5] : null;
  $rawJson = json_encode([
    'crtr_type' => $cols[2] ?? null,
    'crtr_no'   => $cols[3] ?? null,
  ], JSON_UNESCAPED_UNICODE) ?: null;

  $upsert->execute([
    ':stop_id'       => $stopIdInt,
    ':stop_name'     => $stopName,
    ':district_code' => $districtCode,
    ':lat'           => $lat,
    ':lng'           => $lng,
    ':raw_json'      => $rawJson,
  ]);
  if ($upsert->rowCount() === 1) {
    $inserted++;
  } else {
    $updated++;
  }
}

echo "OK rows_processed={$rowNum} inserted={$inserted} updated={$updated}\n";
exit(0);
