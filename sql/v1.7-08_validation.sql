-- v1.7-08 Subscription alert_type matching (FIND_IN_SET). Read-only. Run after alert_ops 적용.

-- 1) 특정 EID에 대해 타겟 후보 subscriptions 수 (EID 치환)
-- SET @eid = 1;
-- SELECT e.id AS event_id, e.event_type, e.route_label, e.ref_id,
--   (SELECT COUNT(DISTINCT s.user_id) FROM app_subscriptions s
--    WHERE s.is_active = 1 AND s.target_type = 'route'
--      AND s.target_id = CONCAT(e.ref_id, '_', e.route_label)
--      AND FIND_IN_SET(e.event_type, REPLACE(s.alert_type, ' ', '')) > 0) AS target_sub_cnt
-- FROM app_alert_events e WHERE e.id = @eid;

-- 2) event_type별 매칭 결과 샘플 (subscriptions 20건, event_type 치환)
-- SET @etype = 'strike';
-- SELECT s.id, s.user_id, s.target_id, s.alert_type,
--   FIND_IN_SET(@etype, REPLACE(s.alert_type, ' ', '')) AS find_in_set_result
-- FROM app_subscriptions s
-- WHERE s.is_active = 1 AND s.target_type = 'route'
-- ORDER BY s.id DESC LIMIT 20;

-- 편의: 위 쿼리 사용 시 @eid / @etype 값을 실제 ID·event_type으로 치환 후 실행.
