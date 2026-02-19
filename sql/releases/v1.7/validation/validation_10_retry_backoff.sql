-- v1.7-10 Retry/backoff. Read-only.

-- 1) retry_count 컬럼 존재 확인
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_alert_deliveries' AND COLUMN_NAME = 'retry_count';

-- 2) status별 카운트
SELECT status, COUNT(*) AS cnt FROM app_alert_deliveries GROUP BY status;

-- 3) failed 중 backoff 미경과(스킵 대상) 샘플 20
SELECT id, user_id, alert_event_id, retry_count, last_error, created_at
FROM app_alert_deliveries
WHERE status = 'failed' AND channel = 'web'
  AND NOT (
    (retry_count = 1 AND created_at <= NOW() - INTERVAL 1 MINUTE)
    OR (retry_count = 2 AND created_at <= NOW() - INTERVAL 5 MINUTE)
    OR (retry_count = 3 AND created_at <= NOW() - INTERVAL 15 MINUTE)
    OR (retry_count >= 4 AND created_at <= NOW() - INTERVAL 60 MINUTE)
  )
ORDER BY created_at DESC
LIMIT 20;

-- 4) 최근 20건 (retry_count, last_error, status, delivered_at)
SELECT id, alert_event_id, user_id, status, retry_count, last_error, delivered_at, created_at
FROM app_alert_deliveries
ORDER BY created_at DESC, id DESC
LIMIT 20;
