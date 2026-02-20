# CTO Post-Implementation Review — Meeting Minutes (Gilime)

**Scope:** Directory Cleanup #1, G1 data pipeline STEP4~STEP8, G1 runtime query/view (v0.8-07~10), SOT/07~09, OPS observability spec, MVP Core closeout + MVP+ / Roadmap readiness.  
**Rules:** No code; minimal-change only; every suggestion = (Why)(Risk)(Minimal Fix)(Verification); FACT vs ASSUMPTION/확인 필요 구분.

**문서 상태:** 본 회의록은 E1/E2 구현 전 시점 기준이다. 현재 E1/E2는 구현 완료됨(OPS_DB_MIGRATIONS STEP9, public/api/index.php). 아래 본문의 "spec-only" 등 기술은 당시 스냅샷으로 유지하며, 상단 정정으로 정합성만 확보한다.

---

## 1) FACTS (confirmed)

- **Directory Cleanup #1:** Scripts under `scripts/php`, `scripts/ps1`, `scripts/node`, `scripts/python`; SQL under `sql/migrations`, `sql/ingest`, `sql/views`, `sql/validate`, `sql/releases`, `sql/archive`. Docs under `docs/releases/<ver>/`, `docs/references/`, `docs/archive/`. Root contains **no** `.ps1` files.
- **.gitignore:** `logs/verify_latest.md` on its own line; `# Composer dependencies` and `vendor/`/`composer.lock` separate; `public/uploads/*.pdf` and `*.tmp` present.
- **STEP4:** `subway_edges_ingest_v1.py` produces 270 edge rows; STEP4-Run LOCK: A 315 matched / 0 unmatched, B 270, C 8364 walk edges.
- **STEP5.2 LOCK:** line_code policy — edges_unique fill only (198); ambiguous 34 / unresolved 59 blank; QA JSON + validate SQL (v0.8-04~06) exist.
- **STEP6:** View `vw_subway_station_lines_g1` in `sql/views/v0.8-07_view_station_lines_g1.sql`; read-only, edge-derived.
- **STEP7:** Query contracts SoT `docs/SOT/08_RUNTIME_QUERY_CONTRACTS_G1.md`; canonical queries `sql/validate/v0.8-09_station_line_candidates_queries.sql`.
- **STEP8:** API contracts SoT `docs/SOT/09_API_CONTRACTS_G1.md`; bindings `sql/validate/v0.8-10_api_query_bindings_g1.sql`; observability spec `docs/OPS/OPS_OBSERVABILITY_G1_API.md`. v0.8-09/10 validated with 서울역/청량리/0150/0158.
- **E1/E2:** SOT/09 defines endpoints; **public/* implementation not done** (spec-only).
- **View rollback:** Documented in `sql/views/README.md` and `docs/OPS/OPS_DB_MIGRATIONS.md`: `DROP VIEW IF EXISTS vw_subway_station_lines_g1;`
- **app/inc:** Structured as config/auth/alert/parse/route/admin/lib; require paths updated. Secrets: `app/inc/config/config.local.php` and `.env` in .gitignore; uploads under `public/uploads/` with path guard.

---

## 2) OPEN QUESTIONS (확인 필요)

| Question | Why it matters | Owner | How to verify |
|----------|----------------|--------|----------------|
| Is trace_id already in request context for public/* entry points? | OPS_OBSERVABILITY_G1_API requires propagation when E1/E2 are implemented. | Backend | Grep public/api, index.php for get_trace_id / X-Trace-Id usage. |
| Actual p50/p95 for E1/E2 once implemented | OPS doc p95 &lt; 200 ms is hypothesis; no baseline. | SRE | After E1/E2 impl: run load/smoke, log query_ms, compute. |
| Unresolved station_cd in SOT/09 example (e.g. 1453) confirmed against v0.8-06 output? | Doc accuracy and testability. | QA/Data | Run v0.8-06 after STEP5 import; confirm one station_cd is unresolved; cross-check SOT/09. |
| config.safe.php in .gitignore — does it hold secrets? | Redundancy vs CI/trackability. | Security | Inspect config.safe.php; if no secrets, consider removing from .gitignore (policy). |

---

## 3) ROLE REVIEWS (Why / Risk / Minimal Fix / Verification)

### 3.1 CTO (Chair)
- **Strengths:** STEP4~8 SoT and directory layout clear; LOCK (D1~D5, STEP5.2) documented; root PS1 and .gitignore fixes done.
- **Risks:** E1/E2 spec-only — API not callable until implementation; no automated regression for STEP4 row counts.
- **Minimal Fix:** None for MVP Core. MVP+: add one runbook/VERIFY step for STEP4 row-count assertion (270, 8364). Roadmap: consider CI run of key validate SQL.
- **Verification:** No *.ps1 at root; .gitignore parses; rollback doc exists; SOT/09 unresolved example has footnote.

### 3.2 PM/Ops (Program PM)
- **Strengths:** Execution order (STEP4 A→B→C, STEP5 import, STEP6 view) and migration order (sql/INDEX.md, OPS_DB_MIGRATIONS) documented.
- **Risks:** Runbook/VERIFY_RUNNER does not yet assert STEP4 counts; handoff checklist may omit “confirm v0.8-06 unresolved list”.
- **Minimal Fix:** Add to STEP4_RUN_LOCK or VERIFY_RUNNER: “Assert subway_edges_g1_v1.csv 270 rows, walk 8364 (or document tolerance).” Optional: add “Run v0.8-06 and confirm unresolved example for SOT/09.”
- **Verification:** Doc or runbook step exists; handoff doc references G1 view/query SoT.

### 3.3 Backend Lead (PHP/API)
- **Strengths:** app/inc structured; require paths updated; uploads path guarded; observability.php has get_trace_id; SOT/09 and v0.8-10 ready for implementation.
- **Risks:** E1/E2 not implemented — trace_id not wired in public/api for G1; contract is forward-looking.
- **Minimal Fix:** None for MVP Core. At E1/E2 impl: ensure trace_id in handler and log api_enter/db_query_*/api_exit per OPS_OBSERVABILITY_G1_API.
- **Verification:** When E1/E2 exist, logs contain trace_id and query_ms.

