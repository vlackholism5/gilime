# Approver role + approval audit (v1.7-06)

Publish는 **role='approver'** 인 사용자만 수행 가능. 승인 시도/차단/성공/실패를 app_alert_approvals에 기록.

## 역할

- **user (기본):** admin 로그인·초안 생성·타겟팅 프리뷰 가능. Publish 버튼 클릭 시 차단(flash=blocked_not_approver), approvals에 publish_blocked(not_approver) 기록.
- **approver:** Publish 허용. 기존 guard(target_user_cnt>0)·큐 적재 유지. 결과별 approvals: blocked_no_targets → publish_blocked(no_targets), 성공 → publish_success, 실패 → publish_failed.

## 스키마

- app_users.role VARCHAR(32) NOT NULL DEFAULT 'user'
- app_alert_approvals: id, alert_event_id, actor_user_id, action, note, created_at. action: publish_blocked, publish_success, publish_failed.

## Smoke

1. user_id=1 role='approver' 로 설정, user_id=2는 'user' 유지.
2. user_id=2로 Publish 시도 → blocked_not_approver + approvals 1건(publish_blocked).
3. user_id=1로 Publish → published + queued_cnt + approvals 1건(publish_success).
