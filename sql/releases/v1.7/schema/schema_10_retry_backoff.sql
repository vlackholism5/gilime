-- v1.7-10 Retry/backoff. Run on PC (Workbench).
-- Pre-check: only add if column does not exist.
-- SELECT COLUMN_NAME FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_alert_deliveries' AND COLUMN_NAME = 'retry_count';
-- If 0 rows, run below:

ALTER TABLE app_alert_deliveries
  ADD COLUMN retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_error;
