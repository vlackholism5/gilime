<?php
declare(strict_types=1);
/**
 * CTO 진단: 길찾기 특정 구간 동작 여부 점검
 *
 * 사용: php scripts/diagnose_route_finder.php "출발지정류장명" "도착지정류장명"
 * 예: php scripts/diagnose_route_finder.php "123전자타운.2001아울렛" "63빌딩.한강유람선"
 */
require_once __DIR__ . '/../../app/inc/auth/db.php';
require_once __DIR__ . '/../../app/inc/route/route_finder.php';

$from = $argv[1] ?? '';
$to = $argv[2] ?? '';

if ($from === '' || $to === '') {
  fwrite(STDERR, "Usage: php scripts/diagnose_route_finder.php \"출발지\" \"도착지\"\n");
  exit(1);
}

$pdo = pdo();

echo "=== 길찾기 CTO 진단: {$from} → {$to} ===\n\n";

// 1) 테이블 건수
echo "[1] 테이블 건수\n";
$tables = ['seoul_bus_stop_master', 'seoul_bus_route_master', 'seoul_bus_route_stop_master'];
foreach ($tables as $t) {
  try {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
    echo "  {$t}: {$cnt}\n";
  } catch (Throwable $e) {
    echo "  {$t}: ERROR - {$e->getMessage()}\n";
  }
}

// 2) resolve
echo "\n[2] 정류장 resolve\n";
$fromResolved = route_finder_resolve_stop($pdo, $from);
$toResolved = route_finder_resolve_stop($pdo, $to);

if ($fromResolved) {
  echo "  출발지: resolve OK → stop_id={$fromResolved['stop_id']}, stop_name={$fromResolved['stop_name']}\n";
} else {
  echo "  출발지: resolve FAIL (stop_master에 없음 또는 exact/LIKE 미매칭)\n";
}

if ($toResolved) {
  echo "  도착지: resolve OK → stop_id={$toResolved['stop_id']}, stop_name={$toResolved['stop_name']}\n";
} else {
  echo "  도착지: resolve FAIL\n";
}

// 3) route search
echo "\n[3] 경로 검색 (버스 + 임시셔틀)\n";
if ($fromResolved && $toResolved) {
  $routes = route_finder_search($pdo, $fromResolved['stop_id'], $toResolved['stop_id'], true);
  echo "  결과: " . count($routes) . "건\n";
  foreach ($routes as $i => $r) {
    $label = $r['route_type'] === 'bus' ? $r['route_name'] : $r['route_label'];
    echo "    " . ($i + 1) . ". {$label} ({$r['route_type']}) {$r['est_min']}분\n";
  }
  if (count($routes) === 0) {
    echo "  → 동일 노선 상 출발→도착 순차 경유 없는 구간 (경로 없음 정상)\n";
  }
} else {
  echo "  SKIP (resolve 실패)\n";
}

echo "\n=== 진단 완료 ===\n";
