-- v1.7-09 Ops Summary. Read-only.

-- 1) Approvals 최근 20
SELECT a.id, a.alert_event_id, a.actor_user_id, a.action, a.note, a.created_at
FROM app_alert_approvals a
ORDER BY a.created_at DESC, a.id DESC
LIMIT 20;

-- 2) Deliveries 상태별 count
SELECT status, COUNT(*) AS cnt FROM app_alert_deliveries GROUP BY status;

-- 3) Events draft / published count
SELECT
  SUM(CASE WHEN published_at IS NULL THEN 1 ELSE 0 END) AS draft_cnt,
  SUM(CASE WHEN published_at IS NOT NULL THEN 1 ELSE 0 END) AS published_cnt
FROM app_alert_events;
