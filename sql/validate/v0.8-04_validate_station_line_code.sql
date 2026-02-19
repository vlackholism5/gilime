-- v0.8-04: Validation — station line_code populated. Run after STEP5 import. Do not execute inside Cursor.
-- SoT: docs/OPS/OPS_DB_MIGRATIONS.md, STEP5.1.

-- -----------------------------------------------------------------------------
-- 1) Total stations
-- -----------------------------------------------------------------------------
SELECT COUNT(*) AS total_stations FROM subway_stations_master;

-- -----------------------------------------------------------------------------
-- 2) Blank line_code count (must be 0)
-- -----------------------------------------------------------------------------
SELECT COUNT(*) AS blank_line_code
FROM subway_stations_master
WHERE line_code IS NULL OR TRIM(line_code) = '';

-- Expected: blank_line_code = 0. If > 0, run importer after header-map patch (STEP5.1).

-- -----------------------------------------------------------------------------
-- 3) Sample 20 rows (station_cd, station_name, line_code)
-- -----------------------------------------------------------------------------
SELECT station_cd, station_name, line_code
FROM subway_stations_master
ORDER BY station_cd
LIMIT 20;
