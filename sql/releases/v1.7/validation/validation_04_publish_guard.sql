-- v1.7-04 Approval + Publish guard validation (read-only)

-- 1) draft_cnt / published_cnt
SELECT
  SUM(CASE WHEN published_at IS NULL THEN 1 ELSE 0 END) AS draft_cnt,
  SUM(CASE WHEN published_at IS NOT NULL THEN 1 ELSE 0 END) AS published_cnt
FROM app_alert_events;

-- 2) 최근 5건 이벤트 (published_at 포함)
SELECT id, event_type, title, ref_type, ref_id, route_label, published_at, created_at
FROM app_alert_events
ORDER BY id DESC
LIMIT 5;

-- 3) 특정 EID의 target_user_cnt (v1.7-03 동일 로직). EID=8 등으로 치환
SELECT COUNT(DISTINCT s.user_id) AS target_user_cnt
FROM app_alert_events e
JOIN app_subscriptions s ON s.is_active = 1 AND s.target_type = 'route'
  AND s.target_id = CONCAT(e.ref_id, '_', e.route_label)
  AND s.alert_type LIKE CONCAT('%', e.event_type, '%')
WHERE e.id = 8
  AND e.ref_type = 'route'
  AND e.ref_id IS NOT NULL
  AND e.route_label IS NOT NULL;
