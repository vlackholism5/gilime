-- v0.8-09: Canonical read-only query patterns for station -> line candidates. SoT: docs/SOT/08_RUNTIME_QUERY_CONTRACTS_G1.md, STEP7.
-- Use with prepared statements: bind :station_name / :station_cd. No side effects.

-- -----------------------------------------------------------------------------
-- Q1) By station_name (exact). Input: :station_name
-- Output: station_name, line_codes_csv, line_codes_json, degree_edges
-- -----------------------------------------------------------------------------
-- SELECT station_name, line_codes_csv, line_codes_json, degree_edges
-- FROM vw_subway_station_lines_g1
-- WHERE station_name = :station_name;

-- Example (test): 서울역 (ambiguous)
SELECT station_name, line_codes_csv, line_codes_json, degree_edges
FROM vw_subway_station_lines_g1
WHERE station_name = '서울역';

-- Example (test): 청량리 (edges_unique, master has line_code)
SELECT station_name, line_codes_csv, line_codes_json, degree_edges
FROM vw_subway_station_lines_g1
WHERE station_name = '청량리';

-- -----------------------------------------------------------------------------
-- Q2) By station_cd: master -> station_name -> view candidates
-- Input: :station_cd
-- Output: station_cd, station_name, master_line_code, view_line_codes_csv, view_line_codes_json
-- -----------------------------------------------------------------------------
-- SELECT s.station_cd, s.station_name, s.line_code AS master_line_code,
--        v.line_codes_csv AS view_line_codes_csv, v.line_codes_json AS view_line_codes_json
-- FROM subway_stations_master s
-- LEFT JOIN vw_subway_station_lines_g1 v ON s.station_name = v.station_name
-- WHERE s.station_cd = :station_cd;

-- Example: 0150 (서울역)
SELECT s.station_cd, s.station_name, s.line_code AS master_line_code,
       v.line_codes_csv AS view_line_codes_csv, v.line_codes_json AS view_line_codes_json
FROM subway_stations_master s
LEFT JOIN vw_subway_station_lines_g1 v ON s.station_name = v.station_name
WHERE s.station_cd = '0150';

-- Example: 0158 (청량리)
SELECT s.station_cd, s.station_name, s.line_code AS master_line_code,
       v.line_codes_csv AS view_line_codes_csv, v.line_codes_json AS view_line_codes_json
FROM subway_stations_master s
LEFT JOIN vw_subway_station_lines_g1 v ON s.station_name = v.station_name
WHERE s.station_cd = '0158';

-- -----------------------------------------------------------------------------
-- Q3) List actionable blanks: master.line_code blank AND view has candidates
-- Output: station_cd, station_name, master_line_code, view_line_codes_csv, view_line_codes_json,
--         line_code_source, line_code_candidates (from meta_json when present)
-- -----------------------------------------------------------------------------
-- SELECT s.station_cd, s.station_name, s.line_code AS master_line_code,
--        v.line_codes_csv AS view_line_codes_csv, v.line_codes_json AS view_line_codes_json,
--        JSON_UNQUOTE(JSON_EXTRACT(s.meta_json, '$.line_code_source')) AS line_code_source,
--        JSON_EXTRACT(s.meta_json, '$.line_code_candidates') AS line_code_candidates
-- FROM subway_stations_master s
-- INNER JOIN vw_subway_station_lines_g1 v ON s.station_name = v.station_name
-- WHERE (s.line_code IS NULL OR TRIM(s.line_code) = '')
-- ORDER BY s.station_cd;

SELECT s.station_cd, s.station_name, s.line_code AS master_line_code,
       v.line_codes_csv AS view_line_codes_csv, v.line_codes_json AS view_line_codes_json,
       JSON_UNQUOTE(JSON_EXTRACT(s.meta_json, '$.line_code_source')) AS line_code_source,
       JSON_EXTRACT(s.meta_json, '$.line_code_candidates') AS line_code_candidates
FROM subway_stations_master s
INNER JOIN vw_subway_station_lines_g1 v ON s.station_name = v.station_name
WHERE (s.line_code IS NULL OR TRIM(s.line_code) = '')
ORDER BY s.station_cd
LIMIT 100;
