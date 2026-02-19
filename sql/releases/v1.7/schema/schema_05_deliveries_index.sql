-- v1.7-05 Deliveries queue. Optional index for status/created_at filtering.
-- Run on PC (Workbench). Skip if index already exists or not needed.

-- Pre-check (optional): SHOW INDEX FROM app_alert_deliveries;

ALTER TABLE app_alert_deliveries
  ADD INDEX idx_deliveries_status_created (status, created_at);

-- Rollback: ALTER TABLE app_alert_deliveries DROP INDEX idx_deliveries_status_created;
