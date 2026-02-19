# CTO Report — Directory Structure Cleanup #1

## 1. Moved files list

### Scripts (old → new)

| Old | New |
|-----|-----|
| scripts/run_station_line_code_issue_list.php | scripts/php/run_station_line_code_issue_list.php |
| scripts/import_subway_g1_from_csv.php | scripts/php/import_subway_g1_from_csv.php |
| scripts/run_validate_station_line_code.php | scripts/php/run_validate_station_line_code.php |
| scripts/test_suggest_stops.php | scripts/php/test_suggest_stops.php |
| scripts/import_seoul_bus_route_stop_master_full.php | scripts/php/import_seoul_bus_route_stop_master_full.php |
| scripts/import_seoul_bus_route_master_full.php | scripts/php/import_seoul_bus_route_master_full.php |
| scripts/run_parse_match_batch.php | scripts/php/run_parse_match_batch.php |
| scripts/diagnose_route_finder.php | scripts/php/diagnose_route_finder.php |
| scripts/kill_mysql_process.php | scripts/php/kill_mysql_process.php |
| scripts/db_smoke_check.php | scripts/php/db_smoke_check.php |
| scripts/check_route_public_data_counts.php | scripts/php/check_route_public_data_counts.php |
| scripts/check_mysql_processlist.php | scripts/php/check_mysql_processlist.php |
| scripts/list_route_finder_sample_stops.php | scripts/php/list_route_finder_sample_stops.php |
| scripts/test_temp_shuttle_parser.php | scripts/php/test_temp_shuttle_parser.php |
| scripts/run_alert_ingest_real_metrics.php | scripts/php/run_alert_ingest_real_metrics.php |
| scripts/run_delivery_outbound_stub.php | scripts/php/run_delivery_outbound_stub.php |
| scripts/run_alert_ingest_stub.php | scripts/php/run_alert_ingest_stub.php |
| scripts/run_alert_generate_from_metrics.php | scripts/php/run_alert_generate_from_metrics.php |
| scripts/import_seoul_bus_stop_master_full.php | scripts/php/import_seoul_bus_stop_master_full.php |
| scripts/migrate_v06_8_to_18.php | scripts/php/migrate_v06_8_to_18.php |
| scripts/import_seoul_stop_master.php | scripts/php/import_seoul_stop_master.php |
| scripts/phpsetup.ps1 | scripts/ps1/phpsetup.ps1 |
| scripts/run_route_scripts.ps1 | scripts/ps1/run_route_scripts.ps1 |
| scripts/run_verify.ps1 | scripts/ps1/run_verify.ps1 |
| scripts/ensure-data-dirs.js | scripts/node/ensure-data-dirs.js |

### SQL (old → new)

| Old | New |
|-----|-----|
| sql/v0.8-01_graph_schema.sql | sql/migrations/v0.8-01_graph_schema.sql |
| sql/v0.8-02_import_subway_master_helpers.sql | sql/migrations/v0.8-02_import_subway_master_helpers.sql |
| sql/v0.8-03_import_subway_g1.sql | sql/ingest/v0.8-03_import_subway_g1.sql |
| sql/v0.8-03_validate_subway_g1.sql | sql/validate/v0.8-03_validate_subway_g1.sql |
| sql/v0.8-04_validate_station_line_code.sql | sql/validate/v0.8-04_validate_station_line_code.sql |
| sql/v0.8-05_validate_station_line_code_quality.sql | sql/validate/v0.8-05_validate_station_line_code_quality.sql |
| sql/v0.8-06_station_line_code_issue_list.sql | sql/validate/v0.8-06_station_line_code_issue_list.sql |
| sql/v0.8-07_view_station_lines_g1.sql | sql/views/v0.8-07_view_station_lines_g1.sql |
| sql/v0.8-08_validate_station_lines_g1.sql | sql/validate/v0.8-08_validate_station_lines_g1.sql |
| sql/v0.8-09_station_line_candidates_queries.sql | sql/validate/v0.8-09_station_line_candidates_queries.sql |
| sql/v0.8-10_api_query_bindings_g1.sql | sql/validate/v0.8-10_api_query_bindings_g1.sql |

---

## 2. Changed references list

