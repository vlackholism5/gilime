-- v1.7-02: Read-only validation. PC(Workbench)에서 실행 후 결과 확인.

-- 1) published_at nullability
SELECT COLUMN_NAME, IS_NULLABLE, DATA_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'app_alert_events'
  AND COLUMN_NAME = 'published_at';
-- 기대: IS_NULLABLE = 'YES'

-- 2) draft / published counts
SELECT
  SUM(CASE WHEN published_at IS NULL THEN 1 ELSE 0 END) AS draft_cnt,
  SUM(CASE WHEN published_at IS NOT NULL THEN 1 ELSE 0 END) AS published_cnt
FROM app_alert_events;

-- 3) content_hash duplicate check (기대: 0 rows)
SELECT content_hash, COUNT(*) AS c
FROM app_alert_events
WHERE content_hash IS NOT NULL
GROUP BY content_hash
HAVING COUNT(*) > 1;
