# v1.7-11 Gate (Real metrics ingest)

## Gate 항목

| # | 항목 | Evidence |
|---|------|----------|
| G1 | 스크립트 실행 | run_alert_ingest_real_metrics inserted/skipped_duplicate 출력. |
| G2 | metrics 이벤트 | title [Metrics] Review needed, ref_type=route, ref_id/route_label NOT NULL. |
| G3 | content_hash 중복 0 | validation 쿼리 2 결과 0 rows. |
| G4 | ref contract 위반 0 | validation 쿼리 3 결과 0 rows. |

## Non-goals

- 외부 API, admin 승인 연동.
