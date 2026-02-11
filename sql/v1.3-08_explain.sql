-- v1.3-08: ops_dashboard Docs needing review — j2에 USE INDEX(idx_joblog_doc_type_status_id) 적용 후 EXPLAIN
-- 실행: Workbench에서 아래 쿼리 실행 후 "(여기 채움)" 블록에 결과 붙여넣기. j2의 key가 idx_joblog_doc_type_status_id로 바뀌었는지 확인.

EXPLAIN
SELECT j.source_doc_id, j.id AS latest_parse_job_id, j.updated_at,
  COALESCE(agg.pending_total, 0) AS pending_total,
  COALESCE(agg.pending_risky_total, 0) AS pending_risky_total
FROM shuttle_doc_job_log j
LEFT JOIN (
  SELECT source_doc_id, created_job_id,
    COUNT(*) AS pending_total,
    SUM(CASE WHEN match_method = 'like_prefix' OR match_method IS NULL THEN 1 ELSE 0 END) AS pending_risky_total
  FROM shuttle_stop_candidate FORCE INDEX (idx_cand_doc_job_status_method)
  WHERE status = 'pending'
  GROUP BY source_doc_id, created_job_id
) agg ON j.source_doc_id = agg.source_doc_id AND j.id = agg.created_job_id
WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
  AND NOT EXISTS (
    SELECT 1 FROM shuttle_doc_job_log j2 USE INDEX (idx_joblog_doc_type_status_id)
    WHERE j2.source_doc_id = j.source_doc_id
      AND j2.job_type = 'PARSE_MATCH' AND j2.job_status = 'success'
      AND j2.id > j.id
  )
ORDER BY j.updated_at DESC;

-- 실행 결과: j2 key=idx_joblog_doc_type_status_id 로 변경됨 (USE INDEX 적용)
-- id | select_type | table      | type | key   | rows | Extra
-- 1  | PRIMARY     | j          | ref  | idx_job_type_status | 13 | Using filesort
-- 1  | PRIMARY     | j2         | ref  | idx_joblog_doc_type_status_id | 5 | Using where; Not exists; Using index
-- 1  | PRIMARY     | <derived2> | ref  | <auto_key0> | 2 | Using where
-- 2  | DERIVED     | shuttle_stop_candidate | index | idx_cand_doc_job_status_method | 39 | Using where; Using index
