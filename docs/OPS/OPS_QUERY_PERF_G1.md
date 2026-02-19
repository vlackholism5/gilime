# OPS — Query performance (G1)

## Purpose

Guidance for G1 read path performance. No DB object changes required by this doc; apply only when needed.

**SoT:** `docs/SOT/08_RUNTIME_QUERY_CONTRACTS_G1.md`, `sql/validate/v0.8-09_station_line_candidates_queries.sql`.

---

## When to materialize the view

- **Keep VIEW** when: read volume is low, edges change rarely, or schema is still evolving.
- **Materialize** (e.g. `CREATE TABLE tbl_station_lines_g1 AS SELECT * FROM vw_subway_station_lines_g1` + refresh job) when:
  - Q1/Q2 are hit often and view execution cost is measurable.
  - You need a stable snapshot for a release and want to avoid view recomputation on every query.
- **Refresh:** After re-running STEP5 import (edges or stations updated), recreate or refresh the materialized table if used.

---

## Suggested index (if needed later)

- `subway_edges_g1` is already indexed by `(line_code, from_station_cd)` and `(line_code, to_station_cd)`.
- If Q1 by `station_name` on the **view** is slow, the view scans the derived table; adding an index on a view is not possible in MySQL. Option: materialize into a table and add `INDEX (station_name)`.

---

## Cache TTL (가설)

- **가설:** Line candidates change only when G1 data is re-imported (STEP5). A cache TTL of **1 hour** or **until next import** is a reasonable default for Q1/Q2 responses.
- **Invalidation:** On running `import_subway_g1_from_csv.php` (or equivalent), invalidate cache for station-line responses or flush the cache key prefix used for G1.
- **확인 필요:** Actual traffic and import frequency will determine whether caching is needed and TTL value.
