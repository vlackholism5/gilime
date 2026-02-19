-- v1.7-07 Outbound stub. Run on PC (Workbench).

-- Pre-check (optional): SHOW COLUMNS FROM app_alert_deliveries;

ALTER TABLE app_alert_deliveries
  ADD COLUMN delivered_at DATETIME DEFAULT NULL AFTER sent_at;

ALTER TABLE app_alert_deliveries
  ADD COLUMN last_error VARCHAR(255) DEFAULT NULL AFTER status;

-- Optional: idx_deliveries_status_created (if not present from v1.7-05)
-- SHOW INDEX FROM app_alert_deliveries WHERE Key_name = 'idx_deliveries_status_created';
-- ALTER TABLE app_alert_deliveries ADD INDEX idx_deliveries_status_created (status, created_at);
