# Delivery semantics (v1.5)

`app_alert_deliveries`는 **실제로 HTML 테이블에 렌더된 이벤트에만** 기록합니다. 필터·페이지 적용 후 최종 목록 기준입니다.

---

## 1. Rule

- **Only events that are rendered in the alerts HTML table** should trigger a delivery insert attempt.
- 적용 순서: event_type 필터 → route_label 필터 → subscribed_only 필터 → **pagination (LIMIT/OFFSET)** → 이 결과 목록에 대해서만 `record_alert_delivery` 호출.

---

## 2. Idempotency

- UNIQUE `uq_delivery_user_event_channel (user_id, alert_event_id, channel)` 사용.
- INSERT 시 중복이면 ON DUPLICATE KEY UPDATE (또는 INSERT IGNORE 후 무시).  
  **같은 사용자가 같은 이벤트를 다시 보면** 건수는 늘지 않고, sent_at 등만 갱신 가능.

---

## 3. Web channel constants

| 컬럼 | 값 |
|------|-----|
| channel | `'web'` |
| status | `'shown'` |
| sent_at | NOW() |

---

## 4. Refresh behavior

- 같은 페이지를 새로고침해도, 이미 delivery가 있는 (user_id, alert_event_id, channel) 조합은 UNIQUE로 인해 **새 행이 생기지 않음**.  
  따라서 deliveries 총 건수는 새로 노출된 이벤트가 있을 때만 증가.
