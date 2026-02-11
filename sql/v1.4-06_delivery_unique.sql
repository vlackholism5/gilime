-- v1.4-06: delivery logging — one row per (user, event, channel)
-- Run on PC after app_alert_deliveries exists.

ALTER TABLE app_alert_deliveries
  ADD UNIQUE KEY uq_delivery_user_event_channel (user_id, alert_event_id, channel);
