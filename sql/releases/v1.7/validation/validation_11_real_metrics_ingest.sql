-- v1.7-11 Real metrics ingest. Read-only.

-- 1) 최근 20개 app_alert_events 중 metrics 이벤트 (title like '[Metrics]%')
SELECT id, event_type, title, ref_type, ref_id, route_label, content_hash, published_at
FROM app_alert_events
WHERE title LIKE '[Metrics]%'
ORDER BY created_at DESC
LIMIT 20;

-- 2) content_hash 중복 0 rows 기대
SELECT content_hash, COUNT(*) AS cnt
FROM app_alert_events
WHERE content_hash IS NOT NULL
GROUP BY content_hash
HAVING COUNT(*) > 1;

-- 3) route/ref contract: ref_type='route' 이면 ref_id, route_label NOT NULL
SELECT id, ref_type, ref_id, route_label
FROM app_alert_events
WHERE ref_type = 'route' AND (ref_id IS NULL OR route_label IS NULL)
LIMIT 20;

-- 4) ingest 후보 source: 최근 PARSE_MATCH success 20
SELECT j.id, j.source_doc_id, j.updated_at
FROM shuttle_doc_job_log j
WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
ORDER BY j.id DESC
LIMIT 20;
