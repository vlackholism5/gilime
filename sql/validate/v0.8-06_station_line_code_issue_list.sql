-- v0.8-06: Station line_code issue list (ambiguous/unresolved). Run after STEP5 import. Do not execute inside Cursor.
-- SoT: docs/OPS/OPS_DB_MIGRATIONS.md, STEP5.2. Policy: Option A — blank allowed, tracked.

-- List ambiguous & unresolved stations with candidates and suggested next action (limit 100)
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
LIMIT 100;
