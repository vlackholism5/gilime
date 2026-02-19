-- v1.7-02: Allow draft alerts (published_at NULL). PC(Workbench)에서만 실행.
-- 사전 확인: published_at이 NOT NULL이면 아래 ALTER 실행. 이미 NULL 허용이면 실행 불필요.
-- SHOW COLUMNS FROM app_alert_events WHERE Field = 'published_at';
-- information_schema: SELECT IS_NULLABLE FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_alert_events' AND COLUMN_NAME = 'published_at';
-- IS_NULLABLE = 'NO' 일 때만 아래 실행.

ALTER TABLE app_alert_events
  MODIFY published_at DATETIME NULL;

-- ---------- 롤백 (필요 시. published_at에 NULL row가 있으면 NOT NULL 복원 불가 → 확인 필요) ----------
-- UPDATE app_alert_events SET published_at = COALESCE(published_at, created_at) WHERE published_at IS NULL;
-- ALTER TABLE app_alert_events MODIFY published_at DATETIME NOT NULL;
-- ----------
