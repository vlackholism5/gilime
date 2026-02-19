<?php
/**
 * Run v0.8-06 issue list (ambiguous/unresolved stations) via PDO.
 * Use when mysql CLI is not in PATH.
 * 실행: php scripts/run_station_line_code_issue_list.php
 * SoT: docs/OPS/OPS_DB_MIGRATIONS.md, STEP5.2
 */
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/db.php';

$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "
SELECT
  station_cd,
  station_name,
  line_code,
  JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.line_code_source')) AS line_code_source,
  JSON_EXTRACT(meta_json, '$.line_code_candidates') AS line_code_candidates,
  CASE
    WHEN JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.line_code_source')) = 'ambiguous'
      THEN 'Pick one from line_code_candidates or add multi-line model'
    WHEN JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.line_code_source')) = 'unresolved'
      THEN 'Expand upstream source or add station_cd->line_code mapping'
    ELSE NULL
  END AS suggested_next_action
FROM subway_stations_master
WHERE JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.line_code_source')) IN ('unresolved', 'ambiguous')
ORDER BY line_code_source, station_cd
LIMIT 100
";

echo "=== v0.8-06 station_line_code_issue_list ===\n\n";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "station_cd | station_name | line_code | line_code_source | line_code_candidates | suggested_next_action\n";
foreach ($rows as $r) {
    printf(
        "%s | %s | %s | %s | %s | %s\n",
        $r['station_cd'] ?? '',
        $r['station_name'] ?? '',
        $r['line_code'] ?? '',
        $r['line_code_source'] ?? '',
        $r['line_code_candidates'] ?? '',
        $r['suggested_next_action'] ?? ''
    );
}
echo "\nTotal: " . count($rows) . " rows.\n";
