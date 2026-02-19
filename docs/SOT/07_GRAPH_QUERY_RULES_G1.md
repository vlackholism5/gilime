# SoT 07 — Graph query rules (G1)

## Purpose

Single source of truth for querying subway G1 data when `subway_stations_master.line_code` may be blank. Station "lines" are a **set** derived from edges; do not assume a single `line_code` in the master table.

## Definitions

| Term | Meaning |
|------|--------|
| **station_name** | 역명 (e.g. 서울역, 시청). In G1, `subway_edges_g1.from_station_cd` / `to_station_cd` store station names. |
| **line_code** | 노선 식별자 (e.g. 1, 2, …, 8). Single value in `subway_stations_master.line_code` when filled. |
| **candidates** | Set of line_code values a station belongs to (e.g. 환승역 → {1, 4}). From edges or `meta_json.line_code_candidates`. |
| **edges_unique** | Station appears on exactly one line in edges → we fill `station.line_code`. |
| **ambiguous** | Station appears on multiple lines in edges → `line_code` left blank; candidates in `meta_json.line_code_candidates`. |
| **unresolved** | Station does not appear in edges (or cd_prefix not in edge set) → `line_code` blank; no candidates. |

## Rules

### R1: Station lines are a set

- Do **not** assume `subway_stations_master.line_code` is always populated or unique per station.
- For "which lines serve this station?", use **edge-derived** candidates (view or meta_json), not the single `line_code` column alone.

### R2: For routing over subway_edges_g1

- Prefer **edge-derived line candidates** (e.g. `vw_subway_station_lines_g1`) when deciding which edges are relevant to a station.
- Filter edges by `line_code IN (candidates)` when a station has multiple lines.

### R3: For display

- When `line_code` is blank, show **candidates** (from view or `meta_json.line_code_candidates`).
- Blank `line_code` in G1 is **allowed** and tracked (Option A policy); do not treat as error.

## How to query

### Get line candidates per station (edge-derived)

Use the view `vw_subway_station_lines_g1` (created by `sql/views/v0.8-07_view_station_lines_g1.sql`):

```sql
-- All stations with at least one edge and their line set
SELECT station_name, line_codes_csv, line_codes_json, degree_edges
FROM vw_subway_station_lines_g1;
```

### Join master with view for full picture

```sql
SELECT s.station_cd, s.station_name, s.line_code AS master_line_code,
       v.line_codes_csv AS edge_derived_lines, v.degree_edges
FROM subway_stations_master s
LEFT JOIN vw_subway_station_lines_g1 v ON s.station_name = v.station_name;
```

### Use candidates when master.line_code is blank

- If `master.line_code` is blank and `meta_json->>'$.line_code_source'` = `'ambiguous'`, use `meta_json->>'$.line_code_candidates'` for display or filtering.
- If you have the view, use `v.line_codes_csv` or `v.line_codes_json` for edge-derived set.

## Assumptions / Open questions (확인 필요)

- **View vs meta_json:** View is built only from `subway_edges_g1`; it does not include stations that have no edges. Master may have more stations (unresolved). For those, only `meta_json` (and possibly future mapping) applies.
- **Determinism:** `line_codes_csv` / `line_codes_json` order is deterministic (ORDER BY line_code) for reproducible output.
- **STEP7 candidate:** Expose view or equivalent in a read-only API for routing/UI without touching public/* until product decision.
