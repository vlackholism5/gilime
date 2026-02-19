-- v0.8-03: Import subway G1 from derived CSVs into subway_stations_master and subway_edges_g1
-- Prerequisite: v0.8-01 graph schema applied. Run locally (Workbench/CLI). Do not execute inside Cursor.
-- SoT: docs/OPS/OPS_DB_MIGRATIONS.md, STEP5.
--
-- Step order: (1) Run DDL for both staging tables. (2) Uncomment and run LOAD DATA for stations, then INSERT stations. (3) Uncomment and run LOAD DATA for edges, then INSERT edges. (4) Run v0.8-03_validate_subway_g1.sql.
-- LOAD DATA requires LOCAL; enable with: SET GLOBAL local_infile = 1; (if permitted).

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1) Staging table: station match CSV
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS _staging_subway_station_match_v1;
CREATE TABLE _staging_subway_station_match_v1 (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  osm_name        VARCHAR(255),
  osm_lat         VARCHAR(32),
  osm_lon         VARCHAR(32),
  osm_full_id     VARCHAR(64),
  station_cd      VARCHAR(32),
  station_name    VARCHAR(120),
  line_code       VARCHAR(16),
  fr_code         VARCHAR(16),
  match_level     VARCHAR(16),
  confidence      VARCHAR(16),
  reason          VARCHAR(64)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LOAD: replace <ABSPATH> with repo root (e.g. C:/xampp/htdocs/gilime_mvp_01 or /path/to/gilime_mvp_01)
-- LOAD DATA LOCAL INFILE '<ABSPATH>/data/derived/seoul/subway/subway_station_match_v1.csv'
-- INTO TABLE _staging_subway_station_match_v1
-- FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n'
-- IGNORE 1 LINES
-- (osm_name, osm_lat, osm_lon, osm_full_id, station_cd, station_name, line_code, fr_code, match_level, confidence, reason);
-- (id is auto-generated; do not include in LOAD column list)

-- Upsert into subway_stations_master. Fallback for empty station_cd: CONCAT(COALESCE(line_code,''), '_', station_name)
INSERT INTO subway_stations_master (station_cd, station_name, line_code, fr_code, lat, lon, osm_full_id, match_confidence, meta_json)
SELECT new.station_cd, new.station_name, new.line_code, new.fr_code, new.lat, new.lon, new.osm_full_id, new.match_confidence, new.meta_json
FROM (
  SELECT
    COALESCE(NULLIF(TRIM(s.station_cd), ''), CONCAT(COALESCE(NULLIF(TRIM(s.line_code), ''), 'X'), '_', COALESCE(NULLIF(TRIM(s.station_name), ''), 'unknown'))) AS station_cd,
    COALESCE(NULLIF(TRIM(s.station_name), ''), '') AS station_name,
    COALESCE(NULLIF(TRIM(s.line_code), ''), '') AS line_code,
    NULLIF(TRIM(s.fr_code), '') AS fr_code,
    NULLIF(TRIM(s.osm_lat), '') + 0.0 AS lat,
    NULLIF(TRIM(s.osm_lon), '') + 0.0 AS lon,
    NULLIF(TRIM(s.osm_full_id), '') AS osm_full_id,
    NULLIF(TRIM(s.confidence), '') + 0.0 AS match_confidence,
    JSON_OBJECT('match_level', COALESCE(NULLIF(TRIM(s.match_level), ''), 'NONE'), 'reason', COALESCE(NULLIF(TRIM(s.reason), ''), ''), 'osm_name', COALESCE(NULLIF(TRIM(s.osm_name), ''), '')) AS meta_json
  FROM _staging_subway_station_match_v1 s
) AS new
ON DUPLICATE KEY UPDATE
  station_name = new.station_name,
  line_code = new.line_code,
  fr_code = new.fr_code,
  lat = new.lat,
  lon = new.lon,
  osm_full_id = new.osm_full_id,
  match_confidence = new.match_confidence,
  meta_json = new.meta_json,
  updated_at = NOW();

-- Optional: drop staging
-- DROP TABLE IF EXISTS _staging_subway_station_match_v1;

-- -----------------------------------------------------------------------------
-- 2) Staging table: edges G1 CSV
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS _staging_subway_edges_g1_v1;
CREATE TABLE _staging_subway_edges_g1_v1 (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  line_code         VARCHAR(16),
  from_station_cd   VARCHAR(32),
  to_station_cd     VARCHAR(32),
  distance_m        VARCHAR(32),
  time_sec          VARCHAR(32),
  meta_json         TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LOAD DATA LOCAL INFILE '<ABSPATH>/data/derived/seoul/subway/subway_edges_g1_v1.csv'
-- INTO TABLE _staging_subway_edges_g1_v1
-- FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n'
-- IGNORE 1 LINES
-- (line_code, from_station_cd, to_station_cd, distance_m, time_sec, meta_json);

-- Insert-only into subway_edges_g1 (no UNIQUE; re-run will append duplicates — use validation to detect)
INSERT INTO subway_edges_g1 (line_code, from_station_cd, to_station_cd, distance_m, time_sec, meta_json)
SELECT
  COALESCE(NULLIF(TRIM(e.line_code), ''), '') AS line_code,
  COALESCE(NULLIF(TRIM(e.from_station_cd), ''), '') AS from_station_cd,
  COALESCE(NULLIF(TRIM(e.to_station_cd), ''), '') AS to_station_cd,
  NULLIF(TRIM(e.distance_m), '') + 0.0 AS distance_m,
  NULLIF(TRIM(e.time_sec), '') + 0 AS time_sec,
  NULL AS meta_json
FROM _staging_subway_edges_g1_v1 e
WHERE NULLIF(TRIM(e.line_code), '') IS NOT NULL
  AND NULLIF(TRIM(e.from_station_cd), '') IS NOT NULL
  AND NULLIF(TRIM(e.to_station_cd), '') IS NOT NULL;

-- DROP TABLE IF EXISTS _staging_subway_edges_g1_v1;