### 3.4 Data/ETL Lead (Python/CSV/JSON)
- **Strengths:** STEP4 A/B/C sequence and outputs documented; 270 edges, 8364 walk, 315 match; Python under scripts/python; QA JSON and validate SQL exist.
- **Risks:** Re-run order and data/derived paths only in docs; no automated regression for row counts.
- **Minimal Fix:** Add one line in STEP4_RUN_LOCK (or README) listing expected counts (270, 8364, 315) as regression check. Optional: single VERIFY step that runs v0.8-03 and asserts counts.
- **Verification:** Doc contains expected counts; optional runbook step.

### 3.5 DB Lead (MySQL)
- **Strengths:** v0.8-01/02/03 and view v0.8-07 in correct folders; v0.8-09/10 canonical queries; migration and rollback documented.
- **Risks:** View column “station_name” is edge-derived (from_station_cd/to_station_cd); no index checklist for E1/E2 by-name/by-code.
- **Minimal Fix:** SOT/08 or view comment already clarifies edge-derived semantics. Index strategy for E1/E2 deferred to implementation; document in SOT/09 or OPS at impl time.
- **Verification:** View and SOT/08 aligned; index decision documented when E1/E2 are implemented.

### 3.6 QA Lead
- **Strengths:** QA JSON, unmatched CSV, validate SQL (v0.8-03~06) exist; STEP4 paste area; SOT/09 unresolved example has footnote (confirm via v0.8-06).
- **Risks:** No automated run of validate SQL after pipeline; blank (93) edge cases not codified as test matrix.
- **Minimal Fix:** Optional: one smoke step “run v0.8-03 validate and assert counts” in VERIFY_RUNNER. Roadmap: test matrix for edges_unique / ambiguous / unresolved if UI/API exposes them.
- **Verification:** Optional step in runbook; test matrix doc if added.

### 3.7 Observability/SRE Lead
- **Strengths:** OPS_OBSERVABILITY_G1_API defines api_enter/db_query_start|end/api_exit and query_ms; observability.php supports get_trace_id; p95 “to be measured after E1/E2” stated.
- **Risks:** trace_id propagation to G1 handlers not implemented; p95 &lt; 200 ms is hypothesis.
- **Minimal Fix:** Done: sentence in OPS_OBSERVABILITY_G1_API that baseline will be measured post-implementation. At E1/E2 impl: wire trace_id and log fields.
- **Verification:** OPS doc states hypothesis; when E1/E2 exist, logs include trace_id and query_ms.

