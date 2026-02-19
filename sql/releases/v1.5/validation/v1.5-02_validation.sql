-- v1.5-02: Alert ref contract validation (read-only).
-- Fix suggestions in comments only; no UPDATE by default.

-- 1) ref_type='route' contract violations (ref_id or route_label NULL)
SELECT id, event_type, title, ref_type, ref_id, route_label, published_at
FROM app_alert_events
WHERE ref_type = 'route' AND (ref_id IS NULL OR route_label IS NULL)
ORDER BY id DESC
LIMIT 50;
-- Fix suggestion: UPDATE app_alert_events SET ref_id = ?, route_label = ? WHERE id = ?;
-- Or set ref_type = NULL if no valid reference.

-- 2) ref_type='doc' contract violations (ref_id NULL)
SELECT id, event_type, title, ref_type, ref_id, route_label, published_at
FROM app_alert_events
WHERE ref_type = 'doc' AND ref_id IS NULL
ORDER BY id DESC
LIMIT 50;
-- Fix suggestion: UPDATE app_alert_events SET ref_id = ? WHERE id = ?;
-- Or set ref_type = NULL.

-- 3) deliveries duplication sanity (should be empty if UNIQUE works)
SELECT user_id, alert_event_id, channel, COUNT(*) AS cnt
FROM app_alert_deliveries
GROUP BY user_id, alert_event_id, channel
HAVING cnt > 1
LIMIT 20;
