# v1.7-10 Gate (Retry/Backoff)

## Gate 항목

| # | 항목 | Evidence |
|---|------|----------|
| G1 | retry_count 컬럼 | v1.7-10_schema 적용, validation 컬럼 존재. |
| G2 | backoff 적용 | failed는 1/5/15/60분 창 경과 후만 처리. skipped_backoff 출력. |
| G3 | 실패 시 상태 | status=failed, last_error, retry_count+1, delivered_at/sent_at NULL. |
| G4 | 문서 | docs/releases/v1.7/specs/spec_10_retry_backoff.md, docs/releases/v1.7/smoke/smoke_10_retry_backoff.md, sql/releases/v1.7/validation/validation_10_retry_backoff.sql. |

## Non-goals

- 실제 외부 채널 연동, 큐 워커/크론 등록.