### 3.8 Security Lead
- **Strengths:** config.local.php and .env in .gitignore; uploads under public/uploads with realpath guard; observability masks sensitive keys and PII.
- **Risks:** config.safe.php in .gitignore — redundancy or policy; confirm no secrets.
- **Minimal Fix:** Confirm whether config.safe.php holds secrets; if not, consider removing from .gitignore (policy decision). Roadmap.
- **Verification:** .gitignore matches policy; no secrets in tracked config.

### 3.9 Release Manager
- **Strengths:** sql/INDEX.md and OPS_DB_MIGRATIONS define order; v0.8-01→02→ingest/validate; releases/v1.7 and archive/v0.6 clear; view rollback documented.
- **Risks:** None outstanding for MVP Core (root PS1 moved, .gitignore fixed, rollback added).
- **Minimal Fix:** None. MVP+: ensure handoff checklist includes “G1 view/query SoT and rollback” and “E1/E2 implementation readiness (SOT/09, v0.8-10).”
- **Verification:** Rollback in sql/views README and OPS_DB_MIGRATIONS; handoff doc references SoT.

---

## 4) GAP LIST (prioritized P1~P10)

| Priority | Gap | Risk | Minimal Fix | Verification | Owner | Target |
|----------|-----|------|-------------|--------------|-------|--------|
| P1 | (Closed) Root PS1 files | — | Moved to scripts/ps1; refs updated. | No *.ps1 at root. | — | Done |
| P2 | (Closed) .gitignore logs/verify_latest.md | — | Separated from comment line. | git check-ignore logs/verify_latest.md. | — | Done |
| P3 | (Closed) Unresolved example SOT/09 | — | Footnote: confirm via v0.8-06. | Doc has placeholder + verification note. | — | Done |
| P4 | (Closed) View rollback | — | sql/views README + OPS_DB_MIGRATIONS. | DROP VIEW documented. | — | Done |
| P5 | (Closed) p95 post-implementation note | — | OPS_OBSERVABILITY_G1_API sentence. | Sentence added. | — | Done |
| P6 | No regression check for STEP4 row counts (270, 8364) | Pipeline change could break counts silently. | Add to STEP4_RUN_LOCK or VERIFY_RUNNER: assert 270, 8364 (or tolerance). | Doc or runbook step exists. | Data/QA | MVP+ |
| P7 | trace_id not wired for E1/E2 | Observability contract applies at impl. | At E1/E2 impl: add trace_id to handler and logs. | When E1/E2 exist: logs contain trace_id. | Backend | MVP+ |
| P8 | config.safe.php in .gitignore | Redundancy; CI/trackability. | Confirm no secrets; remove from .gitignore if policy allows. | .gitignore matches policy. | Security | Roadmap |
| P9 | Index strategy for E1/E2 by-name/by-code | Performance at impl. | Document at implementation time in SOT/09 or OPS. | Not blocking. | DB | Roadmap |
| P10 | Automated validation (v0.8-03) in CI/runbook | Manual-only validation. | Optional: add step to verify runner or smoke. | Optional step in VERIFY_RUNNER. | QA | Roadmap |

---

## 5) DECISION LOG (LOCK)

