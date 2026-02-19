-- v1.7-07 validation (read-only)

SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_alert_deliveries'
  AND COLUMN_NAME IN ('delivered_at', 'last_error', 'sent_at', 'status');

SELECT status, COUNT(*) AS cnt
FROM app_alert_deliveries
GROUP BY status;

SELECT id, alert_event_id, user_id, channel, status, last_error, sent_at, delivered_at, created_at
FROM app_alert_deliveries
ORDER BY created_at DESC
LIMIT 20;
