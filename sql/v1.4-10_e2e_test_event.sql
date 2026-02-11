-- v1.4-10 E2E test only. 용도: 이벤트 1건 생성 → alerts 노출 → deliveries 기록 검증.
-- 운영 로직 변경 없음. 필요 시 1번 블록만 실행 후 /user/alerts.php 접속, 이어서 2번 검증 실행.

-- 1) E2E 테스트 이벤트 1건 INSERT (content_hash = NOW 기반, 중복 시 무시)
INSERT IGNORE INTO app_alert_events (event_type, title, body, ref_type, ref_id, route_label, content_hash, published_at, created_at)
SELECT 'update', '[E2E test] v1.4-10', 'E2E test event for alerts → delivery logging.', 'route', 1, 'R1',
  SHA2(CONCAT('e2e_test_v1_4_10_', NOW(3)), 256), NOW(), NOW();

-- 2) 검증 SELECT 3개 (alerts 노출 전/후 비교용)
SELECT 'app_alert_events' AS tbl, COUNT(*) AS cnt FROM app_alert_events;
SELECT 'app_alert_deliveries' AS tbl, COUNT(*) AS cnt FROM app_alert_deliveries;
SELECT id, alert_event_id, user_id, channel, status, sent_at, created_at
FROM app_alert_deliveries ORDER BY created_at DESC LIMIT 20;
