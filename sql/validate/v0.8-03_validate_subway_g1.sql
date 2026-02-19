-- v0.8-03: Validation only — run after v0.8-03_import_subway_g1.sql. Do not execute inside Cursor.
-- SoT: docs/OPS/OPS_DB_MIGRATIONS.md, STEP5.

-- -----------------------------------------------------------------------------
-- 1) Counts
-- -----------------------------------------------------------------------------
SELECT 'subway_stations_master' AS tbl, COUNT(*) AS cnt FROM subway_stations_master
UNION ALL
SELECT 'subway_edges_g1', COUNT(*) FROM subway_edges_g1;

-- Expected (STEP5): subway_stations_master >= 300, subway_edges_g1 = 270 (or slightly less if dedup applied)

-- -----------------------------------------------------------------------------
-- 2) Duplicates check
-- -----------------------------------------------------------------------------
SELECT line_code, from_station_cd, to_station_cd, COUNT(*) AS dup_cnt
FROM subway_edges_g1
GROUP BY line_code, from_station_cd, to_station_cd
HAVING COUNT(*) > 1;

-- Expected: 0 rows (duplicates = 0). If re-ran import without truncating, duplicates may appear; then document or add UNIQUE.

SELECT station_cd, COUNT(*) AS dup_cnt
FROM subway_stations_master
GROUP BY station_cd
HAVING COUNT(*) > 1;

-- Expected: 0 rows

-- -----------------------------------------------------------------------------
-- 3) Null critical fields check
-- -----------------------------------------------------------------------------
SELECT 'stations: null station_cd' AS check_name, COUNT(*) AS cnt FROM subway_stations_master WHERE station_cd IS NULL OR station_cd = ''
UNION ALL
SELECT 'stations: null station_name', COUNT(*) FROM subway_stations_master WHERE station_name IS NULL OR station_name = ''
UNION ALL
SELECT 'edges: null line_code', COUNT(*) FROM subway_edges_g1 WHERE line_code IS NULL OR line_code = ''
UNION ALL
SELECT 'edges: null from_station_cd', COUNT(*) FROM subway_edges_g1 WHERE from_station_cd IS NULL OR from_station_cd = ''
UNION ALL
SELECT 'edges: null to_station_cd', COUNT(*) FROM subway_edges_g1 WHERE to_station_cd IS NULL OR to_station_cd = '';

-- Expected: all 0 or document acceptable exceptions

-- -----------------------------------------------------------------------------
-- 4) Sample rows
-- -----------------------------------------------------------------------------
SELECT id, station_cd, station_name, line_code, lat, lon, match_confidence FROM subway_stations_master ORDER BY id LIMIT 5;

SELECT id, line_code, from_station_cd, to_station_cd, distance_m, time_sec FROM subway_edges_g1 ORDER BY id LIMIT 5;

-- -----------------------------------------------------------------------------
-- 5) Sanity: distance_m / time_sec > 0 rate
-- -----------------------------------------------------------------------------
SELECT
  COUNT(*) AS total,
  SUM(CASE WHEN distance_m > 0 THEN 1 ELSE 0 END) AS distance_positive,
  SUM(CASE WHEN time_sec > 0 THEN 1 ELSE 0 END) AS time_positive
FROM subway_edges_g1;

-- Expected: distance_positive and time_positive close to total (or document zero values)
