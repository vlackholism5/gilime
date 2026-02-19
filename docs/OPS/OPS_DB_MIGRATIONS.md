# OPS — DB Migrations

## Purpose

How to apply schema migrations and run validation queries locally. Migrations must not be executed inside Cursor; run them in your own environment (Workbench, CLI, or approved runner).

## Applying a migration locally

### Option 1: MySQL CLI (project root)

```bash
mysql -u <user> -p <database_name> < sql/migrations/v0.8-01_graph_schema.sql
```

Example (replace placeholders):

```bash
mysql -u root -p gilime_db < sql/migrations/v0.8-01_graph_schema.sql
```

- Run from the repository root so that the path `sql/migrations/v0.8-01_graph_schema.sql` is correct.
- If your client uses a different default charset, the file already includes `SET NAMES utf8mb4;`.

### Option 2: MySQL Workbench (or similar GUI)

1. Open the migration file: `sql/migrations/v0.8-01_graph_schema.sql`.
2. Select the DDL portion only (from the top through the last `CREATE TABLE ... ;`). Do **not** select the validation query block (comments and `SHOW TABLES` / `DESCRIBE` / `SELECT COUNT(*)`).
3. Execute the selected statements against your target schema.
4. Confirm no errors. Then run validation queries separately (see below).

### Option 3: PowerShell (Windows, if mysql is in PATH)

```powershell
cd c:\xampp\htdocs\gilime_mvp_01
Get-Content sql\migrations\v0.8-01_graph_schema.sql | mysql -u root -p gilime_db
```

- You may need to strip or skip the validation block if your client runs the whole file; otherwise run validation in a second step.

## Running validation queries

- **Do not execute migrations or validation inside Cursor.** Use your local MySQL client.

1. Open the same migration file and scroll to the **Validation queries** section at the bottom.
2. Copy the queries you need (uncomment first). Standard set:
   - `SHOW TABLES LIKE 'graph_%';`
   - `SHOW TABLES LIKE 'subway_%';`
   - `DESCRIBE graph_versions;` (and same for `graph_nodes`, `graph_edges`, `subway_stations_master`, `subway_edges_g1`)
   - `SELECT COUNT(*) AS cnt FROM graph_versions;` (and same for the other four tables)
3. Run them in Workbench (or CLI). After a fresh migration, counts should be 0.
4. Keep a copy of the results (screenshot or text) for evidence if required.

## File reference

| Migration | File | Purpose |
|-----------|------|---------|
| v0.8-01 | sql/migrations/v0.8-01_graph_schema.sql | Graph + subway G1 tables (graph_versions, graph_nodes, graph_edges, subway_stations_master, subway_edges_g1) |
| v0.8-03 | sql/ingest/v0.8-03_import_subway_g1.sql | STEP5: Import subway G1 from derived CSVs (staging + upsert/insert) |
| v0.8-03 | sql/validate/v0.8-03_validate_subway_g1.sql | STEP5: Validation only (counts, duplicates, nulls, samples, sanity) |
| v0.8-04 | sql/validate/v0.8-04_validate_station_line_code.sql | STEP5.1: Validate station line_code populated (blank must be 0) |
| v0.8-05 | sql/validate/v0.8-05_validate_station_line_code_quality.sql | STEP5.2: Validate line_code provenance & quality |
| v0.8-06 | sql/validate/v0.8-06_station_line_code_issue_list.sql | STEP5.2: Issue list (ambiguous/unresolved + suggested action) |
| v0.8-07 | sql/views/v0.8-07_view_station_lines_g1.sql | STEP6: VIEW vw_subway_station_lines_g1 (edge-derived line candidates). Rollback: `DROP VIEW IF EXISTS vw_subway_station_lines_g1;` |
| v0.8-08 | sql/validate/v0.8-08_validate_station_lines_g1.sql | STEP6: Validate view coverage & actionable lists |
| v0.8-09 | sql/validate/v0.8-09_station_line_candidates_queries.sql | STEP7: Canonical query patterns (Q1/Q2/Q3) |
| v0.8-10 | sql/validate/v0.8-10_api_query_bindings_g1.sql | STEP8: API query bindings for E1/E2 |

---

## STEP5: Import subway G1 (local only)

### Where the CSVs are

- **Stations:** `data/derived/seoul/subway/subway_station_match_v1.csv`
- **Edges:** `data/derived/seoul/subway/subway_edges_g1_v1.csv`

### Commands (Workbench or CLI)

