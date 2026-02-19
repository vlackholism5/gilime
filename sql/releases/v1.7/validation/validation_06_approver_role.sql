-- v1.7-06 validation (read-only)

-- app_users.role 존재 및 값
SELECT id, email, display_name, role FROM app_users ORDER BY id LIMIT 20;

-- app_alert_approvals 최근 20건
SELECT id, alert_event_id, actor_user_id, action, note, created_at
FROM app_alert_approvals
ORDER BY created_at DESC
LIMIT 20;

-- action별 건수
SELECT action, COUNT(*) AS cnt FROM app_alert_approvals GROUP BY action;
