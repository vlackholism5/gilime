<?php
/**
 * 서울시 버스 정류장 마스터 실데이터 import
 * 
 * 입력: data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv (euc-kr)
 * 출력: seoul_bus_stop_master 테이블 UPSERT
 * 
 * 실행: php scripts/php/import_seoul_bus_stop_master_full.php
 * 
 * Idempotent: stop_id(PK) 기준 INSERT ... ON DUPLICATE KEY UPDATE
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/db.php';

$pdo = pdo();

echo "=== 서울시 버스 정류장 마스터 Import 시작 ===\n\n";

// 입력 파일 경로
$csvPath = __DIR__ . '/../../data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv';

if (!file_exists($csvPath)) {
    die("ERROR: CSV 파일이 없습니다: {$csvPath}\n");
}

echo "[1] CSV 파일 읽기: {$csvPath}\n";

// CSV 열기 (euc-kr)
$fp = fopen($csvPath, 'r');
if (!$fp) {
    die("ERROR: 파일 열기 실패\n");
}

// 첫 줄(헤더) 읽기
$headerLine = fgets($fp);
if (!$headerLine) {
    die("ERROR: 헤더 읽기 실패\n");
}

// euc-kr → utf-8 변환
$headerUtf8 = mb_convert_encoding($headerLine, 'UTF-8', 'EUC-KR');
$headers = str_getcsv(trim($headerUtf8));

echo "  헤더: " . implode(', ', $headers) . "\n";
echo "  (예상: 정류장ID, 정류장명칭, 시군구코드, 위도, 경도 등)\n\n";

// 헤더 → 컬럼 매핑 (실제 CSV 컬럼명에 맞게 조정 필요)
// 일반적인 서울시 공공데이터 형식 기준
$colMap = [
    'stop_id' => '정류장ID',
    'stop_name' => '정류장명칭',
    'district_code' => '시군구코드',
    'lat' => '위도',
    'lng' => '경도',
];

// 헤더 인덱스 찾기
$colIdx = [];
foreach ($colMap as $field => $colName) {
    $idx = array_search($colName, $headers, true);
    if ($idx === false) {
        // 대체 컬럼명 시도
        $altNames = [
            'stop_id' => ['정류장_ID', 'STOP_ID', 'arsId', 'ARS_ID', 'stationId'],
            'stop_name' => ['정류장_명칭', 'STOP_NAME', 'stationNm', 'stNm'],
            'district_code' => ['DISTRICT_CODE', 'districtCd'],
            'lat' => ['위도', 'LAT', 'latitude', 'gpsY', 'posY'],
            'lng' => ['경도', 'LNG', 'longitude', 'gpsX', 'posX'],
        ];
        
        if (isset($altNames[$field])) {
            foreach ($altNames[$field] as $alt) {
                $idx = array_search($alt, $headers, true);
                if ($idx !== false) break;
            }
        }
    }
    
    if ($idx !== false) {
        $colIdx[$field] = (int)$idx;
    }
}

if (!isset($colIdx['stop_id']) || !isset($colIdx['stop_name'])) {
    die("ERROR: 필수 컬럼(정류장ID, 정류장명칭)을 찾을 수 없습니다.\n헤더를 확인하세요: " . implode(', ', $headers) . "\n");
}

echo "[2] UPSERT 준비\n";

$stmt = $pdo->prepare("
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

$inserted = 0;
$updated = 0;
$skipped = 0;
$total = 0;

echo "[3] 데이터 처리 중...\n";

while (($line = fgets($fp)) !== false) {
    $total++;
    
    // euc-kr → utf-8
    $lineUtf8 = mb_convert_encoding($line, 'UTF-8', 'EUC-KR');
    $row = str_getcsv(trim($lineUtf8));
    
    // 컬럼 추출
    $stopId = isset($colIdx['stop_id']) ? trim($row[$colIdx['stop_id']] ?? '') : '';
    $stopName = isset($colIdx['stop_name']) ? trim($row[$colIdx['stop_name']] ?? '') : '';
    $districtCode = isset($colIdx['district_code']) ? trim($row[$colIdx['district_code']] ?? '') : null;
    $lat = isset($colIdx['lat']) ? trim($row[$colIdx['lat']] ?? '') : null;
    $lng = isset($colIdx['lng']) ? trim($row[$colIdx['lng']] ?? '') : null;
    
    // 필수값 검증
    if ($stopId === '' || $stopName === '') {
        $skipped++;
        continue;
    }
    
    // 좌표 변환
    $latVal = ($lat !== null && $lat !== '') ? (float)$lat : null;
    $lngVal = ($lng !== null && $lng !== '') ? (float)$lng : null;
    
    // raw_json (원본 행 전체, 디버깅용)
    $rawJson = json_encode($row, JSON_UNESCAPED_UNICODE);
    
    // 기존 레코드 확인
    $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM seoul_bus_stop_master WHERE stop_id = ?");
    $existsStmt->execute([$stopId]);
    $exists = (int)$existsStmt->fetchColumn() > 0;
    
    // UPSERT
    $stmt->execute([
        ':stop_id' => $stopId,
        ':stop_name' => $stopName,
        ':district_code' => $districtCode ?: null,
        ':lat' => $latVal,
        ':lng' => $lngVal,
        ':raw_json' => $rawJson,
    ]);
    
    if ($exists) {
        $updated++;
    } else {
        $inserted++;
    }
    
    // 진행 표시 (1000건마다)
    if ($total % 1000 === 0) {
        echo "  처리 중: {$total}건...\n";
    }
}

fclose($fp);

echo "\n[4] Import 완료\n";
echo "  Total: {$total}건\n";
echo "  Inserted: {$inserted}건\n";
echo "  Updated: {$updated}건\n";
echo "  Skipped: {$skipped}건\n\n";

// 최종 건수
$finalCount = $pdo->query("SELECT COUNT(*) FROM seoul_bus_stop_master")->fetchColumn();
echo "  seoul_bus_stop_master 최종 건수: {$finalCount}건\n\n";

echo "OK: imported {$total} rows\n";
