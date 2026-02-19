<?php
/**
 * Run v0.8-04 validation (station line_code) via PDO.
 * Use when mysql CLI is not in PATH.
 * 실행: php scripts/run_validate_station_line_code.php
 * SoT: docs/OPS/OPS_DB_MIGRATIONS.md, STEP5.1
 */
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/db.php';

$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== v0.8-04 validate_station_line_code ===\n\n";

$q1 = $pdo->query("SELECT COUNT(*) AS total_stations FROM subway_stations_master");
$r1 = $q1->fetch(PDO::FETCH_ASSOC);
echo "total_stations: " . ($r1['total_stations'] ?? 0) . "\n\n";

$q2 = $pdo->query("SELECT COUNT(*) AS blank_line_code FROM subway_stations_master WHERE line_code IS NULL OR TRIM(line_code) = ''");
$r2 = $q2->fetch(PDO::FETCH_ASSOC);
$blank = (int)($r2['blank_line_code'] ?? 0);
echo "blank_line_code: {$blank}" . ($blank === 0 ? " (PASS)" : " (FAIL — must be 0)") . "\n\n";

echo "sample 20 rows (station_cd, station_name, line_code):\n";
$q3 = $pdo->query("SELECT station_cd, station_name, line_code FROM subway_stations_master ORDER BY station_cd LIMIT 20");
while ($row = $q3->fetch(PDO::FETCH_ASSOC)) {
    printf("  %s | %s | %s\n", $row['station_cd'] ?? '', $row['station_name'] ?? '', $row['line_code'] ?? '');
}
echo "\nOK.\n";
