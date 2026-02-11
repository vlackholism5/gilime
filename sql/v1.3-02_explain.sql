-- v1.3-02: ops_dashboard "Docs needing review" 변경 후 EXPLAIN
-- 실행: Workbench에서 아래 쿼리 실행 후 결과를 주석 빈칸에 붙여넣기.

EXPLAIN
SELECT j.source_doc_id, j.id AS latest_parse_job_id, j.updated_at,
  COALESCE(agg.pending_total, 0) AS pending_total,
  COALESCE(agg.pending_risky_total, 0) AS pending_risky_total
FROM shuttle_doc_job_log j
INNER JOIN (
  SELECT source_doc_id, MAX(id) AS mid
  FROM shuttle_doc_job_log
  WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
  GROUP BY source_doc_id
) t ON j.source_doc_id = t.source_doc_id AND j.id = t.mid
LEFT JOIN (
  SELECT source_doc_id, created_job_id,
    COUNT(*) AS pending_total,
    SUM(CASE WHEN match_method = 'like_prefix' OR match_method IS NULL THEN 1 ELSE 0 END) AS pending_risky_total
  FROM shuttle_stop_candidate
  WHERE status = 'pending'
  GROUP BY source_doc_id, created_job_id
) agg ON j.source_doc_id = agg.source_doc_id AND j.id = agg.created_job_id
WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
ORDER BY pending_risky_total DESC, pending_total DESC;

-- 실행 결과 붙여넣기 (id, select_type, table, type, key, rows, Extra 등):
-- (여기 채움)
