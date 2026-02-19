-- v0.8-07: VIEW — station line candidates derived from subway_edges_g1. Read-only. SoT: docs/SOT/07_GRAPH_QUERY_RULES_G1.md, STEP6.
-- Why: station.line_code can be blank (ambiguous/unresolved); routing must use edge-derived line set.
-- Apply: run once (Workbench/CLI). No runtime impact.

SET NAMES utf8mb4;

CREATE OR REPLACE VIEW vw_subway_station_lines_g1 AS
SELECT
  station_name,
  CONCAT('[', GROUP_CONCAT(DISTINCT CONCAT('"', line_code, '"') ORDER BY line_code SEPARATOR ','), ']') AS line_codes_json,
  GROUP_CONCAT(DISTINCT line_code ORDER BY line_code) AS line_codes_csv,
  COUNT(*) AS degree_edges
FROM (
  SELECT line_code, from_station_cd AS station_name FROM subway_edges_g1
  UNION ALL
  SELECT line_code, to_station_cd AS station_name FROM subway_edges_g1
) t
GROUP BY station_name;
