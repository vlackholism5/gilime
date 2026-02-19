-- v0.8-05: Validate station line_code quality & provenance. Run after STEP5 import (hardened). Do not execute inside Cursor.
-- SoT: docs/OPS/OPS_DB_MIGRATIONS.md, STEP5.2.

-- -----------------------------------------------------------------------------
-- 1) Total & blank count
-- -----------------------------------------------------------------------------
SELECT COUNT(*) AS total_stations FROM subway_stations_master;

SELECT COUNT(*) AS blank_line_code
FROM subway_stations_master
WHERE line_code IS NULL OR TRIM(line_code) = '';

-- Expected: blank_line_code = 0 ideally. If > 0, they are listed below for manual remediation.

-- -----------------------------------------------------------------------------
-- 2) Count by line_code_source (from meta_json)
-- -----------------------------------------------------------------------------
SELECT
  JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.line_code_source')) AS line_code_source,
  COUNT(*) AS cnt
FROM subway_stations_master
WHERE meta_json IS NOT NULL AND meta_json != '{}'
GROUP BY JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.line_code_source'));

-- -----------------------------------------------------------------------------
-- 3) Unresolved & ambiguous stations (limit 50)
-- -----------------------------------------------------------------------------
SELECT station_cd, station_name, line_code, JSON_EXTRACT(meta_json, '$.line_code_source') AS line_code_source, JSON_EXTRACT(meta_json, '$.line_code_candidates') AS line_code_candidates
FROM subway_stations_master
WHERE JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.line_code_source')) IN ('unresolved', 'ambiguous')
ORDER BY station_cd
LIMIT 50;
