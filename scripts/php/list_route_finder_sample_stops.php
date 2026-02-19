<?php
declare(strict_types=1);
/**
 * 길찾기 가능한 정류장 예시 목록 출력
 *
 * seoul_bus_route_stop_master에 등록된 정류장(버스 노선 경유)만 길찾기 검색 가능.
 * 자동완성/근처 정류장은 seoul_bus_stop_master 전체를 사용.
 *
 * 실행: php scripts/list_route_finder_sample_stops.php
 */
require_once __DIR__ . '/../../app/inc/auth/db.php';
require_once __DIR__ . '/../../app/inc/route/route_finder.php';

$pdo = pdo();
$stops = route_finder_sample_stops($pdo, 50);

echo "=== 길찾기 가능한 정류장 예시 (최대 50건) ===\n";
echo "※ 아래 정류장명을 정확히 입력하면 경로 검색이 가능합니다.\n";
echo "※ 데이터 미적재 시 목록이 비어 있을 수 있습니다.\n";
echo "※ CSV import: import_seoul_bus_stop_master_full.php, import_seoul_bus_route_master_full.php, import_seoul_bus_route_stop_master_full.php\n\n";

if ($stops === []) {
  echo "(목록 없음 - seoul_bus_* 테이블 데이터 확인 필요)\n";
  exit(0);
}

foreach ($stops as $s) {
  echo "- " . ($s['stop_name'] ?? '') . "\n";
}

echo "\n총 " . count($stops) . "건\n";
