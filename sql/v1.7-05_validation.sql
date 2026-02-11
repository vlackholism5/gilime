-- v1.7-05 Deliveries pre-write validation (read-only)
-- Replace 8 with actual EID as needed.

-- (A) 특정 EID에 대해 deliveries pending / shown count
SELECT status, COUNT(*) AS cnt
FROM app_alert_deliveries
WHERE alert_event_id = 8
GROUP BY status;

-- (B) 중복 0 rows (user_id, alert_event_id, channel)
SELECT user_id, alert_event_id, channel, COUNT(*) AS c
FROM app_alert_deliveries
GROUP BY user_id, alert_event_id, channel
HAVING COUNT(*) > 1;

-- (C) 최근 deliveries 20건
SELECT id, alert_event_id, user_id, channel, status, sent_at, created_at
FROM app_alert_deliveries
ORDER BY created_at DESC
LIMIT 20;
