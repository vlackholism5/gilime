-- v0.8-08: Validation — station lines view coverage & actionable lists. Read-only. Run after v0.8-07. SoT: docs/SOT/07_GRAPH_QUERY_RULES_G1.md.
-- Why: Ensure view exists, coverage > 0, and we can list blank-but-actionable and possible mismatches.

-- -----------------------------------------------------------------------------
-- 1) Counts: master vs view
-- -----------------------------------------------------------------------------
SELECT COUNT(*) AS master_station_count FROM subway_stations_master;

SELECT COUNT(DISTINCT station_name) AS view_distinct_station_names FROM vw_subway_station_lines_g1;

-- -----------------------------------------------------------------------------
-- 2) Coverage: master stations that have at least one candidate in the view
-- -----------------------------------------------------------------------------
SELECT COUNT(DISTINCT s.id) AS master_stations_with_view_candidates
FROM subway_stations_master s
INNER JOIN vw_subway_station_lines_g1 v ON s.station_name = v.station_name;

-- -----------------------------------------------------------------------------
-- 3) Top 30: master.line_code blank but view has candidates (actionable)
-- -----------------------------------------------------------------------------
SELECT s.station_cd, s.station_name, s.line_code AS master_line_code, v.line_codes_csv AS view_line_codes
FROM subway_stations_master s
INNER JOIN vw_subway_station_lines_g1 v ON s.station_name = v.station_name
WHERE s.line_code IS NULL OR TRIM(s.line_code) = ''
ORDER BY s.station_cd
LIMIT 30;

-- -----------------------------------------------------------------------------
-- 4) Top 30: master has line_code but view candidates disagree (possible mismatch)
-- -----------------------------------------------------------------------------
SELECT s.station_cd, s.station_name, s.line_code AS master_line_code, v.line_codes_csv AS view_line_codes
FROM subway_stations_master s
INNER JOIN vw_subway_station_lines_g1 v ON s.station_name = v.station_name
WHERE (s.line_code IS NOT NULL AND TRIM(s.line_code) != '')
  AND FIND_IN_SET(TRIM(s.line_code), v.line_codes_csv) = 0
ORDER BY s.station_cd
LIMIT 30;