1. **Apply import (run from repo root; replace `<ABSPATH>` with your repo path, e.g. `C:/xampp/htdocs/gilime_mvp_01`):**
   - Open `sql/ingest/v0.8-03_import_subway_g1.sql`.
   - Execute the DDL (staging tables).
   - Uncomment the first `LOAD DATA LOCAL INFILE` and replace `<ABSPATH>`, then run it.
   - Execute the first `INSERT INTO subway_stations_master ... SELECT ...`.
   - Uncomment the second `LOAD DATA LOCAL INFILE` and replace `<ABSPATH>`, then run it.
   - Execute the `INSERT INTO subway_edges_g1 ... SELECT ...`.
2. **Run validation:**
   - Execute all queries in `sql/validate/v0.8-03_validate_subway_g1.sql`.

**CLI example (after replacing paths in the SQL file):**
```bash
mysql -u root -p gilime_db < sql/ingest/v0.8-03_import_subway_g1.sql
mysql -u root -p gilime_db < sql/validate/v0.8-03_validate_subway_g1.sql
```
(If LOAD DATA is in the file, run the import file once staging is populated; or run LOAD DATA and INSERTs separately.)

**LOAD DATA 권한 없을 때 (Error 3948 / 1227):** 서버에서 `local_infile`을 켤 수 없으면, 아래 PHP 스크립트로 CSV → DB 직접 적재 가능 (스테이징/LOAD DATA 불필요).

```bash
php scripts/php/import_subway_g1_from_csv.php
```

- `subway_station_match_v1.csv` → `subway_stations_master` (upsert)
- `subway_edges_g1_v1.csv` → `subway_edges_g1` (insert)
- 실행 후 `sql/validate/v0.8-03_validate_subway_g1.sql`, `sql/validate/v0.8-04_validate_station_line_code.sql`로 검증

### STEP5.1: line_code blank 발생 시

- **증상:** `subway_stations_master.line_code` 가 전부 빈 값(blank_line_code = total).
- **자동 처리:** importer가 엣지(subway_edges_g1)로부터 역별 line_code를 역산해 자동 backfill. 별도 조치 불필요.
- **조치:** header-map 패치 적용 후 importer 재실행.
  1. `php scripts/php/import_subway_g1_from_csv.php` 실행
  2. 출력에서 `blank_line_code_in_csv` 확인
  3. v0.8-04 검증 실행:
     - **권장 (mysql PATH 불필요):** `php scripts/php/run_validate_station_line_code.php`
     - **CLI (mysql PATH 필요):**
       - PowerShell: `Get-Content sql\validate\v0.8-04_validate_station_line_code.sql | mysql -u root -p gilime_db`
       - bash: `mysql -u root -p gilime_db < sql/validate/v0.8-04_validate_station_line_code.sql`
  4. `blank_line_code = 0` 이면 성공 (line_code 값 예: 1~9 등)

### STEP5.2: line_code hardening (data integrity)

- **Why we keep ambiguous/unresolved blank (Option A — locked):**
  - Ambiguous: 환승역은 단일 line_code 자동 선택하지 않음. `meta_json.line_code_candidates`로 후보만 기록.
  - Unresolved: 엣지/접두사로 추론 불가한 역에 line_code를 만들지 않음.
  - 향후 해결 경로: 공공 노선별 역 정보 확장 또는 다노선 역 모델 도입.
- **QA JSON:** `data/derived/seoul/subway/_qa_station_line_code_backfill_v1.json`
  - `total_stations`, `blank_before`, `filled_by_edges`, `filled_by_cd_prefix`
  - `ambiguous_station_names_topN`, `unresolved_station_names_topN`
- **Quality validation:**
  - `sql/validate/v0.8-05_validate_station_line_code_quality.sql`: total, blank, line_code_source별 count, unresolved/ambiguous 샘플(50).
  - `sql/validate/v0.8-06_station_line_code_issue_list.sql`: 이슈 리스트(후보·권장 조치, 최대 100행). **mysql PATH 없을 때:** `php scripts/php/run_station_line_code_issue_list.php`
- **PASS 기준 (G1 현 단계):** blank_line_code > 0 허용. 단, line_code_source별로 추적되며, ambiguous는 line_code_candidates, unresolved는 이슈 리스트로 관리. v0.8-05/v0.8-06 실행으로 상태 확인.

### STEP6: G1 station lines view (read-only, no runtime impact)