| ID | Decision | Rationale | Tradeoff | Status |
|----|----------|-----------|----------|--------|
| D1 | Root must not contain script files; all PS1 under scripts/ps1. | Single place for scripts; CTO Report #1. | — | LOCK (done) |
| D2 | .gitignore: one pattern per line; comments on separate line. | Avoid parsing ambiguity. | — | LOCK (done) |
| D3 | E1/E2 remain spec-only for MVP Core; no public/* implementation required now. | Scope is contract + observability spec. | API not callable until impl. | LOCK |
| D4 | STEP4 row counts (270, 8364) are LOCK evidence; regression check deferred to MVP+. | Avoid scope creep; doc exists. | No automated check yet. | LOCK |
| D5 | p95 &lt; 200 ms is hypothesis until E1/E2 and traffic exist. | No baseline data. | Document “to be measured.” | LOCK (done) |
| D6 | Unresolved example in SOT/09: placeholder + “confirm via v0.8-06” in MVP Core. | Doc accuracy and testability. | One footnote. | LOCK (done) |
| D7 | View rollback documented in sql/views README and OPS_DB_MIGRATIONS. | Operational rollback clarity. | — | LOCK (done) |

**Proposed (no code):** None. All MVP Core action items from prior review are implemented. Next: MVP+ and Roadmap execution ordering.

---

## 6) ACTION ITEMS

| ID | Task | Owner | Target | Verification | Files likely touched |
|----|------|--------|--------|---------------|----------------------|
| A1 | (Done) Move root PS1 to scripts/ps1; update refs. | Release | MVP Core | No *.ps1 at root; refs point to scripts/ps1/. | Root, scripts/ps1/, README_PDF_PARSING.md |
| A2 | (Done) Fix .gitignore. | Security/Release | MVP Core | git check-ignore logs/verify_latest.md. | .gitignore |
| A3 | (Done) Unresolved example SOT/09 + footnote. | QA/Data | MVP Core | Example + “confirm via v0.8-06.” | docs/SOT/09_API_CONTRACTS_G1.md |
| A4 | (Done) Rollback for vw_subway_station_lines_g1. | Release | MVP Core | DROP VIEW in doc. | sql/views/README.md, docs/OPS/OPS_DB_MIGRATIONS.md |
| A5 | (Done) OPS_OBSERVABILITY_G1_API p95 post-implementation. | SRE | MVP Core | Sentence added. | docs/OPS/OPS_OBSERVABILITY_G1_API.md |
| A6 | Add STEP4 row-count assertion (270, 8364) to STEP4_RUN_LOCK or VERIFY_RUNNER. | Data/QA | MVP+ | Step or assertion text exists. | docs/OPS/STEP4_RUN_LOCK.md or docs/operations/VERIFY_RUNNER.md |
| A7 | At E1/E2 implementation: wire trace_id and log api_enter/db_query_*/api_exit. | Backend | MVP+ | E1/E2 logs contain trace_id, query_ms. | public/api (when impl) |
| A8 | Revisit config.safe.php in .gitignore (policy). | Security | Roadmap | .gitignore matches policy. | .gitignore |
| A9 | Optional: add v0.8-03 (or key validation) run to verify runner or smoke. | QA | Roadmap | Optional step in VERIFY_RUNNER or smoke. | docs/operations/VERIFY_RUNNER.md or runbook |

---

## 7) RISKS & MITIGATIONS

| Risk | Impact | Mitigation |
|------|--------|------------|
| E1/E2 not implemented; contract only | API not callable; integration tests deferred. | LOCK: spec-only for MVP Core; implement in MVP+ with trace_id and OPS log spec. |
| STEP4 row counts not asserted in automation | Pipeline regression undetected. | MVP+: add runbook/VERIFY step asserting 270, 8364 (or documented tolerance). |
| trace_id not in request context when E1/E2 built | Observability gap. | At E1/E2 impl: add trace_id in handler/middleware per OPS_OBSERVABILITY_G1_API. |
| p95 target unmeasured | SLO not evidence-based. | Documented as “measure after E1/E2”; baseline when traffic exists. |
| config.safe.php in .gitignore | Unclear if CI needs it tracked. | Roadmap: confirm no secrets; adjust .gitignore per policy. |
| View “station_name” semantics | Confusion with master station_cd. | SOT/08 and view comment state edge-derived; no code change. |

---

## 8) NEXT EXECUTION PLAN

### MVP Core (closeout)
- All MVP Core action items (A1~A5) **done**. No further code or doc changes required for “MVP Core” per this review.
- **Verification:** No *.ps1 at root; .gitignore correct; SOT/09 unresolved example has footnote; rollback in sql/views + OPS_DB_MIGRATIONS; OPS_OBSERVABILITY_G1_API states p95 post-implementation.

### MVP+
1. Add STEP4 row-count assertion (270 edges, 8364 walk) to STEP4_RUN_LOCK or VERIFY_RUNNER (doc/runbook only).
2. Implement E1/E2 per SOT/09 and v0.8-10; wire trace_id and log api_enter/db_query_*/api_exit per OPS_OBSERVABILITY_G1_API.
3. After E1/E2 deployed: collect query_ms; document p50/p95; adjust 200 ms target if needed.
4. Document index strategy for E1/E2 (by-name, by-code) at implementation time (SOT/09 or OPS).
5. **Verification:** Runbook/VERIFY step exists; E1/E2 logs show trace_id and query_ms.

### Roadmap
1. Revisit config.safe.php in .gitignore (confirm no secrets; remove from .gitignore if policy allows).
2. Consider automated run of sql/validate/v0.8-03 (or key validations) in CI or verify runner.
3. Expand test matrix for blank stations (edges_unique / ambiguous / unresolved) if UI or API exposes them.
4. **Verification:** Policy and .gitignore aligned; optional CI step; test matrix doc if added.

---

**End of meeting minutes.**
