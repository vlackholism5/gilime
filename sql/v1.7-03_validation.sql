-- v1.7-03 Targeting Preview validation (read-only)
-- Replace :EID with actual event id (e.g. 8) before running.

-- 1) 특정 이벤트(EID) 기준 target_user_cnt
SELECT COUNT(DISTINCT s.user_id) AS target_user_cnt
FROM app_alert_events e
JOIN app_subscriptions s ON s.is_active = 1 AND s.target_type = 'route'
  AND s.target_id = CONCAT(e.ref_id, '_', e.route_label)
  AND s.alert_type LIKE CONCAT('%', e.event_type, '%')
WHERE e.id = 8
  AND e.ref_type = 'route'
  AND e.ref_id IS NOT NULL
  AND e.route_label IS NOT NULL;

-- 2) target_user_list 20
SELECT u.id AS user_id, u.display_name, u.email, s.target_id AS subscription_target_id, s.alert_type
FROM app_alert_events e
JOIN app_subscriptions s ON s.is_active = 1 AND s.target_type = 'route'
  AND s.target_id = CONCAT(e.ref_id, '_', e.route_label)
  AND s.alert_type LIKE CONCAT('%', e.event_type, '%')
JOIN app_users u ON u.id = s.user_id
WHERE e.id = 8
  AND e.ref_type = 'route'
  AND e.ref_id IS NOT NULL
  AND e.route_label IS NOT NULL
ORDER BY s.user_id
LIMIT 20;

-- 3) alert_type이 event_type을 포함하지 않아 제외되는 케이스 확인용 (구독은 있으나 해당 event_type 없음)
-- 샘플: event_type='update' 인 이벤트에 대해, alert_type에 'update'가 없는 구독만 조회 (같은 target_id)
SELECT s.id, s.user_id, s.target_id, s.alert_type
FROM app_subscriptions s
WHERE s.is_active = 1 AND s.target_type = 'route'
  AND s.target_id = (SELECT CONCAT(ref_id, '_', route_label) FROM app_alert_events WHERE id = 8 LIMIT 1)
  AND s.alert_type NOT LIKE '%update%'
LIMIT 20;
