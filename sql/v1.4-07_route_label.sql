-- v1.4-07 / v1.4-08: filter alerts by route; set by run_alert_generate_from_metrics.php
-- Run on PC.

ALTER TABLE app_alert_events
  ADD COLUMN route_label VARCHAR(64) DEFAULT NULL AFTER ref_id,
  ADD INDEX idx_app_alert_events_route_label (route_label);
