# Subscription alert_type 매칭 (v1.7-08)

Targeting Preview / Publish guard에서 event_type이 구독의 alert_type CSV에 **정확히 포함**될 때만 타겟으로 잡도록 통일.

## 규칙

- `app_subscriptions.alert_type`는 CSV 저장(예: `'strike,event,update'`).
- 매칭: `FIND_IN_SET(event_type, REPLACE(alert_type, ' ', '')) > 0` (공백 제거 후 콤마 구분 토큰 exact match).
- LIKE '%event_type%' 제거 → 오탐/누락 방지.

## 예시

- event_type=`strike`, alert_type=`strike,event` → 매칭됨.
- event_type=`strike`, alert_type=`strikethrough` → 매칭 안 됨(FIND_IN_SET 토큰 단위).

## 구현

- **alert_ops.php:** Publish guard 타겟 수·타겟 유저 목록, Targeting Preview 카운트·리스트 4곳에서 FIND_IN_SET 사용.
- **app/inc/subscription_match.php:** PHP 헬퍼 `subscription_allows_event_type($csv, $eventType)` (CSV split/trim, lowercase, in_array). SQL만 쓸 경우 선택 사용.

## Non-goals

- 복잡한 조건식/우선순위 엔진 없음. alert_type CSV exact 토큰 매칭만.
