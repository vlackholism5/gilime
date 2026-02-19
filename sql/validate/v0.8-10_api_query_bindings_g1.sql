-- v0.8-10: API query bindings for E1/E2. Read-only. SoT: docs/SOT/09_API_CONTRACTS_G1.md, STEP8.
-- Runtime must bind :station_name (E1) or :station_cd (E2). Output matches API response schema.

-- -----------------------------------------------------------------------------
-- E1) GET /api/g1/station-lines/by-name?station_name=...
-- Bind: :station_name
-- -----------------------------------------------------------------------------
-- SELECT
--   s.station_name,
--   s.station_cd,
--   NULLIF(TRIM(s.line_code), '') AS master_line_code,
--   COALESCE(
--     NULLIF(TRIM(v.line_codes_json), ''),
--     CASE WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN JSON_ARRAY(TRIM(s.line_code)) ELSE JSON_ARRAY() END
--   ) AS line_codes,
--   CASE WHEN v.station_name IS NOT NULL THEN 'view' WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN 'master' ELSE 'none' END AS line_codes_source,
--   COALESCE(v.degree_edges, 0) AS degree_edges,
--   JSON_OBJECT(
--     'line_code_source', JSON_UNQUOTE(JSON_EXTRACT(s.meta_json, '$.line_code_source')),
--     'line_code_candidates', COALESCE(JSON_EXTRACT(s.meta_json, '$.line_code_candidates'), NULL)
--   ) AS meta
-- FROM subway_stations_master s
-- LEFT JOIN vw_subway_station_lines_g1 v ON v.station_name = s.station_name
-- WHERE s.station_name = :station_name
-- ORDER BY s.station_cd
-- LIMIT 1;

-- Example: station_name = '서울역' (ambiguous)
SELECT
  s.station_name,
  s.station_cd,
  NULLIF(TRIM(s.line_code), '') AS master_line_code,
  COALESCE(
    NULLIF(TRIM(v.line_codes_json), ''),
    CASE WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN JSON_ARRAY(TRIM(s.line_code)) ELSE JSON_ARRAY() END
  ) AS line_codes,
  CASE WHEN v.station_name IS NOT NULL THEN 'view' WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN 'master' ELSE 'none' END AS line_codes_source,
  COALESCE(v.degree_edges, 0) AS degree_edges,
  JSON_OBJECT(
    'line_code_source', JSON_UNQUOTE(JSON_EXTRACT(s.meta_json, '$.line_code_source')),
    'line_code_candidates', COALESCE(JSON_EXTRACT(s.meta_json, '$.line_code_candidates'), NULL)
  ) AS meta
FROM subway_stations_master s
LEFT JOIN vw_subway_station_lines_g1 v ON v.station_name = s.station_name
WHERE s.station_name = '서울역'
ORDER BY s.station_cd
LIMIT 1;

-- Example: station_name = '청량리' (edges_unique)
SELECT
  s.station_name,
  s.station_cd,
  NULLIF(TRIM(s.line_code), '') AS master_line_code,
  COALESCE(
    NULLIF(TRIM(v.line_codes_json), ''),
    CASE WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN JSON_ARRAY(TRIM(s.line_code)) ELSE JSON_ARRAY() END
  ) AS line_codes,
  CASE WHEN v.station_name IS NOT NULL THEN 'view' WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN 'master' ELSE 'none' END AS line_codes_source,
  COALESCE(v.degree_edges, 0) AS degree_edges,
  JSON_OBJECT(
    'line_code_source', JSON_UNQUOTE(JSON_EXTRACT(s.meta_json, '$.line_code_source')),
    'line_code_candidates', COALESCE(JSON_EXTRACT(s.meta_json, '$.line_code_candidates'), NULL)
  ) AS meta
FROM subway_stations_master s
LEFT JOIN vw_subway_station_lines_g1 v ON v.station_name = s.station_name
WHERE s.station_name = '청량리'
ORDER BY s.station_cd
LIMIT 1;

-- -----------------------------------------------------------------------------
-- E2) GET /api/g1/station-lines/by-code?station_cd=...
-- Bind: :station_cd
-- -----------------------------------------------------------------------------
-- SELECT
--   s.station_name,
--   s.station_cd,
--   NULLIF(TRIM(s.line_code), '') AS master_line_code,
--   COALESCE(
--     NULLIF(TRIM(v.line_codes_json), ''),
--     CASE WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN JSON_ARRAY(TRIM(s.line_code)) ELSE JSON_ARRAY() END
--   ) AS line_codes,
--   CASE WHEN v.station_name IS NOT NULL THEN 'view' WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN 'master' ELSE 'none' END AS line_codes_source,
--   COALESCE(v.degree_edges, 0) AS degree_edges,
--   JSON_OBJECT(
--     'line_code_source', JSON_UNQUOTE(JSON_EXTRACT(s.meta_json, '$.line_code_source')),
--     'line_code_candidates', COALESCE(JSON_EXTRACT(s.meta_json, '$.line_code_candidates'), NULL)
--   ) AS meta
-- FROM subway_stations_master s
-- LEFT JOIN vw_subway_station_lines_g1 v ON v.station_name = s.station_name
-- WHERE s.station_cd = :station_cd
-- LIMIT 1;

-- Example: station_cd = '0150' (서울역, ambiguous)
SELECT
  s.station_name,
  s.station_cd,
  NULLIF(TRIM(s.line_code), '') AS master_line_code,
  COALESCE(
    NULLIF(TRIM(v.line_codes_json), ''),
    CASE WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN JSON_ARRAY(TRIM(s.line_code)) ELSE JSON_ARRAY() END
  ) AS line_codes,
  CASE WHEN v.station_name IS NOT NULL THEN 'view' WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN 'master' ELSE 'none' END AS line_codes_source,
  COALESCE(v.degree_edges, 0) AS degree_edges,
  JSON_OBJECT(
    'line_code_source', JSON_UNQUOTE(JSON_EXTRACT(s.meta_json, '$.line_code_source')),
    'line_code_candidates', COALESCE(JSON_EXTRACT(s.meta_json, '$.line_code_candidates'), NULL)
  ) AS meta
FROM subway_stations_master s
LEFT JOIN vw_subway_station_lines_g1 v ON v.station_name = s.station_name
WHERE s.station_cd = '0150'
LIMIT 1;

-- Example: station_cd = '0158' (청량리, edges_unique)
SELECT
  s.station_name,
  s.station_cd,
  NULLIF(TRIM(s.line_code), '') AS master_line_code,
  COALESCE(
    NULLIF(TRIM(v.line_codes_json), ''),
    CASE WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN JSON_ARRAY(TRIM(s.line_code)) ELSE JSON_ARRAY() END
  ) AS line_codes,
  CASE WHEN v.station_name IS NOT NULL THEN 'view' WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN 'master' ELSE 'none' END AS line_codes_source,
  COALESCE(v.degree_edges, 0) AS degree_edges,
  JSON_OBJECT(
    'line_code_source', JSON_UNQUOTE(JSON_EXTRACT(s.meta_json, '$.line_code_source')),
    'line_code_candidates', COALESCE(JSON_EXTRACT(s.meta_json, '$.line_code_candidates'), NULL)
  ) AS meta
FROM subway_stations_master s
LEFT JOIN vw_subway_station_lines_g1 v ON v.station_name = s.station_name
WHERE s.station_cd = '0158'
LIMIT 1;