- **docs/OPS/OPS_DB_MIGRATIONS.md:** All `sql/v0.8-*` → `sql/migrations/`, `sql/ingest/`, `sql/views/`, `sql/validate/` as per table; `php scripts/` → `php scripts/php/` for import_subway_g1_from_csv, run_validate_station_line_code, run_station_line_code_issue_list; `sql\v0.8-*` → `sql\migrations\*`, `sql\validate\*`.
- **docs/SOT/07_GRAPH_QUERY_RULES_G1.md:** `sql/v0.8-07_...` → `sql/views/v0.8-07_view_station_lines_g1.sql`.
- **docs/SOT/08_RUNTIME_QUERY_CONTRACTS_G1.md:** `sql/v0.8-09_...` → `sql/validate/v0.8-09_station_line_candidates_queries.sql`.
- **docs/SOT/09_API_CONTRACTS_G1.md:** `sql/v0.8-10_...` → `sql/validate/v0.8-10_api_query_bindings_g1.sql`.
- **docs/OPS/OPS_QUERY_PERF_G1.md:** `sql/v0.8-09_...` → `sql/validate/v0.8-09_station_line_candidates_queries.sql`.
- **scripts/php/import_subway_g1_from_csv.php:** Echo: `php scripts/run_validate_station_line_code.php` → `php scripts/php/run_validate_station_line_code.php`, `sql/v0.8-05_...` → `sql/validate/v0.8-05_...`.
- **README.md:** `scripts/import_seoul_bus_*` → `scripts/php/import_seoul_bus_*`, `php scripts/import_seoul_bus_stop_master_full.php` → `php scripts/php/...`.
- **data/README_DATA_DIRS.md:** `node scripts/ensure-data-dirs.js` → `node scripts/node/ensure-data-dirs.js`, `scripts/ensure-data-dirs.js` → `scripts/node/ensure-data-dirs.js`, `php scripts/import_seoul_bus_*` → `php scripts/php/import_seoul_bus_*`.
- **docs/ux/ROUTE_FINDER_DIAGNOSIS_v1_8.md:** `scripts/run_route_scripts.ps1` → `scripts/ps1/run_route_scripts.ps1`; all `php scripts/*.php` → `php scripts/php/*.php`; `scripts/check_route_public_data_counts.php` → `scripts/php/...`.
- **docs/operations/VERIFY_RUNNER.md:** `scripts/run_verify.ps1` → `scripts/ps1/run_verify.ps1`.
- **sql/verify/README.md:** `scripts/run_verify.ps1` → `scripts/ps1/run_verify.ps1`.
- **sql/releases/v1.7/verify/README.md:** `scripts/run_verify.ps1` → `scripts/ps1/run_verify.ps1`.
- **public/admin/upload_pdf.php:** `scripts/run_parse_match_batch.php` → `scripts/php/run_parse_match_batch.php`.
- **public/admin/doc.php:** `php scripts/run_parse_match_batch.php` → `php scripts/php/run_parse_match_batch.php`.
- **public/admin/ops_summary.php:** `php scripts/import_seoul_bus_*` → `php scripts/php/import_seoul_bus_*`.
- **public/admin/ops_control.php:** `php scripts/run_delivery_outbound_stub.php`, `php scripts/run_alert_ingest_real_metrics.php` → `scripts/php/...`.
- **scripts/php/*.php (header/comments):** Example paths `scripts/run_parse_match_batch.php` etc. → `scripts/php/...`.
- **docs/STATUS_FOR_GPT.md, README_PDF_PARSING.md, docs/operations/WORKER_QUEUE_DESIGN_v1_7.md, docs/releases/v1.7/specs/spec_07_outbound_stub.md, spec_14_parse_ops_hardening.md, spec_11_real_metrics_ingest.md, smoke_14_parse_ops_hardening.md, SMOKE_RUNBOOK_2026_02_20.md, PM_SYNC_2026_02_10.md, spec_09_ops_summary.md, gate_20_mvp_2026_02_20.md, PDF_PIPELINE_FUTURE_DESIGN.md, gate_14_parse_ops_hardening.md, gate_16_parse_status_policy.md, smoke_16_parse_status_policy.md, spec_16_parse_status_policy.md, smoke_07_outbound_stub.md, smoke_12_ops_control.md, smoke_11_real_metrics_ingest.md, smoke_10_retry_backoff.md, docs/v1.4-10_smoke.md, ALERT_GENERATE_v1_4.md, v1.4-05_smoke.md, v0.6-19_작업요약_GPT전달용.md:** All references to moved `scripts/*.php` updated to `scripts/php/*.php` (and `scripts/run_verify.ps1` → `scripts/ps1/run_verify.ps1` where applicable).

---

## 3. Verification

- **Filesystem:** Confirmed: no files under `app/`, `public/`, or `data/` were moved or renamed. `scripts/php/`, `scripts/ps1/`, `scripts/node/` contain the moved script files; `scripts/python/` unchanged. `sql/migrations/`, `sql/views/`, `sql/ingest/`, `sql/validate/` contain the v0.8-* files as specified; `sql/archive/`, `sql/releases/`, `sql/verify/`, `sql/operations/` unchanged.
- **Python:** `python scripts/python/subway_match_v1.py` — ran successfully (wrote CSV under repo data).
- **Node:** `node scripts/node/ensure-data-dirs.js` — ran successfully; ROOT correct (created data dirs under repo root).
- **PHP:** `php scripts/php/run_validate_station_line_code.php` — ran successfully; found app/inc and DB, produced validation output.
- **MySQL (manual):** From repo root: `mysql -u root -p gilime_db < sql/views/v0.8-07_view_station_lines_g1.sql` and `mysql ... < sql/validate/v0.8-08_validate_station_lines_g1.sql` — to be run in your environment; paths are correct.
- **PowerShell (optional):** `scripts/ps1/run_verify.ps1` — ProjectRoot fixed to repo root; run manually to confirm it finds `sql/verify/*.sql` and writes `logs/verify_latest.md`.

---

## 4. New file

- **docs/OPS/OPS_REPO_STRUCTURE_RULES.md:** Folder roles (scripts/php, python, ps1, node; sql/migrations, views, ingest, validate, archive), naming rules, and where new MVP+ files go.

No feature work; no DB schema changes; only moves, path fixes, reference updates, and the one new doc.
