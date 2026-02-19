# v1.7-07 Smoke (Outbound stub)

1. `sql/releases/v1.7/schema/schema_07_outbound_stub.sql` 적용. validation으로 delivered_at/last_error 존재 확인.
2. draft 1건 Publish → queued_cnt 확인. Workbench에서 pending 건수 확인.
3. php scripts/php/run_delivery_outbound_stub.php --limit=200 실행. sent 증가, pending 감소 확인.
4. validation: status별 카운트, 최근 20건에 delivered_at/last_error 확인.
