# Outbound Stub (v1.7-07)

pending deliveries를 가져와 sent/failed로 전환하는 운영 스텁. 실제 이메일/문자/푸시 연동 없음.

## 흐름

- Publish → pending 적재(v1.7-05). user/alerts 접속 → pending→shown(v1.7-05).
- `php scripts/run_delivery_outbound_stub.php --limit=200`: status='pending' 행을 created_at ASC로 LIMIT, 각 row를 status='sent', delivered_at=NOW(), last_error=NULL로 업데이트. 예외 시 status='failed', last_error 저장.
- shown은 스텁에서 건드리지 않음(pending만 sent로 전환).

## 스키마

- app_alert_deliveries: delivered_at DATETIME NULL, last_error VARCHAR(255) NULL.

## Non-goals

- 실제 이메일/SMS/푸시 연동 없음.
