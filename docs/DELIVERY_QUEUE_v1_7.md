# Delivery Queue (v1.7-05)

Publish 시점에 target users에 대해 app_alert_deliveries를 pending으로 선생성하고, user/alerts 노출 시 pending→shown으로 전환.

## 흐름

1. **Publish (admin/alert_ops):** target_user_cnt>0 통과 후 published_at=NOW(), target users(최대 1000)에 대해 INSERT IGNORE app_alert_deliveries(alert_event_id, user_id, channel='web', status='pending', sent_at=NULL). UNIQUE(user_id, alert_event_id, channel)로 중복 방지. flash=published_with_queue&queued_cnt=N.
2. **user/alerts 렌더:** 현재 페이지에 노출된 이벤트에 대해 user_id>0일 때만, 기존 row 중 status='pending'인 것만 UPDATE status='shown', sent_at=NOW(). 새 row 생성 없음(publish에서 이미 생성).
3. 새로고침 시 shown은 그대로 유지(pending만 업데이트하므로 중복 0).

## 제외 (Non-goals)

- 이메일/SMS 등 실발송 없음.
- 재시도 워커/크론 없음.
- 다중 채널 확장 없음.
