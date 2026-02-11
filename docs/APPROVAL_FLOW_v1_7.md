# Approval Flow (v1.7-04)

운영자 승인·발행 최소 플로우. Draft → Published 전환 시 타겟 수 확인 후 Publish 허용/차단.

## 상태 정의

- **draft:** `app_alert_events.published_at IS NULL`
- **published:** `app_alert_events.published_at IS NOT NULL`

## 운영자 절차

1. Create draft (published_at 비우고 생성)
2. Targeting preview 확인 (event_id로 target_user_cnt·리스트 확인)
3. Publish: target_user_cnt > 0 이면 허용(published_at = NOW()), 0이면 차단(flash=blocked_no_targets)
4. user/alerts.php에서 발행된 알림 노출 확인

## Publish 정책 (v1.7-04)

- Publish 버튼 클릭 시 targeting preview count를 먼저 계산.
- **target_user_cnt = 0** → publish 차단, flash=blocked_no_targets.
- **target_user_cnt > 0** → publish 허용, published_at = NOW(), flash=published.
- 실제 발송(deliveries 선생성) 없음. content_hash 고정 유지.

## Non-goals (v1.7-04)

- deliveries 선생성(발송) 금지.
- outbound(email/SMS) 금지.
- 타겟팅 정교화(정규화) 금지.
- role/권한 시스템 금지.
