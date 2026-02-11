-- v1.4-02 Validation: verify app_* tables exist and sample joins
-- Run after v1.4-02_schema.sql on PC

-- 1) Tables
SHOW TABLES LIKE 'app_%';

-- 2) Counts (should be 0 or more)
SELECT 'app_users' AS tbl, COUNT(*) AS cnt FROM app_users
UNION ALL SELECT 'app_user_sessions', COUNT(*) FROM app_user_sessions
UNION ALL SELECT 'app_subscriptions', COUNT(*) FROM app_subscriptions
UNION ALL SELECT 'app_alert_events', COUNT(*) FROM app_alert_events
UNION ALL SELECT 'app_alert_deliveries', COUNT(*) FROM app_alert_deliveries;

-- 3) Sample join: sessions -> users
SELECT s.id, s.user_id, u.display_name, s.expires_at
FROM app_user_sessions s
JOIN app_users u ON u.id = s.user_id
LIMIT 5;

-- 4) Sample join: subscriptions -> users
SELECT sub.id, sub.user_id, sub.target_type, sub.target_id, sub.is_active, u.display_name
FROM app_subscriptions sub
JOIN app_users u ON u.id = sub.user_id
LIMIT 5;

-- 5) Sample: events (no join)
SELECT id, event_type, title, published_at, created_at FROM app_alert_events ORDER BY created_at DESC LIMIT 5;
