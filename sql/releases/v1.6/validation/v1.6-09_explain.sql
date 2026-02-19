-- v1.6-09: alert_ops / alert_audit 목록 쿼리 EXPLAIN. 실행 후 결과를 아래 주석 빈칸에 붙여넣기.
-- (선택) 인덱스 적용 전후 rows/type 비교용.

-- -------- 1) alert_ops list (event_type + route_label + published_from/to 적용 시) --------
EXPLAIN
SELECT id, event_type, title, ref_type, ref_id, route_label, published_at, created_at
FROM app_alert_events
WHERE 1=1
  AND event_type = 'event'
  AND route_label = 'R1'
ORDER BY published_at DESC, id DESC
LIMIT 200;

-- 실행 결과:
-- (붙여넣기)


-- -------- 2) alert_audit list (alert_event_id + user_id 적용 시) --------
EXPLAIN
SELECT id, alert_event_id, user_id, channel, status, sent_at, created_at
FROM app_alert_deliveries
WHERE alert_event_id = 4 AND user_id = 1
ORDER BY created_at DESC, id DESC
LIMIT 200;

-- 실행 결과:
-- (붙여넣기)
