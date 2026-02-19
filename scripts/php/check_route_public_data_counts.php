<?php
declare(strict_types=1);
/**
 * 길찾기 공공데이터 건수·stop_id NULL 점검 (ROUTE_FINDER_DIAGNOSIS 5.0)
 */
require_once __DIR__ . '/../../app/inc/auth/db.php';

$pdo = pdo();

function tableCount(PDO $pdo, string $table): int {
  $st = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
  ");
  $st->execute([':t' => $table]);
  $exists = (int)($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
  if ($exists <= 0) return -1;
  $row = $pdo->query("SELECT COUNT(*) AS c FROM {$table}")->fetch(PDO::FETCH_ASSOC);
  return (int)($row['c'] ?? 0);
}

$stopMaster = tableCount($pdo, 'seoul_bus_stop_master');
$routeMaster = tableCount($pdo, 'seoul_bus_route_master');
$routeStop = tableCount($pdo, 'seoul_bus_route_stop_master');

$stopIdNullCount = -1;
$stopIdNullPct = null;
if ($routeStop > 0) {
  try {
    $row = $pdo->query("SELECT COUNT(*) AS c FROM seoul_bus_route_stop_master WHERE stop_id IS NULL")->fetch(PDO::FETCH_ASSOC);
    $stopIdNullCount = (int)($row['c'] ?? 0);
    $stopIdNullPct = $routeStop > 0 ? round(100 * $stopIdNullCount / $routeStop, 1) : 0;
  } catch (Throwable $e) {
    $stopIdNullCount = -1;
  }
}

echo "seoul_bus_stop_master=" . $stopMaster . PHP_EOL;
echo "seoul_bus_route_master=" . $routeMaster . PHP_EOL;
echo "seoul_bus_route_stop_master=" . $routeStop . PHP_EOL;
if ($stopIdNullCount >= 0) {
  echo "route_stop_stop_id_null=" . $stopIdNullCount . " (" . ($stopIdNullPct ?? '') . "%)" . PHP_EOL;
}