- **Why:** Station `line_code` can be blank (ambiguous/unresolved). Routing and display need edge-derived line **set**; SoT: `docs/SOT/07_GRAPH_QUERY_RULES_G1.md`.
- **What:** Create VIEW `vw_subway_station_lines_g1` (station_name, line_codes_json, line_codes_csv, degree_edges) from `subway_edges_g1`. Read-only; no change to public/*.
- **Apply (once):** Run `sql/views/v0.8-07_view_station_lines_g1.sql` (Workbench or CLI). If mysql not in PATH, run the file contents in Workbench.
- **Validate:** Run `sql/validate/v0.8-08_validate_station_lines_g1.sql` — counts, coverage, top 30 blank-but-actionable, top 30 possible mismatches.
- **Verify:** View exists; coverage > 0; actionable list returns rows (e.g. ambiguous stations).

### STEP7: Query contract (read-only, no runtime impact)

- **Why:** Define stable read-contract for station → line candidates and blank handling before any public/* integration.
- **What:** Canonical SQL patterns (Q1 by station_name, Q2 by station_cd, Q3 actionable blanks) in `sql/validate/v0.8-09_station_line_candidates_queries.sql`. Contract shapes and precedence: `docs/SOT/08_RUNTIME_QUERY_CONTRACTS_G1.md`.
- **Verify:** Run v0.8-09 (read-only). Example tests: Q1 for `서울역` (ambiguous), Q1 for `청량리` (edges_unique). Optional perf/cache guidance: `docs/OPS/OPS_QUERY_PERF_G1.md`.

### STEP8: API contract + observability (no runtime implementation)

- **Why:** Lock API spec and DB bindings before implementing routes in public/*.
- **What:** Endpoints E1 (by-name), E2 (by-code) and response schema: `docs/SOT/09_API_CONTRACTS_G1.md`. Exact SQL for E1/E2: `sql/validate/v0.8-10_api_query_bindings_g1.sql`. Log/trace spec: `docs/OPS/OPS_OBSERVABILITY_G1_API.md`.
- **Verify:** Run v0.8-10 with hardcoded examples (서울역, 청량리, 0150, 0158); each returns 1 row with line_codes, line_codes_source, degree_edges, meta.

### STEP9: API E1/E2 implementation + smoke

- **Why:** First working API for G1 station-lines (E1 by-name, E2 by-code). No new migration SQL; implementation only in `public/api/index.php` and `app/inc/api/g1_station_lines.php`.
- **Endpoints (GET):**
  - E1: `.../api/index.php?path=g1/station-lines/by-name&station_name=<역명>` (e.g. 서울역, 청량리).
  - E2: `.../api/index.php?path=g1/station-lines/by-code&station_cd=<역코드>` (e.g. 0150, 0158).
- **Smoke (run from repo root or adjust base URL):** Replace `http://localhost/gilime_mvp_01` with your base URL if needed.

```bash
# E1 by-name
curl -s "http://localhost/gilime_mvp_01/api/index.php?path=g1/station-lines/by-name&station_name=서울역"
curl -s "http://localhost/gilime_mvp_01/api/index.php?path=g1/station-lines/by-name&station_name=청량리"

# E2 by-code
curl -s "http://localhost/gilime_mvp_01/api/index.php?path=g1/station-lines/by-code&station_cd=0150"
curl -s "http://localhost/gilime_mvp_01/api/index.php?path=g1/station-lines/by-code&station_cd=0158"

# Optional: not_found (404)
curl -s "http://localhost/gilime_mvp_01/api/index.php?path=g1/station-lines/by-name&station_name=NonExistentStation"
curl -s "http://localhost/gilime_mvp_01/api/index.php?path=g1/station-lines/by-code&station_cd=99999"
```

- **Expected:** 200 with `ok: true`, `data.station_name`, `data.line_codes` (array), `data.line_codes_source`, `data.meta`; not_found URLs return 404 with `error.code: "not_found"`. Bad request (e.g. missing param or invalid station_cd) returns 400.
- **정합성 체크 2개:** by-name vs by-code 동일 data, ambiguous 역 line_codes↔meta.line_code_candidates 일치 — [docs/OPS/OPS_G1_SMOKE_REGression.md](OPS_G1_SMOKE_REGression.md) 참조.
- **운영 롤백/비활성화:** 뷰 롤백 및 G1 API 비활성화(임시) 절차 — [docs/OPS/OPS_G1_RUNBOOK.md](OPS_G1_RUNBOOK.md) 참조.

### Expected validation outputs (thresholds)

- **subway_stations_master:** `COUNT(*)` >= 300.
- **subway_edges_g1:** `COUNT(*)` = 270 (or slightly less if dedup policy applied on import).
- **Duplicates:** 0 rows from the duplicate-check queries (or document any and why).
- **Null critical fields:** 0 for station_cd, station_name, line_code, from_station_cd, to_station_cd (or document exceptions).
- **distance_m / time_sec > 0:** majority positive (sanity).

## Checklist

- [ ] Migration run outside Cursor (Workbench or CLI).
- [ ] Validation queries run after migration; results recorded.
- [ ] No runtime code (public/*), routes, or UI changed for this migration.
