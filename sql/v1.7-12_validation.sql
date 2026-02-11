-- v1.7-12 Ops control. Read-only.

-- 1) deliveries 상태별 + failed retry_count 분포
SELECT status, COUNT(*) AS cnt FROM app_alert_deliveries GROUP BY status;

SELECT retry_count, COUNT(*) AS cnt
FROM app_alert_deliveries
WHERE status = 'failed'
GROUP BY retry_count
ORDER BY retry_count;

-- 2) 최근 metrics 이벤트 10
SELECT id, event_type, title, ref_id, route_label, published_at
FROM app_alert_events
WHERE title LIKE '[Metrics]%'
ORDER BY created_at DESC, id DESC
LIMIT 10;

-- 3) content_hash 중복 0 rows
SELECT content_hash, COUNT(*) AS cnt
FROM app_alert_events
WHERE content_hash IS NOT NULL
GROUP BY content_hash
HAVING COUNT(*) > 1;
