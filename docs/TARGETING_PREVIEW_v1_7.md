# Targeting Preview (v1.7-03)

운영자가 알림을 만들 때, 발송 전에 해당 알림이 누구에게 매칭되는지(대상 유저 수/리스트)를 프리뷰하는 규칙과 SQL 예시.

## 1. 타겟팅 규칙 (v1)

**이벤트 조건:** app_alert_events.ref_type='route', ref_id IS NOT NULL, route_label IS NOT NULL. 초안(published_at NULL)이어도 프리뷰 가능.

**구독 매칭:** app_subscriptions.is_active=1, target_type='route', target_id = CONCAT(ref_id,'_',route_label). alert_type(csv)에 event_type이 포함되어야 함. 예: event_type='strike' → alert_type LIKE '%strike%' (정교화는 v1.7-05).

## 2. SQL 예시 (read-only)

**target_user_cnt:**
```sql
SELECT COUNT(DISTINCT s.user_id) AS target_user_cnt
FROM app_subscriptions s
WHERE s.is_active = 1 AND s.target_type = 'route'
  AND s.target_id = CONCAT(:ref_id, '_', :route_label)
  AND s.alert_type LIKE CONCAT('%', :event_type, '%');
```

**target_user_list (LIMIT 20):**
```sql
SELECT u.id AS user_id, u.display_name, u.email, s.target_id AS subscription_target_id, s.alert_type
FROM app_subscriptions s
JOIN app_users u ON u.id = s.user_id
WHERE s.is_active = 1 AND s.target_type = 'route'
  AND s.target_id = CONCAT(:ref_id, '_', :route_label)
  AND s.alert_type LIKE CONCAT('%', :event_type, '%')
ORDER BY s.user_id
LIMIT 20;
```

## 3. 한계점

문자열 LIKE 사용으로 alert_type 정규화 필요 시 v1.7-05. 프리뷰만 수행, 실제 발송/채널/승인/스케줄러는 v1.7-04~.
