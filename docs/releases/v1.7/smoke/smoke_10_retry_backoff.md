# v1.7-10 Smoke (Retry/Backoff)

1. `sql/releases/v1.7/schema/schema_10_retry_backoff.sql` 적용 후 retry_count 컬럼 존재 확인.
2. (선택) failed 1건 확보: stub 실행 시 의도적 실패 또는 validation으로 failed 존재 확인.
3. `php scripts/php/run_delivery_outbound_stub.php --limit=200` 실행.
4. 출력에 processed/sent/failed/skipped_backoff 확인.
5. Workbench에서 `sql/releases/v1.7/validation/validation_10_retry_backoff.sql` 실행 — retry_count, status별 카운트, 최근 20건 확인.
