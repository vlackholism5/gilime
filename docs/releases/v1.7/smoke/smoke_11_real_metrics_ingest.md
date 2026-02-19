# v1.7-11 Smoke (Real metrics ingest)

1. php scripts/php/run_alert_ingest_real_metrics.php --since_minutes=1440 --limit=200 실행.
2. 출력 inserted / skipped_duplicate 확인.
3. Workbench: app_alert_events에서 title LIKE '[Metrics]%' 최근 건 확인.
4. `sql/releases/v1.7/validation/validation_11_real_metrics_ingest.sql` 실행: content_hash 중복 0 rows, ref contract 위반 0 rows 확인.
