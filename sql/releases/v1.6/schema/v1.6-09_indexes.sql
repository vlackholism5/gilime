-- v1.6-09: alert ops/audit 조회 안정화용 인덱스 후보 (선택).
-- 실제 적용은 PC에서만. 적용 전 SHOW INDEX로 존재 확인 후 없을 때만 CREATE 실행.
-- (MySQL은 CREATE INDEX IF NOT EXISTS 미지원. 중복 생성 시 Warning 1831 발생 가능.)

-- ---------- 사전 확인 (주석 해제 후 실행, 0 rows일 때만 해당 CREATE 실행) ----------
-- SHOW INDEX FROM app_alert_events WHERE Key_name = 'idx_app_alert_events_route_published';
-- SHOW INDEX FROM app_alert_deliveries WHERE Key_name = 'idx_deliveries_event_created';
-- SHOW INDEX FROM app_alert_deliveries WHERE Key_name = 'idx_deliveries_user_created';
-- ----------

-- app_alert_events: route_label + published_at (필터 + ORDER BY published_at DESC)
-- 대안: (published_at, event_type) 도 가능. 둘 중 1개만 적용해도 됨.
CREATE INDEX idx_app_alert_events_route_published
  ON app_alert_events(route_label, published_at);

-- app_alert_deliveries: alert_ops audit 필터 + 정렬
CREATE INDEX idx_deliveries_event_created
  ON app_alert_deliveries(alert_event_id, created_at);

CREATE INDEX idx_deliveries_user_created
  ON app_alert_deliveries(user_id, created_at);

-- ---------- 롤백 (필요 시만) ----------
-- DROP INDEX idx_app_alert_events_route_published ON app_alert_events;
-- DROP INDEX idx_deliveries_event_created ON app_alert_deliveries;
-- DROP INDEX idx_deliveries_user_created ON app_alert_deliveries;
-- ----------
