# sql/views

Read-only views. Apply in order when required by migrations.

## v0.8-07 — vw_subway_station_lines_g1

- **File:** `v0.8-07_view_station_lines_g1.sql`
- **Purpose:** STEP6; edge-derived station line candidates (SoT: docs/SOT/07_GRAPH_QUERY_RULES_G1.md, docs/SOT/08_RUNTIME_QUERY_CONTRACTS_G1.md).

**Rollback:** `DROP VIEW IF EXISTS vw_subway_station_lines_g1;`
